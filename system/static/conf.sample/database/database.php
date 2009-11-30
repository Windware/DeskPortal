<?php
	#Configuration for PHP PDO function format (http://php.net/manual/en/pdo.drivers.php)

	#Variables passed (Read from settings.xml)
	# $sector - 'system' for system database, 'user' for user's database
	# $type - Database type (mysql, pgsql, sqlite, etc)
	# $host - Database server hostname
	# $port - Database server port
	# $database - Name of the database to use

	# User name and password will be passed to PDO function without mentioning them here

	class System_Static_Database
	{
		public static function charset($type) #Returns part of the table creation query to determine the character set
		{
			switch($type)
			{
				case 'sqlite' : return ''; break; #SQLite3 does not support table level character encoding

				default : return 'DEFAULT CHARACTER SET utf8'; break; #Set client connection character set
			}
		}

		public static function connection($sector, $type, $host, $port, $database) #The PDO connection method string
		{
			switch($sector)
			{
				case 'system' : #For system database
					switch($type)
					{
						case 'sqlite' : return "$type:$database"; break;

						default : return "$type:host=$host;port=$port;dbname=$database"; break;
					}
				break;

				case 'user' : #For user database
					switch($type)
					{
						case 'sqlite' : return "$type:$database"; break;

						default : return "$type:host=$host;port=$port;dbname=$database"; break;
					}
				break;

				default : return false; break;
			}
		}

		public static function init($type) #Database initialization on table creation
		{
			switch($type)
			{
				case 'sqlite' : return 'PRAGMA auto_vacuum = FULL'; break; #Set auto vacuum feature on

				default : return ''; break;
			}
		}
	}
?>
