<?php
	class System_1_0_0_Event
	{
		public static function run(&$system)
		{
			$system->global['param']['periodic'] = true; //Set that it is executed under periodic scheduler

			$time = explode(' ', date('i G j w n')); //Get the minute, hour, day, week and month values (NOTE : Do not use 'gmdate' here)
			$log = $system->log(__METHOD__);

			foreach(glob($system->global['define']['top'].'*/*/*/0/base/periodic.xml') as $schedule) //Check schedules on every applications
			{
				preg_match_all('/(\S+)="(\S*)"/', file_get_contents($schedule), $matches); //Find the configuration
				foreach($matches[1] as $key => $value) $info[$value] = $matches[2][$key]; //Find out the schedule information

				if($info['language'] != 'php') continue; //Only run the task defined for PHP

				if(!preg_match('/^[a-z]+$/', $info['id'])) continue; //Only allow alphabets for ID
				if(!preg_match('/^\d+$/', $info['version'])) continue; //Only allow digits for version

				$value = substr($info['interval'], 0, -1); //Numerical part of the interval

				if(!preg_match('/^\d+$/', $value)) continue; //If not a number, ignore
				if($value == 0) continue; //Ignore invalid entries

				$run = true;

				switch(substr($info['interval'], -1))
				{
					case 'm' : //For minutes. Only when the minute is divisible by the specified value
						if($time[0] % $value != 0) $run = false;
					break;

					case 'h' : //For hours. Only when the minute is 0 and the hour is divisible by the specified value
						if($time[0] != 0 || $time[1] % $value != 0) $run = false;
					break;

					case 'd' : //For every day. Only when minute and hour are both zero (Not allowing skipping days)
						if($value != 1 || $time[0] != 0 || $time[1] != 0) $run = false;
					break;

					case 'w' : //For every week. Only when minute, hour and week are all zero (Not allowing skipping weeks)
						if($value != 1 || $time[0] != 0 || $time[1] != 0 || $time[3] != 0) $run = false;
					break;

					case 'M' : //For every month. Only when minute and hour are both zero and the day is 1(Not allowing skipping months)
						if($value != 1 || $time[0] != 0 || $time[1] != 0 || $time[4] != 1) $run = false;
					break;

					default : $run = false; break;
				}

				if(!$run) continue;

				$env = $system->app_env($schedule); //Get application information
				$log->system(LOG_INFO, "Running periodic task for : {$env['id']}");

				if(!chdir("{$env['root']}../{$info['version']}")) //Change the working directory to the specified version's root
				{
					$problem = "Cannot change directory to application version '{$info['version']}' root for : {$env['id']}";
					$log->system(LOG_ERR, $problem, 'Make sure a proper version is specified');

					continue; //Go to next entry
				}

				$system->file_load('server/php/periodic.php'); //Load the periodic script
				$executor = ucwords($env['id']).'_Periodic'; //Name of the class to run

				try { new $executor($info['id']); } //Execute the scheduled task and pass the task ID

				catch(Exception $error)
				{
					$problem = "Error running periodic task on '$executor' for task '{$info['id']}' : ".$error->getMessage();
					$log->system(LOG_ERR, $problem, "Check the error and the class '$executor'");
				}

				$log->system(LOG_INFO, "Done running periodic task on '$executor' for task '{$info['id']}'");
			}
		}
	}
