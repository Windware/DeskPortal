
	$self.thread = new function()
	{
		var _class = $id + '.thread';

		var _previous; //Previously selected thread

		this.get = function(id, node) //Get list of threads of a board
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(id) || !$system.is.element(node)) return log.param();

			var list = function(request)
			{
				if(!$system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/thread/get', 'user/thread/get/message');

				var thread = $system.dom.tags(request.xml, 'thread');
				var section = ['reply', 'title'];

				for(var x = 0; x < thread.length; x++)
				{
					var link = document.createElement('a');
					var index = $system.dom.attribute(thread[x], 'id');

					__message[index] = {number : x, reply : $system.dom.attribute(thread[x], 'reply'), 'title' : $system.dom.attribute(thread[x], 'title')};
					link.onclick = $system.app.method($self.message.get, [index]);

					$system.node.text(link, x + ' : ' + __message[index].title);
					zone.appendChild(link);
				}
			}

			$system.node.text($id + '_list_title', '');

			var zone = $system.node.id($id + '_thread');
			zone.innerHTML = '';

			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'thread.get', board : id}, null, list); //Get list of boards
		}
	}
