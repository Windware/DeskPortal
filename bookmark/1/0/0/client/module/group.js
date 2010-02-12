
	$self.group = new function()
	{
		var _class = $id + '.group';

		var _load = function(callback, request) //Load group list XML and cache
		{
			__group = {indexed : {}, ordered : []}; //Initialize group lists
			var list = $system.dom.tags(request.xml, 'category'); //Get the attributes from the XML

			for(var i = 0; i < list.length; i++)
			{
				var id = $system.dom.attribute(list[i], 'id'); //Group ID
				var param = {id : id, name : $system.dom.attribute(list[i], 'name')};

				__group.ordered.push(param);
				__group.indexed[id] = param;
			}

			return $system.app.callback(_class + '._load', callback, [__group]);
		}

		this.get = function(callback) //Get the current group listing
		{
			if(__group !== undefined) return $system.app.callback(_class + 'get', callback, [__group]);
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'group.get'}, null, $system.app.method(_load, [callback]));
		}

		this.remove = function() //Removes the chosen group
		{
			var log = $system.log.init(_class + '.remove');

			var group = $system.node.id($id + '_pick_group');
			if(!$system.is.element(group) || !$system.is.digit(group.value)) return log.param();

			var language = $system.language.strings($id, 'conf.xml');
			if(!confirm(language.confirm)) return false;

			var removal = function(request)
			{
				log.user($global.log.notice, 'user/category/remove', '', [__group.indexed[group.value] ? __group.indexed[group.value].name : group.value]);

				//Clear out the input fields
				$system.node.id($id + '_pick_name').value = '';
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				$self.group.update(request); //Update the group list
				$system.node.id($id + '_pick_group').value = 0; //Set to the top option

				$self.item.get(); //Relist the entries
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'group.remove'}, {group : group.value}, removal);
		}

		this.set = function(form) //Sets a category
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form') || !$system.is.digit(form.group.value) || !$system.is.text(form.name.value, true)) return log.param();

			if(!form.name.value.length) return false; //TODO : make error

			var update = function(request)
			{
				log.user($global.log.notice, form.group.value != '0' ? 'user/category/update' : 'user/category/create', '', [form.name.value]);
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				$self.group.update(request);
				form.name.value = ''; //Empty the text box
			}

			$system.network.send($self.info.root + 'server/php/front.php', {task : 'group.set'}, {id : form.group.value, name : form.name.value}, update);
			return false; //Avoid the form from being submitted
		}

		this.update = function(request) //Update the group listing in various locations
		{
			var log = $system.log.init(_class + '.update');
			if(!$system.is.object(request)) return log.param();

			var list = function(group)
			{
				$self.conf._1_group(); //Set configuration tab groups
				$self.gui.group(); //Set groups on interfaces
			}

			_load(list, request); 
		}
	}
