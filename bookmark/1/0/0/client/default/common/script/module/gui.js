
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _list = function(id) //Construct category list HTML
		{
			if(!$system.is.digit(id)) return '';
			var category = '';

			for(var i = 0; i < __group.ordered.length; i++) //FIXME - Get the HTML as a template
			{
				var cat = __group.ordered[i];
				var checked = __bookmarks[id].category[cat.id] ? ' checked="checked"' : ''; //If the category matches, keep it checked

				var text = '<div><label for="%id%_cat_%bookmark%_%cat%"><input id="%id%_cat_%bookmark%_%cat%" type="checkbox" value="%cat%"%check% /> %name%</label></div>';
				category += $system.text.format(text, {bookmark : id, cat : cat.id, name : $system.text.escape(cat.name), id : $id, check : checked});
			}

			return category;
		}

		this.adjust = function(frame) //For iPhone, wrap the cache in a container to limit the content size, since iframe's size cannot be set
		{
			if(!$system.browser.os != 'iphone') return;
			if(!$system.is.element(frame, 'iframe')) return false;

			var node;
			var inside = frame.contentWindow.document;

			var zone = inside.createElement('div');
			zone.style.overflow = 'auto';

			while(node = inside.body.firstChild) zone.appendChild(node);

			zone.style.width = frame.clientWidth + 'px';
			zone.style.height = frame.clientHeight + 'px';

			inside.body.appendChild(zone);
			return true;
		}

		this.cache = function(id) //Create the cache content window of a bookmark
		{
			var log = $system.log.init(_class + '.cache');
			if(!$system.is.digit(id)) return log.param();

			var node = $id + '_cache_' + id; //Cache view window ID

			var language = $system.language.strings($id);
			var title = $self.info.title + ' ' + language.cache;

			var values = {id : id, title : __bookmarks[id].name, cache : $system.network.form($self.info.root + 'server/php/run.php?task=gui.cache&id=' + id)};
			var replace = function(phrase, match) { return values[match]; } //Replace variables

			if($system.node.id(node)) return $system.window.fade(node);
			return $system.window.create(node, title, $self.info.template.cache.replace(/%value:(\w+?)%/g, replace), $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 700, 400, true, false, true, null, null, true);
		}

		this.edit = function(id) //Bring up bookmark edit window
		{
			var log = $system.log.init(_class + '.edit');
			if(!$system.is.digit(id)) return log.param();

			var node = $id + '_edit_' + id;
			if($system.node.id(node)) return $system.window.fade(node); //Fade in or out after created

			var language = $system.language.strings($id);
			var title = $self.info.title + ' ' + language.edit;

			var date = $system.date.create(__bookmarks[id].added).format($global.user.pref.format.date);

			var values = {id : id, cat : _list(id), added : date ? date : '?'};
			var section = $system.array.list('cache name address state viewed');

			for(var i = 0; i < section.length; i++) values[section[i]] = __bookmarks[id][section[i]];
			if(!values.cache) values.cache = language.never;

			var replace = function(phrase, match) { return values[match]; } //Replace variables
			$system.window.create(node, title, $self.info.template.edit.replace(/%value:(\w+?)%/g, replace), $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 400, undefined, true, false, true, null, null, true);

			__opened[id] = true;
		}

		this.group = function(callback) //Set category on the main interface
		{
			var log = $system.log.init(_class + '.group');

			var list = function(callback, group)
			{
				var chosen = {}; //List of checked categories

				var checks = $system.node.id($id + '_selection').elements;
				for(var i = 0; i < checks.length; i++) if(checks[i].checked) chosen[checks[i].value] = true;

				var zone = $system.node.id($id + '_categories'); //Area to list categories
				zone.innerHTML = ''; //Reset the category list

				for(var i = 0; i < group.ordered.length; i++) //Append the rest of the entries
				{
					var box = document.createElement('input'); //Make a check box

					box.type = 'checkbox';
					box.id = $id + '_box_' + group.ordered[i].id;

					box.value = group.ordered[i].id; //Set its parameters
					box.onclick = $self.item.get;

					if(chosen[box.value]) box.checked = true; //Check if within the specified list
					var area = document.createElement('div');

					var line = document.createElement('label'); //Create a label tag
					var name = group.ordered[i].name;

					if($system.browser.engine == 'trident') line.setAttribute('htmlFor', box.id);
					else line.setAttribute('for', box.id);

					line.appendChild(box); //Set the check box
					line.appendChild(document.createTextNode(' ' + name)); //Set textual name

					var zone = $system.node.id($id + '_categories'); //Area to list categories

					area.appendChild(line);
					zone.appendChild(area); //Append to the category zone
				}

				for(var id in __opened) //Set the new categories in the edit windows open
				{
					var form = $system.node.id($id + '_edit_categories_' + id);
					if($system.is.element(form, 'form')) form.innerHTML = _list(id);
				}

				return $system.app.callback(log.origin + '.list', callback);
			}

			return $self.group.get($system.app.method(list, [callback]));
		}

		this.open = function(id) //Open a link
		{
			var log = $system.log.init(_class + '.open');
			open(__bookmarks[id].address); //Open the page

			//TODO - Dynamically increase the count on the gui

			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'item.viewed'}, {id : id}); //Increase the view count
		}

		this.select = function(on) //Select or deselect all categories
		{
			var log = $system.log.init(_class + '.select');
			var items = $system.node.id($id + '_selection').elements; //Pick all the elements

			//Change the checked status on the 'input' elements
			for(var i = 0; i < items.length; i++) if(items[i].type == 'checkbox') items[i].checked = !!on;
			return $self.item.get(); //Apply the selected categories to the list
		}
	}
