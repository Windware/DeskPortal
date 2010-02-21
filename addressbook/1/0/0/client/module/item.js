
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _address = {}; //Address information cache

		var _selection = {}; //Currently selected group information

		this.edit = function(id) //Pop a window to edit an entry
		{
			var log = $system.log.init(_class + '.edit');

			if(id === undefined) id = 0;
			if(!$system.is.digit(id)) return log.param();

			var node = $id + '_edit_' + id;
			var language = $system.language.strings($id);

			var focus = function() { if(!$system.node.hidden(node)) $system.node.id($id + '_form_name_' + id).focus(); } //Focus on the name field
			if($system.node.id(node)) return $system.window.fade(node, undefined, focus);

			if(id == 0) var replace = ''; //On a new entry, keep the fields blank
			else
			{
				if(!$system.is.object(_address[id])) return log.dev($global.log.error, 'dev/load', 'dev/load/solution', [id]); //Check if the data is loaded

				var replace = function(phrase, match) //Or use existing data
				{
					switch(match)
					{
						case 'address' : case 'note' : return _address[id][match].replace(/\\n/g, "\n"); break; //Convert line break marks

						default : return _address[id][match]; break;
					}
				}
			}

			var template = $self.info.template.edit.replace(/%value:id%/g, id); //HTML template for the window
			__opened[id] = true; //Add to the list of opened edit window

			var list = function(id) //Do post load operation of the edit window
			{
				if(id == 0)
				{
					var pick = 0;
					$system.node.hide($id + '_edit_delete_' + id); //Remove the 'delete' button on new entry
				}
				else
				{
					var pick = _address[id].groups

					switch(_address[id].sex)
					{
						case '0' : $system.node.id($id + '_sex_female_' + id).checked = true; break;

						case '1' : $system.node.id($id + '_sex_male_' + id).checked = true; break;
					}
				}

				$self.gui.group($id + '_edit_group_' + id, undefined, pick); //Group selection
			}

			return $system.window.create(node, $self.info.title + ' : ' + language.info, template.replace(/%value:(.+?)%/g, replace), $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 350, undefined, true, false, true, focus, $system.app.method(list, [id]), true);
		}

		this.get = function(group, refresh, callback) //Show the items
		{
			var log = $system.log.init(_class + '.get');

			if(group === '')
			{
				_selection.id = undefined;
				return $self.gui.header(); //On empty choice, clear up the list
			}

			if(group === undefined)
			{
				if(!$system.is.digit(_selection.id)) return $self.gui.header(); //If nothing is chosen, reload the header
				group = _selection.id; //If not set, use the current selection
			}

			if(!$system.is.digit(group)) return log.param();
			_selection.id = group; //Remember the selection

			var display = function(callback, request)
			{
				$system.node.fade($id + '_entries', false);
				$self.gui.header(); //Set table header

				if(request === undefined)
				{
					var wrong = false;

					//NOTE : Safari 4 (and likely above) reports getElementsByTagName a 'function' (Chrome's webkit reports as 'object')
					if($system.browser.name == 'Safari' && $system.browser.version >= 4) { if(typeof _selection.request != 'function') wrong = true; }
					else if(!$system.is.object(_selection.request)) wrong = true;

					if(wrong) return log.dev($global.log.error, 'dev/cache', 'dev/cache/solution');
					var list = _selection.request; //Use the cache if refreshing
				}
				else
				{
					var list = $system.dom.tags(request.xml, 'address');
					_selection.request = list;
				}

				var table = $system.node.id($id + '_entries');
				var language = $system.language.strings($id);

				var find = [];

				if(__search.length) //If searching
				{
					var phrase = __search.split(/(　|\s)/); //NOTE : Also splitting 'Japanese full width space' in UTF8
					for(var i = 0; i < phrase.length; i++) if(phrase[i].match(/\S/)) find.push(RegExp(phrase[i], 'i')); //Set the search phrases
				}

				var skip = $system.array.list('id groups birth_month birth_day updated note'); //Attributes to skip as an individual line

				for(var i = 0; i < list.length; i++)
				{
					var id = $system.dom.attribute(list[i], 'id');
					_address[id] = {};

					var info = ''; //Tip info string
					var nodes = list[i].attributes;

					var hit = {}; //Search result flag

					for(var j = 0; j < nodes.length; j++) //Load up the attributes
					{
						var name = nodes[j].nodeName;
						var param = _address[id][name] = $system.dom.attribute(list[i], name);

						if(find.length) for(var k = 0; k < find.length; k++) if(!hit[k] && param.match(find[k])) hit[k] = true; //Search for matches if a phrase is set
						if($system.array.find(skip, name)) continue;

						if(name == 'birth_year')
						{
							var birth = {month : $system.dom.attribute(list[i], 'birth_month') || '?', day : $system.dom.attribute(list[i], 'birth_day') || '?'};
							birth.date = [param, birth.month, birth.day].join('/');

							//Take away redundant slashes and question marks
							if(birth.date.match(/\/\?\/\?$/)) birth.date = birth.date.replace(/\/\?\/\?$/, '');
							else if(birth.date.match(/^\//)) birth.date = birth.date.replace(/^\//, '');
							else if(birth.date.match(/\/\?$/)) birth.date = birth.date.replace(/\/\?$/, '');

							name = 'birthday'; //Set to a translatable name
							_address[id][name] = birth.date;
						}
						else if(name == 'sex')
						{
							switch(param)
							{
								case '0' : param = language.female;

								case '1' : param = language.male;
							 }
						}
						else if(name == 'created') param = param.replace(/ .+/, ''); //Remove the time (But keep the data in the cache)

						if(param.length) info += language[name] + ' : ' + param + '\n'; //Create the tip info
					}

					if(find.length)
					{
						var ignore = false;
						for(var j = 0; j < find.length; j++) if(!hit[j]) ignore = true; //If search misses, ignore the entry

						if(ignore) continue;
					}

					var row = document.createElement('tr'); //Create a row
					$system.node.hover(row, $id + '_active'); //Make hover color change IE compatible

					$system.event.add(row, 'onmousedown', $system.app.method($system.event.cancel, [row])); //Don't let the window get dragged by clicking on the row
					row.onclick = $system.app.method($self.item.edit, [id]); //Show the edit window on click

					for(var j = 0; j < __all.length; j++) //Sustain the order by looking at the master list
					{
						if(!__column[__all[j]]) continue; //If not supposed to be listed, skip

						var cell = document.createElement('td');
						var value = _address[id][__all[j]]; //User information on the cell

						switch(__all[j])
						{
							case 'created' : value = value.replace(/ .+/, ''); break; //Remove time

							case 'sex' : //Show textual description
								var desc = [language.female, language.male];
								value = desc[value] || '';
							break;

							case 'mail_main' : case 'mail_mobile' : case 'mail_alt' :
								if(value.match('@'))
								{
									value = $system.text.escape(value);
									value = $system.text.template('<a onclick="%top%.%id%.gui.mail(\'' + value + '\')%cancel%"%tip:mail%>' + value + '</a>', $id);
								}
							break;

							case 'web' :
								if(value.length)
								{
									value = $system.text.escape(value);
									value = $system.text.template('<a onclick="%top%.%id%.gui.web(\'' + value + '\')%cancel%"%tip:web%>' + value + '</a>', $id);
								}
							break;

							default : value = $system.text.escape(value); break;
						}

						cell.innerHTML = value.replace(/\\n/g, '<br />\n');
						row.appendChild(cell); //Set the content cell on the row
					}

					$system.tip.set(row, $id, 'info', [info], true);
					table.appendChild(row); //Append the row onto the table
				}

				var notify = function(list)
				{
					if(request !== undefined) //Notify only when actually retrieved remotely
					{
						var name = list.indexed[group] && list.indexed[group].name || language.uncategorized;
						log.user($global.log.info, find.length ? 'user/search' : 'user/list', '', [name]);
					}

					if(typeof callback == 'function') callback();
				}

				$self.group.get(notify);
				return true;
			}

			if(refresh === true) return display(callback); //If refreshing, use the cache
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', group : group}, null, $system.app.method(display, [callback]));
		}

		this.remove = function(id) //Remove an entry
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(id)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.remove)) return;

			var load = function(request)
			{
				log.user($global.log.notice, 'user/remove', '', [_address[id] && _address[id].name || id]);

				$self.item.get(); //Reload the entries
				$self.gui.close(id); //Close the edit window
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.remove'}, {id : id}, load);
		}

		this.set = function(id, form) //Sets an entry information
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form') || !$system.is.digit(id)) return log.param();

			var quit = function(type) { return $system.gui.alert($id, type, type + '/solution') && false; }
			if(form.name.value == '') return quit('user/save/fail');

			var value = form.birth_year.value; //Check on birthday values
			if(value && !$system.is.digit(value) || String(value).length > 4) return quit('user/birth');

			var value = form.birth_month.value;
			if(value && (!$system.is.digit(value) || value < 1 || value > 12)) return quit('user/birth');

			var value = form.birth_day.value;
			if(value && (!$system.is.digit(value) || value < 1 || value > 31)) return quit('user/birth');

			var section = ['mail_main', 'mail_mobile', 'mail_alt'];

			for(var i = 0; i < section.length; i++)
			{
				var value = form[section[i]].value; //Check on mail value
				if(value && !value.match(/.@./)) return quit('user/mail');
			}

			var params = {id : id}; //The parameters to send

			for(var i = 0; i < form.elements.length; i++)
			{
				var item = form.elements[i];

				switch(item.type) //Pick only the values those need to be sent
				{
					case 'button' : case 'submit' : continue; break;

					case 'radio' : if(!item.checked) continue; break;
				}

				params[item.name] = item.value;
			}

			form.cancel.click(); //Close the window

			var save = function(selection)
			{
				log.user($global.log.notice, 'user/save', '', [params.name]);
				$self.item.get(selection);
			}

			var callback = $system.app.method(save, [$system.node.id($id + '_selection').value]); //Pass currently selected group
			$system.network.send($self.info.root + 'server/php/front.php', {task : 'item.set'}, params, callback);

			return false;
		}
	}
