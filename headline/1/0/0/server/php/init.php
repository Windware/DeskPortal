<?php
	class Headline_1_0_0
	{
		protected static $_namespace = array('dc' => 'http://purl.org/dc/elements/1.1/'); #XML namespace

		protected static function _flatten($string) #Remove tags, redundant whitespaces and convert HTML entities back into regular characters
		{
			return html_entity_decode(trim(str_replace("\n", ' ', preg_replace('/<.+?>/', ' ', $string))));
		}

		public static function update($feed = null) #Update the feed caches
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($feed !== null) #If a feed was specified for a single update
			{
				if(!$system->is_address($feed)) return $log->param();

				$limiter = ' address = :address AND'; #Append a limitation to the query
				$parameter[':address'] = $feed;
			}

			$conf = $system->app_conf();

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$parameter[':obsolete'] = $system->date_datetime(time() - $conf['limit'] * 60); #The time the feed should be renewed
			#FIXME - Count usage of the feed for each users and delete it off when not used

			#Get list of registered feeds that is not updated within the last configured minutes
			$query = $database->prepare("SELECT id, address, updated FROM {$database->prefix}feed WHERE$limiter updated IS NULL OR updated < :obsolete");
			$query->run($parameter);

			if(!$query->success) return false;

			$id = array(); #List of database row ID
			$request = array(); #Request to send

			foreach($query->all() as $row) #Construct the request entities
			{
				$id[$row['address']] = (int) $row['id']; #Remember the ID

				$header = strtotime($row['updated']) ? array('If-Modified-Since' => gmdate('r', $row['updated'])) : array();
				$request[] = array('address' => $row['address'], 'header' => $header);

				$log->dev(LOG_NOTICE, "Making a remote call to {$row['address']} to get a news RSS");
			}

			#TODO - Add favicon.ico along with the feed request
			$results = $system->network_http($request); #Concurrently fetch the remote resources
			if($results === false) return false;

			$success = true; #Indicates if the whole operations were successful

			foreach($results as $content)
			{
				$failure = false; #Indicates if the feed is valid or not
				$href = false; #Whether the link to the article exists in the 'href' attribute or not

				if($content['status'] == '200') #Check for network status but ignore its Content-Type as it can vary
				{
					try
					{
						#Placing '@' to avoid errors displayed when it is captured (libxml_use_internal_errors() works?)
						$xml = @new SimpleXMLElement($content['body'], LIBXML_COMPACT); #Parse the feed received

						switch(strtolower($xml->getName())) #Depending on what type of feed it is
						{
							case 'rdf' : #RSS0.91, RSS1.0
								$entries = $xml->item;
								$site = $xml->channel;

								$date = 'dc:date';
								$link = $site->link;

								$summary = 'description';
							break;

							case 'rss' : #RSS2.0
								$entries = $xml->channel->item;
								$site = $xml->channel;

								$date = 'pubDate';
								$link = $site->link;

								$summary = 'description';
							break;

							case 'feed' : #ATOM
								$entries = $xml->entry;
								$site = $xml;

								$date = 'updated';
								foreach($site->link as $source) if($source['rel'] != 'self') $link = $source['href'];

								$summary = 'content';
								$href = true; #Link to the article is an attribute of a 'link' node
							break;

							default :
								$solution = 'Make sure the feed is a valid RSS or ATOM';
								$log->dev(LOG_ERR, "Cannot find entries in the RSS for '{$content['address']}'", $solution);

								$failure = true;
							break;
						}
					}

					catch(Exception $error) #Catch the parse error
					{
						$solution = 'Make sure the feed address is proper and it is a valid RSS feed';
						$log->dev(LOG_WARNING, "Cannot parse RSS for '{$content['address']}' : ".$error->getMessage(), $solution);

						$failure = true;
					}
				}
				else $failure = true;

				$index = $id[$content['address']]; #Feed ID
				if($failure) $success = false;

				$log->dev(LOG_INFO, "Setting updated time for feed '{$content['address']}'");
				$query = $database->prepare("UPDATE {$database->prefix}feed SET updated = :updated WHERE id = :id");

				#Update the feed information and give some minutes difference, so feeds are retrieved sporadically
				$query->run(array(':id' => $index, ':updated' => $system->date_datetime(time() + mt_rand(0, 600))));
				if(!$query->success || $failure) continue; #If the site entry cannot be updated, do not update the entries

				$log->dev(LOG_INFO, "Updating site information for feed '{$content['address']}'");
				$query = $database->prepare("UPDATE {$database->prefix}feed SET site = :site, description = :description, icon = :icon WHERE id = :id");

				#Update the feed information
				$query->run(array(':id' => $index, ':site' => self::_flatten($link), ':description' => self::_flatten($site->title), ':icon' => $icon));

				if(!$query->success) continue; #If the site entry cannot be updated, do not update the entries

				if(!is_object($entries))
				{
					$log->dev(LOG_WARNING, "The feed '{$content['address']}' includes no entries");
					continue;
				}

				$query = array(); #Reinitialize the variable

				#Keep the query prepared for consecutive selects
				$query['entry'] = $database->prepare("SELECT COUNT(id) FROM {$database->prefix}entry WHERE feed = :feed AND link = :link");

				#Keep the query prepared for possible consecutive inserts
				$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}entry (feed, subject, link, date, description) VALUES (:feed, :subject, :link, :date, :description)");

				#TODO - Sort the '$entries' in the order of '$date' in descending order instead of simple 'array_reverse'
				foreach($entries as $headline) #For all of the headline entries in the feed retrieved
				{
					$page = $href ? $headline->link['href'] : $headline->link; #Get the page link

					$query['entry']->run(array(':feed' => $index, ':link' => $page));
					if(!$query['entry']->success || $query['entry']->column() > 0) continue; #If the entry is already registered, ignore it

					if(preg_match('/^(dc):(.+)/', $date, $matches)) #Get the namespace values
					{
						$space = $headline->children(self::$_namespace[$matches[1]]);
						$published = $space->{$matches[2]};
					}
					else $published = $headline->{$date};

					#Turn it into DATETIME format
					if(!$published) $published = date($system->global['define']['datetime']);
					else $published = gmdate($system->global['define']['datetime'], strtotime($published));

					$query['insert']->run(array(':feed' => $index, ':subject' => self::_flatten($headline->title), ':link' => self::_flatten($page), ':date' => $published, ':description' => self::_flatten($headline->{$summary})));
				}
			}

			return $success;
		}
	}
?>
