
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		var _cache = {}; //Listing cache

		this.change = function(folder) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.text(folder)) return log.param();

			$self.item.get($system.node.id($id + '_account').value, folder); //Get the folder for current account
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
					link.onclick = $system.app.method($self.item.get, [account, param.id]);

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
	}
