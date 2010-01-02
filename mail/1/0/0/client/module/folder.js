
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		var _previous; //Previously selected folder

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

		this.get = function(account, callback, request) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			var list = function(account, callback, request)
			{
				_cache[account] = request;
				var language = $system.language.strings($id);

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

						if(depth == 0 && name == 'INBOX') __inbox[account] = id; //Remember the folder ID for default mail box

						var link = document.createElement('a'); //Create link for the folder
						link.id = $id + '_folder_' + id;

						link.onclick = $system.app.method($self.folder.change, [id]);
						$system.node.hover(link, $id + '_hilight');

						var trunk = ''; //Create folder listing indicator
						for(var j = 0; j < depth * 2; j++) trunk += '&nbsp;';

						if(depth) link.innerHTML = trunk + '|- ';
						var display = document.createElement('strong');

						display.appendChild(document.createTextNode(name));
						link.appendChild(display);

						area.appendChild(link);
						construct(nodes[i], depth + 1); //Look through child folders
					}

					return true;
				}

				var section = $system.browser.engine == 'trident' ? 1 : 0; //IE counts first 'xml' tag as first node

				if(!construct(request.xml.childNodes[section], 0)) //Create folder listing
					return log.user($global.log.warning, 'user/folder/list', 'user/folder/list/solution');

				if(_previous) $system.node.classes($id + '_folder_' + _previous, $id + '_displayed', true);
				$system.app.callback(_class + '.get.list', callback);

				return true;
			}

			__selected.account = account; //Keep the selected account

			if($system.is.object(request)) return list(account, callback, request); //If cached object is given, call it directly
			if(_cache[account]) return list(account, callback, _cache[account]); //If cached object is given, call it directly

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account}, null, $system.app.method(list, [account, callback]));
		}

		this.update = function() //Update the folders
		{
			if(!$system.is.digit(__selected.account)) return false;
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.update', account : __selected.account}, null, $system.app.method($self.folder.get, [__selected.account, null]));
		}
	}
