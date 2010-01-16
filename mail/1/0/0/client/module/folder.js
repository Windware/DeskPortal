
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		var _previous; //Previously selected folder

		var _space = 10; //Amount of space to put on left for folders below another

		this.change = function(folder) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');

			if(!$system.is.digit(folder)) return log.param();
			if(!$self.item.get(folder)) return false; //Get the folder items for current account

			//Change the look of the chosen folder
			if(_previous) $system.node.classes($id + '_folder_' + _previous, $id + '_displayed', false);
			$system.node.classes($id + '_folder_' + folder, $id + '_displayed', true);

			_previous = folder;
		}

		this.get = function(account, update, callback, request) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			var language = $system.language.strings($id);

			if(account == '0') //On empty selection, clean up the area
			{
				$system.node.id($id + '_folder').innerHTML = '';

				var table = $system.node.id($id + '_read_zone');
				while(table.firstChild) table.removeChild(table.firstChild);

				$system.app.callback(_class + '.get', callback);
				return true;
			}

			var list = function(account, callback, request)
			{
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

						var link = document.createElement('a'); //Create link for the folder
						link.id = $id + '_folder_' + id;

						link.onclick = $system.app.method($self.folder.change, [id]);
						$system.node.hover(link, $id + '_hilight');

						var special = false; //If this folder is special or not

						if(depth == 0) //On base folders, give localized folder names for special folders
						{
							for(var j = 0; j < title.length; j++)
							{
								if(__special[title[j]][account] != id) continue;

								name = language[title[j]];
								special = {name : title[j], id : j}; //Remember the name and the position of the special folder

								break;
							}
						}

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
						link.appendChild(display); //Put the folder name

						var icon = null;

						if(depth == 0) //On base folders
						{
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
							}
							else //If not special
							{
								group.push([link]);
								index = group.length - 1;
							}
						}
						else group[index].push(link); //For child folders, place them below each base folders

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

				if(_previous) $system.node.classes($id + '_folder_' + _previous, $id + '_displayed', true);
				$system.app.callback(_class + '.get.list', callback);

				return true;
			}

			if(!update)
			{
				if($system.is.object(request)) return list(account, callback, request); //If cached object is given, call it directly
				if(_cache[account]) return list(account, callback, _cache[account]); //If cached object is given, call it directly
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account, update : update ? 1 : 0, subscribed : 1}, null, $system.app.method(list, [account, callback]));
		}
	}
