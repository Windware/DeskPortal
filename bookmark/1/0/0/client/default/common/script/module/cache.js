
	$self.cache = new function()
	{
		var _class = $id + '.cache';

		this.add = function(id) //Create a cache for the bookmark
		{
			var log = $system.log.init(_class + '.add');
			if(!$system.is.digit(id)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language['cache/confirm'])) return false;

			var update = function(id, request)
			{
				log.user($global.log.notice, 'user/cache/add', '', [__bookmarks[id] ? __bookmarks[id].name : id]);

				var status = $system.dom.tags(request.xml, 'status');
				status = $system.dom.attribute(status[0], 'value'); //Return value

				switch(status)
				{
					case '0' :
						var node = $system.dom.tags(request.xml, 'cache');
						var result = $system.dom.attribute(node[0], 'time') || '(' + language['error'] + ')';
					break;

					default : case '1' : var result = '(' + language.error + ')'; break;

					case '2' : var result = '(' + language['error/big'] + ')'; break;
				}

				$system.node.text([$id, 'cache', id, 'updated'].join('_'), result); //Set the result
				if(status !== '0') return; //Only update the cache content with a successful result

				var viewer = $system.node.id([$id, 'cache', id, 'viewer'].join('_')); //Cache frame
				if($system.is.element(viewer, 'iframe')) viewer.contentWindow.location.reload(); //Refresh the page with the new cache
			}

			$system.network.send($self.info.root + 'server/php/run.php', {task : 'cache.add'}, {id : id}, $system.app.method(update, [id]));
			return false; //Don't submit the form action
		}

		this.source = function(id) //Open the source page of the cache
		{
			var log = $system.log.init(_class + '.source');
			if(!$system.is.digit(id) || !__bookmarks[id]) return log.param();

			return window.open(__bookmarks[id].address);
		}
	}
