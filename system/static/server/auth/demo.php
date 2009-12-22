<?php
	class System_Static_Auth
	{
		public function verify($name, $pass) #Check against pre defined demo user credential
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!is_string($name) || !is_string($pass)) return $log->param();

			$conf = $system->file_conf('system/static/conf/auth/demo.xml');
			return $conf['user'] == strtolower($name) && $conf['pass'] == $pass;
		}
	}
?>
