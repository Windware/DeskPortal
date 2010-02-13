
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _displayed; //Currently displayed memo ID

		this.add = function(form) //Adds a new memo
		{
			var log = $system.log.init(_class + '.add');
			if(!$system.is.element(form, 'form')) return log.param();

			var title = form.subject.value;
			if(title == '') return false;

			var groups = [];

			for(var i = 0; i < form.elements.length; i++) //Find the checked list of groups
				if(form.elements[i].type == 'checkbox' && form.elements[i].checked) groups.push(form.elements[i].value);

			var load = function(request)
			{
				log.user($global.log.notice, 'user/add', '', [title]);
				$self.item.get(request.xml); //Load the memos
			}

			//Register a new memo
			$system.network.send($self.info.root + 'server/php/front.php', {task : 'item.set'}, {name : title, groups : groups}, load);
			$system.window.fade($id + '_new', true, undefined, true); //Let go of the window completely

			return false; //Invalidate the form from changing the page
		}

		this.get = function(list) //Get the list of current memos
		{
			if(!$system.is.object(list)) //TODO - try async
			{
				var request = $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get'}, null, null);
				if(!request.valid()) return false;

				list = request.xml; //Get the response list
			}

			//Initialize the memo information hash
			__memos = {};
			__relation = {};

			var section = $system.array.list('id name groups last');
			var entries = $system.dom.tags(list, 'memo'); //All memo

			for(var i = 0; i < entries.length; i++)
			{
				var param = {}; //Memo information parameters
				for(var j = 0; j < section.length; j++) param[section[j]] = $system.dom.attribute(entries[i], section[j]);

				__memos[param.id] = {name : param.name, groups : param.groups.split(','), last : param.last}; //Keep memo names
				var belongs = param.groups.split(',');

				for(var j = 0; j < belongs.length; j++)
				{
					if(!$system.is.digit(belongs[j])) belongs[j] = 0;
					if(!__relation[belongs[j]]) __relation[belongs[j]] = {indexed : {}, ordered : []};

					__relation[belongs[j]].ordered.push(param.id); //Keep the list of memo by group
					__relation[belongs[j]].indexed[param.id] = true; //Keep the relation by memo ID index
				}
			}

			var area = $system.node.id($id + '_list'); //The list area
			area.innerHTML = ''; //Clean out the current list first

			var language = $system.language.strings($id);

			var list = $self.group.get(); //Get the group in the order passed
			if(__relation[0]) list = list.concat([{id : 0, name : '(' + language.other + ')'}]); //Add uncategorized group if any exists

			for(var i = 0; i < list.length; i++)
			{
				var id = list[i].id; //The group's ID
				if(!__relation[id] || !__relation[id].ordered || __relation[id].ordered.length == 0) continue; //Skip empty groups

				var tag = document.createElement('p'); //Group listing

				tag.id = $id + '_tag_' + id;
				tag.className = $id + '_closed';

				$system.node.text(tag, list[i].name);
				tag.style.cursor = 'pointer';

				tag.onclick = $system.app.method($self.gui.expand, [id]); //Function to expand the group entries
				$system.event.add(tag, 'onmousedown', $system.app.method($system.event.cancel, [tag]));

				area.appendChild(tag);
				$system.tip.set(tag, $id, 'expand');

				var zone = document.createElement('div');

				zone.id = $id + '_zone_' + id;
				zone.className = $system.info.id + '_hidden'; //Keep the group hidden first

				for(var j = 0; j < __relation[id].ordered.length; j++)
				{
					var memo = __relation[id].ordered[j];

					var source = [/%memo%/g, /%name%/g, /%group%/g];
					var target = [memo, $system.text.escape(__memos[memo].name), id];

					zone.innerHTML += $system.text.replace($self.info.template.entry, source, target);
				}

				area.appendChild(zone);
				if(__expansion[id]) $self.gui.expand(id, true); //Expand the currently selected groups
			}

			if(_displayed) $self.item.show(_displayed.id, _displayed.group); //Open the currently displayed memo
		}

		this.remove = function(id) //Remove a memo
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(id)) return log.param();

			var language = $system.language.strings($id);
			var name = __memos[id] ? __memos[id].name : id;

			if(!confirm(language.confirm.replace(/%%/, name))) return;

			$system.window.fade($id + '_edit_' + id, true, null, true); //Let go of the edit window
			$system.node.fade($id + '_field_' + id, true, null, true); //Let go of the text field

			delete __opened[id]; //Let go from the list of opened info window list
			if(id == _displayed.id) _displayed = null; //Forget about current selection

			var load = function(request)
			{
				$self.item.get(request.xml); //Load the memos
				log.user($global.log.notice, 'user/remove', '', [name]);
			}

			//Remove the entry
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.remove'}, {id : id}, load);
		}

		this.save = function(id) //Save the memo content
		{
			var log = $system.log.init(_class + '.save');

			var field = $system.node.id($id + '_field_' + id);
			if(!field) return false; //If memo is deleted, quit

			var content = field.value; //The content text

			var confirmation = function(request) //Upon request completion
			{
				if($system.dom.status(request.xml) != '0') return log.user($global.log.notice, 'user/save/fail'); //Log about save failure

				log.user($global.log.notice, 'user/save', '', [__memos[id] ? __memos[id].name : id]);
				var field = $system.node.id($id + '_field_' + id);

				if($system.is.element(field)) $system.node.classes(field, $id + '_altered', false); //Change the look of the field on save
				__content[id] = field.value; //Keep the saved content text
			}

			//Save the content remotely
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.save'}, {id : id, content : content}, confirmation);
		}

		this.set = function(id, form) //Submit the edited memo information to the server
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.digit(id) || !$system.is.element(form, 'form')) return log.param();

			var param = {id : id, groups : [], name : form.name.value}; //Form value to send
			var lists = $system.node.id($id + '_edit_groups_' + id).elements; //Group check boxes

			delete __opened[id]; //Remove from the opened info window list
			for(var i = 0; i < lists.length; i++) if(lists[i].checked) param.groups.push(lists[i].value); //Pick the checked values

			if(_displayed) _displayed.group = param.groups[0] || 0; //Set the belonging group of the displayed memo to the first group found

			var load = function(request)
			{
				log.user($global.log.notice, 'user/edit', '', [param.name]);

				$self.group.get(); //Update the list
				$self.item.get(request.xml); //Update the list with the given data
			}

			$system.window.fade($id + '_edit_' + id, true, null, true); //Let go of the edit window
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.set'}, param, load);
		}

		this.show = function(id, group) //Load a memo
		{
			var log = $system.log.init(_class + '.show');
			var node = $system.node.id([$id, 'link', group, id].join('_'));

			if(!$system.is.digit(id) || !$system.is.element(node)) return log.param();
			var field = $id + '_field_' + id; //Memo edit area

			var display = function(id, group)
			{
				if(!_displayed) $system.node.fade($id + '_field_' + id, false); //Show the edit area
				else if(_displayed.id != id)
				{
					$system.node.hide($id + '_field_' + _displayed.id, true); //Hide the last edit area
					$system.node.fade($id + '_field_' + id, false); //Show the edit area
				}

				if(__clicked) __clicked.className = $id + '_opened'; //Revert the look
				node.className = $id + '_active'; //Change the look to be active

				$self.gui.expand(group, true); //Expand the specified group

				//Remember the current selection
				_displayed = {id : id, group : group};
				__clicked = node;
			}

			if($system.node.id(field)) return display(id, group); //If already loaded, turn it on

			var language = $system.language.strings($id); //Get the language strings
			var edit = document.createElement('textarea'); //Create a new edit area

			//Keep track of edited tab on key up and on paste : TODO - Opera doesn't support onpaste
			edit.onkeyup = edit.onpaste = $system.app.method($self.gui.prepare, [id]);
			edit.id = field; //Give a unique ID for the text area

			$system.node.hide(edit, true); //Hide before the last entry disappears

			$system.node.text(edit, language.loading); //Set initial loading text
			$system.node.id($id + '_edit').appendChild(edit); //Create the new edit area

			var load = function(id, group, request) //Show the memo content to the screen
			{
				var text = $system.dom.text($system.dom.tags(request.xml, 'content')[0]); //Memo content
				var field = $system.node.id($id + '_field_' + id);

				__content[id] = field.value = text; //Show the content and keep the content cached for alteration observation
				display(id, group);

				log.user($global.log.info, 'user/load', '', [__memos[id] ? __memos[id].name : id]);
			}

			//Load the memo content
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.show', id : id}, null, $system.app.method(load, [id, group]));
		}
	}
