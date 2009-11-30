
	$self.group = new function()
	{
		var _class = $id + '.group';

		var _groups; //List of groups

		this.get = function(indexed, list) //Get the current group listing
		{
			var log = $system.log.init(_class + '.get');
			if(_groups !== undefined && list === undefined) return indexed ? _groups.indexed : _groups.ordered;

			if(!$system.is.object(list)) //Get a fresh list if not provided - TODO : try async with a callback
			{
				var request = $system.network.send($self.info.root + 'server/php/front.php', {task : 'group.get'}, null, null);
				if(!request.valid()) return false;

				list = request.xml;
			}

			_groups = {indexed : {}, ordered : []};
			list = $system.dom.tags(list, 'group'); //Get the attributes from the XML

			for(var i = 0; i < list.length; i++)
			{
				var param = {id : $system.dom.attribute(list[i], 'id'), name : $system.dom.attribute(list[i], 'name')};

				_groups.ordered.push(param);
				_groups.indexed[$system.dom.attribute(list[i], 'id')] = param;
			}

			return indexed ? _groups.indexed : _groups.ordered;
		}

		this.remove = function() //Removes the chosen group
		{
			var log = $system.log.init(_class + '.remove');

			var group = $system.node.id($id + '_pick_group').value;
			if(!$system.is.digit(group)) return log.param();

			var language = $system.language.strings($id, 'conf.xml');
			if(!confirm(language.confirm)) return false;

			var removal = function(request)
			{
				log.user($global.log.notice, 'user/group/remove', '', [_groups.indexed[group] ? _groups.indexed[group].name : group]);

				//Clear out the input fields
				$system.node.id($id + '_pick_name').value = '';
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				$self.group.update(request.xml); //Update the group list
				$system.node.id($id + '_pick_group').value = 0; //Set to the top option

				$self.item.get(); //Relist the memo
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'group.remove'}, {group : group}, removal);
		}

		this.set = function(form) //Sets a group
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form') || !$system.is.digit(form.group.value) || !$system.is.text(form.name.value, true)) return log.param();

			if(!form.name.value.length) return false; //TODO : make error

			var name = form.name.value; //Keep the input name
			form.name.value = ''; //Empty the text box

			var update = function(request)
			{
				log.user($global.log.notice, form.group.value != '0' ? 'user/group/update' : 'user/group/create', '', [name]);

				$system.node.hide($id + '_pick_delete', true); //Remove the delete button
				$self.group.update(request.xml);
			}

			$system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.set'}, {id : form.group.value, name : name}, update);
			return false; //Avoid the form from being submitted
		}

		this.update = function(xml) //Update the group listing in various locations
		{
			var log = $system.log.init(_class + '.update');
			var groups = $self.group.get(false, xml); //Update the group list on the interface

			$self.gui.fill(); //Update the group list on the new memo window
			$self.item.get(); //Update the group on the main list

			$self.conf._1_group(); //Update the group list on the configuration panel

			for(var index in __opened)
			{
				var form = $system.node.id($id + '_edit_groups_' + index); //Find the window's group selection form
				if(!$system.is.element(form, 'form')) continue;

				form.innerHTML = ''; //Clean up the group check boxes

				for(var j = 0; j < groups.length; j++) //Set the group list
				{
					//If the category matches, keep it checked
					var checked = __relation[groups[j].id] && __relation[groups[j].id].indexed[index] ? ' checked="checked"' : '';
					form.innerHTML += $system.text.format($self.info.template['choice'], {id : groups[j].id, name : groups[j].name, check : checked});
				}
			}
		}
	}
