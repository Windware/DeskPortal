
	$self.background = new function()
	{
		var _class = $id + '.background';

		var _loaded; //Indicates the wallpapers are loaded

		this.get = function(refresh) //Get a list of wallpapers
		{
			var log = $system.log.init(_class + '.get');
			if(!refresh && _loaded) return true;

			var list = function(request)
			{
				$system.node.id($id + '_wallpaper').innerHTML = ''; //Clear up any previous listings
				var image = $system.dom.tags(request.xml, 'image');

				for(var i = 0; i < image.length; i++)
				{
					var name = $system.dom.attribute(image[i], 'name');
					if(name.match(/\.\./)) continue; //Drop bad looking path

					var file = $system.info.devroot + 'graphic/wallpaper/' + name; //Requesting wallpaper file
					var box = document.createElement('img');

					box.className = $id + '_wallpaper';
					box.onclick = $system.app.method($system.image.wallpaper, [file, true]);

					box.src = $system.network.form($self.info.root + 'server/php/front.php?task=background.thumbnail&file=' + file);
					$system.node.id($id + '_wallpaper').appendChild(box); //Load the thumbnail images
				}

				_loaded = true;
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'background.get'}, {}, list);
		}
	}
