<?php
	class Headline_1_0_0_Feed
	{
		private $_system;

		public $address, $fresh, $id;

		public function __construct($address) #Initialize the feed object
		{
			$system = $this->_system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_address($address)) return $log->param();

			$this->address = $address; #Remember the address given

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			#Get the ID of the feed
			$query = $database->prepare("SELECT id FROM {$database->prefix}feed WHERE address = :address");
			$query->run(array(':address' => $address));

			if(!$query->success) return false;

			$this->id = $query->column(); #Remember the id if it exists in the database
			if($this->id) return;

			$this->add(); #Register it if it's new
			$this->fresh = true;
		}

		public function add() #Add the feed to be retrieved periodically on the server side
		{
			$system = $this->_system;

			$log = $system->log(__METHOD__);
			$log->system(LOG_INFO, "Adding a new feed : $this->address");

			if($this->id) return $log->system(LOG_NOTICE, "Feed already added : $this->address", 'Cannot add the same feed', true);

			$database = $system->database('system', __METHOD__); #Insert the new feed into the database
			if(!$database->success) return false;

			$query = $database->prepare("INSERT INTO {$database->prefix}feed (address) VALUES (:address)");
			$query->run(array(':address' => $this->address));

			if(!$query->success) return false;

			$this->id = $database->id(); #Get the feed's id
			if($this->update()) return true; #Get the feed updated

			$this->remove(); #Remove it if failed to update the content (Likely bad address)
			return false;
		}

		public function entry($limiter = '', $values = array()) #List headline entries in the feed
		{
			$system = $this->_system;
			$log = $system->log(__METHOD__);

			if(!$this->id) return $log->system(LOG_WARNING, "Feed is not registered : $this->address", 'Entries can only be read on registered feeds');
			if(!is_string($limiter) || !is_array($values)) return $log->param();

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return array();

			$parameters = array(':id' => $this->id); #Set the query parameter

			if(strlen($limiter) > 0) #Add a custom query (NOTE : Make sure the '$limiter' is sane at the caller side)
			{
				$limiter = " AND $limiter";
				$parameters = array_merge($parameters, $values);
			}

			#Get the list of entries for this feed
			$query = $database->prepare("SELECT * FROM {$database->prefix}entry WHERE feed = :id$limiter ORDER BY date DESC");
			$query->run($parameters);

			return $query->success ? $query->all() : array();
		}

		public static function get(System_1_0_0_User $user = null) #List the subscribed feeds of an user
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}subscribed WHERE user = :user");
			$query->run(array(':user' => $user->id)); #Get the list of subscribed feeds

			if(!$query->success) return false;
			$subscription = $query->all();

			if(!count($subscription)) return $xml; #If not subscribed to any, quit here

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$grep = $parameter = $period = array(); #Query param

			foreach($subscription as $index => $row)
			{
				$grep[] = ":id{$index}_index";
				$parameter[":id{$index}_index"] = $row['feed'];

				$period[$row['feed']] = $row['period']; #Get the period setting for a feed
			}

			$query = $database->prepare("SELECT * FROM {$database->prefix}feed WHERE id IN (".implode(',', $grep).') ORDER BY description');
			$query->run($parameter); #Get the list of subscribed feeds' information

			if(!$query->success) return false;
			$feeds = $query->all(); #List of feeds

			#Get the newest date of a feed's entry
			$query = $database->prepare("SELECT date FROM {$database->prefix}entry WHERE feed = :feed ORDER BY date DESC LIMIT 1");
			$section = explode(' ', 'id address site description');

			$list = array();

			foreach($feeds as $row) #Create feed XML
			{
				$param = array();
				foreach($section as $piece) $param[$piece] = $row[$piece];

				$query->run(array(':feed' => $row['id']));
				if(!$query->success) return false;

				$param['newest'] = $query->column();
				$param['period'] = $period[$row['id']];

				$list[] = $param;
			}

			return $list;
		}

		public function remove() #Delete the feed and its associated entries
		{
			$system = $this->_system;

			$log = $system->log(__METHOD__);
			$log->system(LOG_INFO, "Removing a feed : $this->address");

			if(!$this->id) return $log->system(LOG_NOTICE, "Feed not registered : $this->address", '', true);

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			if(!$database->begin()) return false; #Keep the operation atomic

			#Delete the entries of the feed
			$query = $database->prepare("DELETE FROM {$database->prefix}entry WHERE feed = :id");
			$query->run(array(':id' => $this->id));

			if(!$query->success) return $database->rollback() && false; #Rollback the deletion

			$query = $database->prepare("DELETE FROM {$database->prefix}feed WHERE id = :id");
			$query->run(array(':id' => $this->id)); #Delete the feed

			if(!$query->success || !$database->commit()) return $database->rollback() && false;

			$this->id = null; #Invalidate the object
			return true;
		}

		public static function sample($language = null, System_1_0_0_User $user = null) #Give sample feeds to an user
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			try #Load the sample XML
			{
				$sample = @new SimpleXMLElement($system->file_read($system->language_file($system->self['id'], 'sample.xml', $language)), LIBXML_COMPACT);
			}

			catch(Exception $error)
			{
				return $log->dev(LOG_ERR, "Sample feed list cannot be loaded : $error", 'Check sample.xml file in document folders');
			}

			foreach($sample->feed as $entry) #Each of the sample feeds
			{
				$feed = new self((string) $entry['address']); #Initialize the feed
				if($feed->id) $feed->subscribe($feed->id, $user);
			}

			return $query->success;
		}

		public static function subscribe($id, System_1_0_0_User $user = null) #Subscribe a user to a feed
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database['user'] = $system->database('user', __METHOD__, $user);
			if(!$database['user']->success) return false;

			#Set subscription
			$query = $database['user']->prepare("REPLACE INTO {$database['user']->prefix}subscribed (user, feed) VALUES (:user, :feed)");
			$query->run(array(':user' => $user->id, ':feed' => $id));

			if(!$query->success) return false;

			$database['system'] = $system->database('system', __METHOD__);
			if(!$database['system']->success) return false;

			#Record the subscription to the system database (So, when it's empty, the feed can be deleted from the periodic job)
			$query = $database['system']->prepare("REPLACE INTO {$database['system']->prefix}used (feed, user) VALUES (:feed, :user)");
			$query->run(array(':feed' => $id, ':user' => $user->id));

			if($query->success) return true;

			#On failure, delete it off the user database too to avoid inconsistency
			$query = $database['user']->prepare("DELETE FROM {$database['user']->prefix}subscribed WHERE user = :user AND feed = :feed");
			$query->run(array(':user' => $user->id, ':feed' => $id));

			if(!$query->success)
			{
				$problem = "Cannot delete the feed subscription for user ID $user->id on feed ID $id, after failing to set its subscription record to the system database.";
				$problem .= "The feed subscription count on the system database is now inconsistent by 1 less count against amount of subscription on user databases.";

				$log->system(LOG_ERR, $problem, 'Make sure the system database is capable of storing the record and user database is accessible');
			}

			return false;
		}

		public static function unsubscribe($id, System_1_0_0_User $user = null) #Unsubscribe a user from a feed
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			#Remove the feed entry from user database
			$query = $database->prepare("DELETE FROM {$database->prefix}subscribed WHERE user = :user AND feed = :feed");
			$query->run(array(':user' => $user->id, ':feed' => $id));

			if(!$query->success) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			#Record the removal of subscription to the system database
			$query = $database->prepare("DELETE FROM {$database->prefix}used WHERE feed = :feed AND user = :user");
			$query->run(array(':feed' => $id, ':user' => $user->id));

			if(!$query->success)
			{
				$problem = "Cannot delete the subscription count from system database on feed ID $id for user ID $user->id";
				$problem .= "The feed subscription count on the system database is now inconsistent by 1 additional count against amount of subscription on user databases.";

				return $log->system(LOG_ERR, $problem, 'Make sure the system database is accessible');
			}

			#Find if any other user still has this feed subscribed
			$query = $database->prepare("SELECT COUNT(feed) FROM {$database->prefix}used WHERE feed = :feed");
			$query->run(array(':feed' => $id));

			if(!$query->success) return false;
			if($query->column() != 0) return true;

			#Remove the feed to avoid fetching the content remotely anymore
			$query = $database->prepare("DELETE FROM {$database->prefix}feed WHERE id = :id");
			$query->run(array(':id' => $id));

			return $query->success; #Still report error, if this operation fails
		}

		public function update() { return $this->id ? Headline_1_0_0::update($this->address) : false; } #Update the feed content

		public function xml($limiter = '', $values = array()) #Create XML list of feed entries
		{
			$system = $this->_system;
			$log = $system->log(__METHOD__);

			$stories = '';

			#Template to send out the headline to the client
			$template = $system->file_read("{$system->self['root']}resource/news.xml", LOG_ERR);
			if(!$template) return $stories;

			if(!is_array($entries = $this->entry($limiter, $values))) return $stories;

			#Construct the headline XML : TODO - Escape the values to make sure that they don't cancel CDATA section
			foreach($entries as $headline) $stories .= $system->xml_fill($template, $headline);

			return $stories; #Return the concatenated headline XML
		}
	}
?>
