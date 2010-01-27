
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		var _count = {}; //Number of unread mails for each folders

		var _loaded = {}; //Flag to indicate if a folder has been updated

		var _lock = {}; //Lock to wait for mails to finish loading

		var _previous = {}; //Previously selected folder

		var _space = 10; //Amount of space to put on left for folders below another

		var _structure = {}; //Temporary structure parameter for folder assembling

		this.change = function(folder) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(folder)) return log.param();

			var account = __belong[folder];

			if(_lock[account]) return false; //Wait till previous loading finishes
			_lock[account] = true;

			//Change the look of the chosen folder
			if(_previous[account]) $system.node.classes($id + '_folder_' + _previous[account], $id + '_displayed', false);
			$system.node.classes($id + '_folder_' + folder, $id + '_displayed', true);

			var unlock = function(folder)
			{
				var account = __belong[folder];
				_lock[account] = false;

				var local = __account[account].type == 'pop3' && __special.inbox[account] != folder; //Do not update other than INBOX for POP3
				if(_loaded[folder] || local) return true; //If never updated, update from the server

				_loaded[folder] = _lock[account] = true;
				return $self.item.get(folder, 1, $system.app.method(unlock, [folder])); //Update it
			}

			if(!_loaded[folder]) delete __update[folder]; //If never loaded, drop the update flag to avoid duplicate updating
			if(!$self.item.get(folder, false, $system.app.method(unlock, [folder]))) return false; //Get the folder items for current account

			_previous[account] = folder;
			return true;
		}

		this.clear = function(folder) //Clear caches for a folder
		{
			delete __cache[folder];
			delete __belong[folder];

			for(var id in __mail) if(__mail[id].folder == folder) delete __mail[id];

			if(__refresh[folder]) clearTimeout(__refresh[folder]);
			delete __refresh[folder];

			for(var section in __special) delete __special[section][folder];
			delete __update[folder];
		}

		this.get = function(account, update, callback, request) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			if(!__account[account]) //If periodical update arrives with the account vanished
			{
				clearInterval(__timer[account]); //Remove the timer
				delete __timer[account];

				return false;
			}

			var language = $system.language.strings($id);

			if(account == '0') //On empty selection, clean up the area
			{
				$system.node.id($id + '_folder').innerHTML = '';
				$system.node.hide($id + '_mail_empty', true, true); //Remove the empty notification

				var table = $system.node.id($id + '_read_zone');
				while(table.firstChild) table.removeChild(table.firstChild);

				$system.app.callback(_class + '.get', callback);
				return true;
			}

			var list = function(account, callback, request)
			{
				$self.gui.indicator(false); //Hide indicator

				_cache[account] = request.xml || request;
				if(__selected.account != account) return true; //If displaying account got changed, do not display the result

				var area = $system.node.id($id + '_folder');
				area.innerHTML = '';

				var header = document.createElement('div');
				header.className = $id + '_folder_header';

				header.innerHTML = '<strong>' + language.folder + '</strong>';
				area.appendChild(header);

				var construct = function(tree, depth) //Create the directory structure tree
				{
					if(!$system.is.object(tree) || !$system.is.digit(depth)) return false;

					var nodes = tree.childNodes;
					if(!nodes) return false;

					for(var i = 0; i < nodes.length; i++)
					{
						if(nodes[i].nodeType != 1 || nodes[i].nodeName != 'folder') continue; //Get the folder's node

						var id = $system.dom.attribute(nodes[i], 'id');
						var name = $system.dom.attribute(nodes[i], 'name');

						var recent = $system.dom.attribute(nodes[i], 'recent'); //Number of unread mails
						var special = false; //If this folder is special or not

						for(var j = 0; j < title.length; j++)
						{
							if(__special[title[j]][account] != id) continue;

							name = language[title[j]];
							special = {name : title[j], id : j}; //Remember the name and the position of the special folder

							break;
						}

						if(_count[id] && recent > _count[id])
						{
							__update[id] = true; //Make it update on next access

							var message = language.recent.replace('%folder%', name).replace('%account%', __account[account].description);
							$system.gui.notice($id, message, null); //Notify the new message presence
						}

						_count[id] = recent;
						__belong[id] = account; //Remember the belonging account

						var link = document.createElement('a'); //Create link for the folder
						link.id = $id + '_folder_' + id;

						link.onclick = $system.app.method($self.folder.change, [id]);
						$system.node.hover(link, $id + '_hilight');

						if(special === false) //Create folder icon for regular folders
						{
							var icon = document.createElement('img');
							$system.image.set(icon, $self.info.devroot + 'graphic/folder.png');

							icon.className = $id + '_indicator';
							icon.style.marginLeft = depth * _space + 'px';

							link.appendChild(icon);
						}

						var display = document.createElement('strong');

						display.onmousedown = $system.app.method($system.event.cancel, [display]);
						display.appendChild(document.createTextNode(' ' + name));

						if($system.is.digit(_count[id]) && _count[id] > 0) //If any new messages exist
						{
							display.appendChild(document.createTextNode(' (' + _count[id] + ')'));
							link.appendChild(display); //Put the folder name
						}

						link.appendChild(display); //Put the folder name
						var icon = null;

						if(special !== false) //When it's a special folder
						{
							var spacer = document.createTextNode(' ');
							link.insertBefore(spacer, link.firstChild);

							icon = document.createElement('img'); //Create an icon
							icon.className = $id + '_indicator';

							$system.image.set(icon, $self.info.devroot + 'graphic/' + special.name + '.png');
							link.insertBefore(icon, link.firstChild); //Prepend an icon

							group[special.id] = [link];
							index = special.id;

							_structure.depth = depth; //Remember the current depth
							if(depth == 0) _structure.index = index;
						}
						else //If not special
						{
							if(depth == 0)
							{
								group.push([link]);
								_structure.index = index = group.length - 1; //Remember the folder index
							}
							else
							{
								if(depth <= _structure.depth) index = _structure.index; //Revert the folder index when out of special folder tree
								group[index].push(link); //For child folders, place them below each base folders
							}
						}

						if(!construct(nodes[i], depth + 1)) return false; //Look through child folders
					}

					return true;
				}

				var index; //Folder counter

				var title = []; //Special folder name list
				for(var folder in __special) title.push(folder);

				var group = [];
				group[title.length] = null; //Reserve the space in the array for special folders (So the next 'push' will be appended behind)

				var section = $system.browser.engine == 'trident' ? 1 : 0; //IE counts first 'xml' tag as first node

				if(!request.xml || !request.xml.childNodes || !construct(request.xml.childNodes[section], 0)) //Create folder listing
					return log.user($global.log.warning, 'user/folder/list', 'user/folder/list/solution');

				for(var i = 0; i < group.length; i++) //Create the folder listing starting from the special folders
					if($system.is.array(group[i])) for(var j = 0; j < group[i].length; j++) area.appendChild(group[i][j]);

				if(_previous[account]) $system.node.classes($id + '_folder_' + _previous[account], $id + '_displayed', true);
				$system.app.callback(_class + '.get.list', callback);

				return true;
			}

			if(!$system.is.digit(update))
			{
				if($system.is.object(request)) return list(account, callback, request); //If cached object is given, call it directly
				if(_cache[account]) return list(account, callback, _cache[account]); //If cached object is given, call it directly
			}

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account, update : update, subscribed : 1}, null, $system.app.method(list, [account, callback]));
		}
	}
