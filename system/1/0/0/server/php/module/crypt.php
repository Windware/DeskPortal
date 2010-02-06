<?php
	class System_1_0_0_Crypt
	{
		protected static $_cipher = MCRYPT_BLOWFISH; #Encryption method

		protected static function _init($key) #Initialize the crypt module
		{
			$resource = mcrypt_module_open(self::$_cipher, '', 'cbc', '');

			$key = substr(md5($key), 0, mcrypt_enc_get_key_size($resource));
			$vector = substr(md5($key), 0, mcrypt_enc_get_iv_size($resource));
	
			mcrypt_generic_init($resource, $key, $vector);
			return $resource;
		}

		public static function decrypt(&$system, $data, $key) #Decrypt given data with the key
		{
			if(!$system->is_text($data) || !$system->is_text($key)) return false;

			$resource = self::_init($key);
			$data = mdecrypt_generic($resource, base64_decode($data));

			mcrypt_generic_deinit($resource);
			mcrypt_module_close($resource);

			return rtrim($data, "\0");
		}

		public static function encrypt(&$system, $data, $key) #Encrypt given data with the key
		{
			if(!$system->is_text($data) || !$system->is_text($key)) return false;

			$resource = self::_init($key);
  			$data = mcrypt_generic($resource, $data);

			mcrypt_generic_deinit($resource);
			mcrypt_module_close($resource);

			return base64_encode($data);
		}
	}
?>
