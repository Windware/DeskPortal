
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _state = $system.array.list('current finished discarded'); //List of statuses

		var _times = $system.array.list('year month day hour minute'); //List of due time parameters

		var _todo; //Item cache

		this.add = function(form) //Add a new entry
		{
			var log = $system.log.init(_class + '.add');
			if(!$system.is.element(form, 'form')) return log.param();

			var title = form.subject.value;
			if(title == '') return false;

			var load = function(request)
			{
				switch(Number($system.dom.status(request.xml)))
				{
					case 0 : var message = 'user/add'; break;

					case 2 : var message = 'user/add/duplicate'; break;

					default : var message = 'user/add/fail'; break;
				}

				log.user($global.log.notice, message, '', [title]);

				$self.category.get(false); //Load the new list from the response
				$self.item.get(); //Craft the main table
			}

			//Register a new entry
			$system.window.fade($id + '_new', true, null, true); //Let go of the window completely
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.add'}, {name : title, category : form.category.value}, load);
		}

		this.get = function() //Get the list of todos
		{
			var log = $system.log.init(_class + '.get');

			var categories = $self.category.get(true);
			var language = $system.language.strings($id);

			var table = $system.node.id($id + '_main'); //The table 
			$system.node.fade(table.id, false);

			while(table.firstChild) table.removeChild(table.firstChild); //Clean up the field (Using innerHTML on table breaks khtml)

			log.user($global.log.info, 'user/get', '');
			var header = $system.array.list('title category due registered status');

			var row = document.createElement('tr');
			row.id = $id + '_header';

			for(var i = 0; i < header.length; i++) //Create the header section
			{
				var cell = document.createElement('th');
				$system.node.text(cell, language[header[i]]);

				row.appendChild(cell);
			}

			table.appendChild(row); //Add the header row

			var request = $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', filter : __display}, null, null);
			var list = $system.dom.tags(request.xml, 'item');

			_todo = {indexed : {}, ordered : []};

			for(var i = 0; i < list.length; i++) //Append the list to the table
			{
				var id = $system.dom.attribute(list[i], 'id');
				_todo.indexed[id] = {id : id, content : $system.dom.text(list[i])}; //Create list cache

				//Keep the values cached
				for(var j = 0; j < header.length; j++) _todo.indexed[id][header[j]] = $system.dom.attribute(list[i], header[j]);
				for(var j = 0; j < _times.length; j++) _todo.indexed[id][_times[j]] = $system.dom.attribute(list[i], _times[j]);

				_todo.ordered.push(_todo.indexed[id]); //Cache in the given order
			}

			var language = $system.language.strings($id);
			var today = $system.date.create();

			for(var i = 0; i < _todo.ordered.length; i++)
			{
				var info = _todo.ordered[i];

				var row = document.createElement('tr');
				row.id = $id + '_row_' + info.id;

				row.className = $id + '_row';
				$system.node.hover(row, $id + '_active'); //Make hover style

				$system.event.add(row, 'onmousedown', $system.app.method($system.event.cancel, [row])); //Don't let the window get dragged by clicking on the row
				row.onclick = $system.app.method($self.item.show, [info.id]); //Show the edit window on click

				var desc = {none : '(' + language.none + ')', unknown : '(?)'}; //Texts to show when a value is empty
				$system.tip.set(row, $id, 'content', [$system.text.escape(info.content) || desc.none], true); //Set the content as a tool tip

				for(var j = 0; j < header.length; j++)
				{
					var value = info[header[j]];
					var cell = document.createElement('td');

					switch(header[j]) //Format the display according to column
					{
						case 'category' :
							var display = categories[value] ? $system.text.escape(categories[value].name) : desc.none;
						break;

						case 'due' :
							if($system.is.digit(info.year)) //If due time is specified
							{
								var expanded = false; //Flag to keep unit expansion occurance
								var last = {}; //The absolute last moment till the expire time (Complements the undefined part of the expiration time)

								for(var k = 0; k < _times.length; k++) //For all of the time parameters
								{
									if(info[_times[k]] === '') //If the value is undefined
									{
										//Increment the previous entry to keep the expiration time till the end of that unit's digit
										//(Ex : If only year is defined as 2009, let the expiration time at the start of 2010)
										if(!expanded)
										{
											last[_times[k - 1]]++; //If a unit exceeds the maximum possible value, let the 'Date' JS function deal with it
											expanded = true; //Only expand the last unit defined
										}

										last[_times[k]] = (k <= 2) ? 1 : 0; //When undefined, get the month and day as '1' and hour and minute as '0'
									}
									else last[_times[k]] = info[_times[k]]; //Otherwise, use as is
								}

								var expire = $system.date.create([last.year, last.month, last.day, last.hour, last.minute], true);
								var left = parseInt((expire.timestamp() - today.timestamp()) / (3600 * 24), 10); //Get the days left

								if(left <= 0) left = 0; //Don't show negative expiration time

								//Format the time according to available parameters
								if(info.year)
								{
									if(info.month)
									{
										if(info.day)
										{
											if(info.hour)
											{
												var time = $system.date.create([info.year, info.month, info.day, info.hour, info.minute]);
												var display = time.format($global.user.pref.format.full);
											}
											else
											{
												var time = $system.date.create([info.year, info.month, info.day]);
												var display = time.format($global.user.pref.format.date);
											}
										}
										else
										{
											var time = $system.date.create([info.year, info.month]);
											var display = time.format($global.user.pref.format.month);
										}
									}
									else
									{
										var time = $system.date.create([info.year]);
										var display = time.format($global.user.pref.format.year);
									}
								}
								else var display = '';

								var days = language.left.replace('%%', left);
								if(info.status == 0 && left <= 3) days = '<span class="' + $id + '_close">' + days + '</span>'; //Hilight expiring entry

								display += ' (' + days + ')';
							}
							else var display = desc.none;
						break;

						case 'registered' :
							var display = desc.unknown;

							if(value)
							{
								var day = value.replace(/ .+/, '').split('-');
								day = $system.date.create([day[0], day[1], day[2]]);

								if(day) display = day.format($global.user.pref.format.date);
							}
						break;

						case 'status' :
							var display = _state[value] && language[_state[value]] ? language[_state[value]] : desc.unknown;
							if(value != 0) $system.node.classes(row, $id + '_inactive', true);
						break;

						default : var display = $system.text.escape(value); break;
					}

					if(header[j] == 'title') cell.className = $id + '_title';
					cell.innerHTML = display;

					row.appendChild(cell);
				}

				table.appendChild(row);
			}
		}

		this.remove = function(id) //Remove an entry
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(id)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.remove)) return false;

			var update = function(id, request)
			{
				log.user($global.log.notice, 'user/remove', '', [_todo.indexed[id] ? _todo.indexed[id].title : id]);

				$self.item.get(); //Refresh the list
				$system.window.fade($id + '_info_' + id, true, null, true);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.remove'}, {id : id}, $system.app.method(update, [id]));
		}

		this.set = function(form) //Set an item's information
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form')) return log.param();

			var end = false; //Indicates if the time specified ends there
			var param = []; //Time parameter

			for(var i = 0; i < _times.length; i++)
			{
				var value = form[_times[i]].value;

				if(!$system.is.digit(value)) end = true; //If a value is unspecified, only track the upper selections
				else
				{
					if(end) //If any other values are set after having an empty selection, show error
					{
						$system.gui.alert($id, 'user/time', 'user/time/solution');
						return log.user($global.log.warning, 'user/time', 'user/time/solution');
					}

					param.push(value);
				}
			}

			var time = $system.date.create([param[0], param[1], param[2], param[3], param[4]]); //Get the time object with the specified time

			if(!time.valid)
			{
				$system.gui.alert($id, 'user/time', 'user/time/solution');
				return log.user($global.log.warning, 'user/time', 'user/time/solution');
			}

			var param = {}; //Parameters to send

			var section = $system.array.list('id title category status content').concat(_times);
			for(var i = 0; i < section.length; i++) param[section[i]] = form[section[i]].value;

			var update = function(value, request)
			{
				log.user($global.log.notice, 'user/update', '', [param.title]);

				$self.item.get(); //Refresh the list
				$system.window.fade($id + '_info_' + value, true, null, true);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.set'}, param, $system.app.method(update, [form.id.value]));
		}

		this.show = function(id) //Open the information window
		{
			var log = $system.log.init(_class + '.show');
			if(!$system.is.digit(id)) return log.param();

			if(!_todo.indexed[id]) //If the item information is not found
			{
				var all = 1; //Filter option index for displaying all items
				if(__display == all) return log.dev($global.log.warning, 'dev/load', 'dev/load/solution', [id]); //If all items are listed yet the information is not found, quit

				$system.node.id($id + '_filtering').value = all; //Switch the filter option
				$self.gui.filter(all); //Refresh the list
			}

			var node = $id + '_info_' + id;
			if($system.node.id(node)) return $system.window.fade(node);

			_todo.indexed[id].registered.match(/^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)$/);
			var registered = {year : RegExp.$1, month : RegExp.$2, day : RegExp.$3, hour : RegExp.$4, minute : RegExp.$5, second : RegExp.$6};

			var replace = function(phrase, match)
			{
				var value = _todo.indexed[id][match];

				switch(match)
				{
					case 'category' : //Create select form options
						var options = '<option value="">-----</option>\n';

						for(var i = 0; i < cats.length; i++)
						{
							var checked = value == cats[i].id ? ' selected="selected"' : '';
							options += $system.text.format('<option value="%%"%%>%%</option>\n', [cats[i].id, checked, $system.text.escape(cats[i].name)]);
						}

						return options;
					break;

					case 'registered':
						if(!value) return '';
						value = value.replace(/ .+/, '').split('-');

						var date = $system.date.create([value[0], value[1], value[2]]);
						return date.format($global.user.pref.format.date);
					break;

					case 'status':
						var language = $system.language.strings($id);
						var options = '';

						for(var i = 0; i < _state.length; i++)
						{
							var checked = value == i ? ' selected="selected"' : '';
							options += $system.text.format('<option value="%%"%%>%%</option>\n', [i, checked, language[_state[i]]]);
						}

						return options;
					break;

					case 'year' : return $system.date.select(Number(registered.year), Number(registered.year) + 10, false, value); break;

					case 'month' : return $system.date.select(1, 12, false, value); break;

					case 'day' : return $system.date.select(1, 31, false, value); break;

					case 'hour' : return $system.date.select(0, 23, false, value); break;

					case 'minute' : return $system.date.select(0, 59, false, value, true); break;

					default : return value; break;
				}
			}

			var cats = $self.category.get();
			var body = $self.info.template.info.replace(/%value:(.+?)%/g, replace);

			__opened[id] = true; //Remember the opened window
			return $system.window.create(node, $self.info.title, body, $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 500, undefined, true, false, true, null, null, true);
		}
	}
