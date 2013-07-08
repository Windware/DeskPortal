<?php
	class System_Static_Auth
	{
		public function verify($identity, $password) //Check against pre defined demo user credential
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_identity($identity) || !is_string($password)) return $log->param();

			$conf = $system->file_conf('system/static/conf/auth/demo.xml');
			return $conf['identity'] === strtolower($identity) && $conf['password'] === $password;
		}
	}
