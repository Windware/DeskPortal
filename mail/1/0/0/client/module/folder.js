
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		var _lock = false; //Avoid requesting multiple folders at once

		var _previous; //Previously selected folder

		this.change = function(account, folder) //Change the displaying folder
		{
			if(_lock) return false;

			var log = $system.log.init(_class + '.change');
			if(!$system.is.text(folder)) return log.param();

			_lock = true;

			//Change the look of the chosen folder
			if(_previous) $system.node.classes($id + '_folder_' + _previous, $id + '_displayed', false);
			$system.node.classes($id + '_folder_' + folder, $id + '_displayed', true);

			var unlock = function() { _lock = false; }
			$self.item.get(account, folder, unlock); //Get the folder for current account

			_previous = folder;
		}

		this.get = function(account, callback, request) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			var list = function(account, callback, request)
			{
				_cache[account] = request;

				var area = $system.node.id($id + '_folder');
				area.innerHTML = '';

				var folder = $system.dom.tags(request.xml, 'folder');
				var section = ['id', 'name', 'count', 'recent']; //Folder parameters

				__folder[account] = {};

				for(var i = 0; i < folder.length; i++)
				{
					var param = {}; //Folder parameters

					for(var j = 0; j < section.length; j++) param[section[j]] = $system.dom.attribute(folder[i], section[j]);
					__folder[account][param.name] = param; //Keep folder information

					var link = document.createElement('a');
					link.id = $id + '_folder_' + param.id;

					link.onclick = $system.app.method($self.folder.change, [account, param.id]);

					$system.node.text(link, param.name.match(/\./) ? param.name.replace(/^.+?\./, ' |- ') : param.name);
					area.appendChild(link);
				}

				if(typeof callback == 'function') callback();
				return true;
			}

			if($system.is.object(request)) return list(account, callback, request); //If cached object is given, call it directly
			if(_cache[account]) return list(account, callback, _cache[account]); //If cached object is given, call it directly

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account}, null, $system.app.method(list, [account, callback]));
		}

		this.update = function() //Update the folders
		{
			if(!__selected.account || !__selected.folder) return;

			var list = function(request) { $self.folder.get(__selected.account, null, request); }
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.update', folder : __selected.folder}, null, list);
		}
	}
