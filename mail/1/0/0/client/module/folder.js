
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		var _scroll = {}; //Scroll amount for each folders

		var _space = 10; //Amount of space to put on left for folders below another

		var _structure = {}; //Temporary structure parameter for folder assembling

		this.change = function(folder, callback) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(folder)) return log.param();

			var account = __belong[folder];

			__page[folder] = __page[folder] ? $system.node.id($id + '_show').value : 1; //Pick the selected page or start from page 1
			var table = $system.node.id($id + '_read_zone'); //Mail listing table

			if(__selected.folder) //If a previously chosen folder exists
			{
				$system.node.classes($id + '_folder_' + __selected.folder, $id + '_displayed', false); //Revert the look of the previous folder

				if(!_scroll[__selected.folder]) _scroll[__selected.folder] = {};
				_scroll[__selected.folder][__page[__selected.folder]] = {order : __selected.order, reverse : __selected.reverse, position : table.parentNode.scrollTop}; //Remember the scroll height
			}

			$system.node.classes($id + '_folder_' + folder, $id + '_displayed', true); //Emphasize the current selection
			var form = $system.node.id($id + '_form');

			var run = function(folder, callback)
			{
				var scroll = _scroll[folder] && _scroll[folder][__page[folder]]; //Check if the scroll position cache is still valid

				if(scroll) //If the scroll position is declared
				{
					scroll = scroll.order == __order.item && scroll.reverse == __order.reverse && scroll.position; //Check if order has changed

					if($system.is.digit(scroll)) table.parentNode.scrollTop = scroll; //Recover the scroll height
					else delete _scroll[folder]; //If any of the display order changes, discard the positions
				}

				if(!scroll) table.parentNode.scrollTop = 0; //Move to top of page
				return $system.app.callback(_class + '.change.run', callback);
			}

			__selected = {account : __selected.account, folder : folder, marked : form.marked.checked, unread : form.unread.checked, order : __order.item, reverse : __order.reverse, search : form.search.value}; //Remember current selection
			return $self.item.get(folder, __page[folder], false, $system.app.method(run, [folder, callback])); //Get the folder items for current account
		}

		this.clear = function(folder) //Clear caches for a folder
		{
			delete __cache[folder]; //Delete message list cache

			for(var id in __mail) if(__mail[id].folder == folder) delete __mail[id]; //Remove all mail data in this folder
			delete __update[folder]; //Remove the flags to indicate that a folder should be updated on next access
		}

		this.empty = function(account) //Empty trash folder
		{
			var log = $system.log.init(_class + '.empty');
			if(!$system.is.digit(account)) return log.param();

			var event = $system.event.source(arguments);
			event.cancelBubble = true;

			var language = $system.language.strings($id);
			if(!confirm(language.purge)) return false;

			var clean = function(account, trash, request)
			{
				$self.gui.indicator(false); //Hide indicator

				if($system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/folder/empty/title', 'user/folder/empty/message');
				$self.folder.clear(trash); //Clear mail data for the folder

				if(__selected.account != account) return;

				$self.folder.get(account, __account[account].type == 'imap' ? 1 : 2);
				if(__selected.folder == trash) $self.item.get(trash, __page[trash]); //Update the listing to be empty
			}

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.empty'}, {account : account}, $system.app.method(clean, [account, __special.trash[account]]));
		}

		this.get = function(account, update, callback, request) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			if(account == '0') //On empty selection, clean up the area
			{
				$system.node.id($id + '_folder').innerHTML = '';
				$system.node.hide($id + '_mail_empty', true, true); //Remove the empty notification

				$system.node.id($id + '_show').innerHTML = '<option value="1">1</option>'; //Clear up the page list

				var table = $system.node.id($id + '_read_zone');
				while(table.firstChild) table.removeChild(table.firstChild);

				$system.app.callback(log.origin, callback);

				delete __selected.account;
				delete __selected.folder;

				return true;
			}

			if(!__account[account]) //If periodical update arrives with the account vanished
			{
				clearInterval(__timer[account]); //Remove the timer
				delete __timer[account];

				return false;
			}

			var language = $system.language.strings($id);

			var list = function(account, update, callback, request)
			{
				$self.gui.indicator(false); //Hide indicator
				_cache[account] = $system.is.object(request.xml) && request.xml || request;

				var area = $system.node.id($id + '_folder');

				if($system.dom.status(_cache[account]) != '0') //On failure
				{
					area.innerHTML = ''; //Empty the listing
					delete _cache[account]; //Clear the cache to try again later

					return $system.gui.alert($id, 'user/folder/list', 'user/folder/list/message', undefined, null, ['<strong>' + __account[account].description + '</strong>']);
				}

				if(!__account[account]) return;

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
						var count = $system.dom.attribute(nodes[i], 'count'); //Number of loaded mails

						var special = false; //If this folder is special or not

						for(var j = 0; j < title.length; j++)
						{
							if(__special[title[j]][account] != id) continue;

							name = language[title[j]];
							special = {name : title[j], id : j}; //Remember the name and the position of the special folder

							break;
						}

						if(update == 1 && Number(count) > 0) //If new mail exists after an update
						{
							var notify = function(id, account, name)
							{
								if(!__account[account]) return;
								var message = language.recent.replace('%folder%', name).replace('%account%', __account[account].description);

								var open = function(account, folder) //Show the folder with new mails
								{
									if(!$system.window.list[$id].displayed.body) $system.tool.hide($id, 'body'); //Uncover the body
									$self.account.change(account, folder); //Change account and folder
								}

								$system.gui.notice($id, message, $system.app.method(open, [account, id])); //Notify the new message presence
								log.user($global.log.notice, 'user/folder/get', '', [__account[account].description, name]);
							}

							var run = null;

							if(id != __special.drafts[account] && id != __special.sent[account] && id != __special.trash[account])
								run = $system.app.method(notify, [id, account, name]); //If not under drafts, sent and trash folder, notify of new mails

							$self.item.get(id, __page[id], 1, run); //Update the listing
						}

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

						$system.event.add(display, 'onmousedown', $system.app.method($system.event.cancel, [display]));
						display.appendChild(document.createTextNode(' ' + name));

						if($system.is.digit(recent) && recent > 0) //If any new messages exist
						{
							display.appendChild(document.createTextNode(' (' + recent + ')'));
							link.appendChild(display); //Put the folder name
						}

						link.appendChild(display); //Put the folder name

						if(special !== false) //When it's a special folder
						{
							var spacer = document.createTextNode(' ');
							link.insertBefore(spacer, link.firstChild);

							var icon = document.createElement('img'); //Create an icon
							icon.className = $id + '_indicator';

							$system.image.set(icon, $self.info.devroot + 'graphic/' + special.name + '.png');
							link.insertBefore(icon, link.firstChild); //Prepend an icon

							group[special.id] = [link];
							index = special.id;

							_structure.depth = depth; //Remember the current depth
							if(depth == 0) _structure.index = index;

							if(special.name == 'trash')
							{
								var spacer = document.createTextNode(' ');
								link.appendChild(spacer);

								var icon = document.createElement('img'); //Create an icon

								icon.className = $id + '_indicator';
								icon.style.cursor = 'pointer';

								$system.tip.set(icon, $id, 'empty');
								icon.onclick = $system.app.method($self.folder.empty, [account]); //To empty trash

								$system.image.set(icon, $self.info.devroot + 'graphic/empty.png');
								link.appendChild(icon); //Put empty trash icon
							}
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

				if(!_cache[account].childNodes || !construct(_cache[account].childNodes[section], 0)) //Create folder listing
				{
					area.innerHTML = ''; //Empty the listing
					delete _cache[account]; //Clear the cache to try again later

					return log.user($global.log.warning, 'user/folder/list', 'user/folder/list/message');
				}

				if(__selected.account != account) return true; //If displaying account got changed, do not display the result
				area.innerHTML = '';

				var header = document.createElement('div');
				header.className = $id + '_folder_header';

				header.innerHTML = '<strong>' + language.folder + '</strong>';
				area.appendChild(header);

				for(var i = 0; i < group.length; i++) //Create the folder listing starting from the special folders
					if($system.is.array(group[i])) for(var j = 0; j < group[i].length; j++) area.appendChild(group[i][j]);

				if(__selected.folder) $system.node.classes($id + '_folder_' + __selected.folder, $id + '_displayed', true);
				$system.app.callback(_class + '.get.list', callback);

				return true;
			}

			if(!$system.is.digit(update))
			{
				if($system.is.object(request)) return list(account, update, callback, request); //If cached object is given, call it directly
				if(_cache[account]) return list(account, update, callback, _cache[account]); //If cached object is given, call it directly

				update = 0;
			}

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account, update : update, subscribed : 1}, null, $system.app.method(list, [account, update, callback]));
		}
	}
