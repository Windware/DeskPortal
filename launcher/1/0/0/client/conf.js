
	$self.conf = new function()
	{
		this._1_display = function()
		{
			var area = $system.node.id($id + '_listed'); //Area to place list of apps
			var replace = function(phrase, match) { return values[match]; }

			for(var category in __apps)
			{
				var header = document.createElement('h4');

				$system.node.text(header, __apps[category].name);
				area.appendChild(header);

				var list = __apps[category].list;

				for(var i = 0; i < list.length; i++)
				{
					var values = {id : list[i].id, title : list[i].title, check : !list[i].exclude ? ' checked="checked"' : ''};
					area.innerHTML += $self.info.template.used.replace(/%value:(.+?)%/g, replace);
				}
			}

			$system.node.id($id + '_display').checked = !!$global.user.conf[$id].display; //Check the option box
			return true;
		}

		this.save = function(form) //Save the display option
		{
			if(!$system.is.element(form, 'form')) return false;

			var state = form.display.checked ? 1 : 0;
			$system.node.fade($id + '_name', !state);

			var exclude = []; //Find list of excluded apps
			for(var i = 0; i < form.elements.length; i++) if($system.is.id(form.elements[i].name) && !form.elements[i].checked) exclude.push(form.elements[i].name);

			var notify = function(request)
			{
				if($system.dom.status(request.xml) == '0')
				{
					var notice = {title : 'user/conf/save', message : 'user/conf/save/message'};
					$self.run();
				}
				else var notice = {title : 'user/conf/fail', message : 'user/conf/fail/message'};

				return $system.gui.alert($id, notice.title, notice.message);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.save'}, {display : state, exclude : exclude}, notify);
		}
	}
