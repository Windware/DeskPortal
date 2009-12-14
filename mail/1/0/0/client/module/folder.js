
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		this.change = function(folder) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.text(folder)) return log.param();

			$self.item.get($system.node.id($id + '_account').value, folder); //Get the folder for current account
		}

		this.get = function(account, callback) //List folders for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account)) return log.param();

			var list = function(account, callback, request)
			{
				var area = $system.node.id($id + '_folder');
				area.innerHTML = '';

				var folder = $system.dom.tags(request.xml, 'folder');
				var section = ['name', 'count', 'recent']; //Folder parameters

				for(var i = 0; i < folder.length; i++)
				{
					var param = {}; //Folder parameters

					for(var j = 0; j < section.length; j++) param[section[j]] = $system.dom.attribute(folder[i], section[j]);
					__account[account].folder.push(param); //Keep folder information

					var link = document.createElement('a');
					link.onclick = $system.app.method($self.item.get, [account, param.name]);

					$system.node.text(link, param.name.match(/\./) ? param.name.replace(/^.+?\./, ' |- ') : param.name);
					area.appendChild(link);
				}

				if(typeof callback == 'function') callback();
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'folder.get', account : account}, null, $system.app.method(list, [account, callback]));
		}
	}
