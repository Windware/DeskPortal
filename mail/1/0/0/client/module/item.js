
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _body = {}; //Message body cache

		var _cache = {}; //Message listing cache

		var _lock = false; //Do not allow updating multiple folders simultaneously

		var _preserve = 5; //Amount of minutes to keep local cache for each folder listings

		var _update = {}; //Flag to indicate that a folder should be updated from the server

		this.get = function(folder, callback, request) //Get list of mails of a folder
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.text(folder)) return log.param();

			if(_lock)
			{
				$system.app.callback(_class + '.get', callback);
				return false; //Wait for previous operation
			}

			if(!$system.is.digit(page)) page = $system.node.id($id + '_show').value;

			var page = $system.node.id($id + '_show').value;
			var order = $system.node.id($id + '_form').order.value;
			var reverse = $system.node.id($id + '_form').reverse.checked ? 1 : 0;

			$system.node.id($id + '_folder').value = folder;
			__selected = {folder : folder, page : page, order : order, reverse : reverse};

			//Create cache storage
			if(!_cache[folder]) _cache[folder] = {};
			if(!_cache[folder][page]) _cache[folder][page] = {};
			if(!_cache[folder][page][order]) _cache[folder][page][order] = {};

			var expire = function(folder, page, order, reverse) //Expire the cache
			{
				if(__selected.folder != folder || __selected.page != page || __selected.order != order || __selected.reverse != reverse)
					return _update[folder] = true; //If not currently displayed list, flag to note that this folder should be updated on next display

				delete _cache[folder]; //Remove the cache
				$self.item.get(folder); //Update the displaying list
			}

			var list = function(folder, page, order, reverse, request) //List the mails upon receiving the contents
			{
				//Create cache storage
				if(!_cache[folder]) _cache[folder] = {};
				if(!_cache[folder][page]) _cache[folder][page] = {};
				if(!_cache[folder][page][order]) _cache[folder][page][order] = {};

				_cache[folder][page][order][reverse] = request;

				var language = $system.language.strings($id);
				var section = $system.array.list('subject from date'); //List of columns to display

				var table = $system.node.id($id + '_read_mail'); //Display table
				while(table.firstChild) table.removeChild(table.firstChild); //Clean up the field (innerHTML on table breaks khtml)

				table.scrollTop = 0;

				var row = document.createElement('tr');
				row.id = $id + '_read_header';

				for(var i = 0; i < section.length; i++) //Set headers
				{
					var header = document.createElement('th');
					header.className = $id + '_row_' + section[i];

					header.onclick = $system.app.method($self.gui.sort, [section[i]]);
					$system.node.text(header, language[section[i]]);

					row.appendChild(header);
				}

				table.appendChild(row);
				if(!__mail[folder]) __mail[folder] = {}; //Mail information cache

				var max = $system.dom.tags(request.xml, 'page')[0];
				max = $system.dom.attribute(max, 'total');

				if($system.is.digit(max))
				{
					var zone = $system.node.id($id + '_paging');
					zone.innerHTML = '';

					var select = document.createElement('select');

					select.id = $id + '_show'; //NOTE : Using 'id' instead of 'name' since IE6 cannot set 'name' on a dynamically created select object
					select.onchange = $system.app.method($self.item.get, [__selected.folder]);

					for(var i = 1; i <= max; i++)
					{
						var option = document.createElement('option');

						option.value = i;
						$system.node.text(option, i);

						select.appendChild(option);
					}

					select.value = page;
					zone.appendChild(select);

					zone.appendChild(document.createTextNode(' / ' + max));
				}

				var part = ['from', 'to', 'cc'];
				var mail = $system.dom.tags(request.xml, 'mail');

				for(var i = 0; i < mail.length; i++)
				{
					var id = $system.dom.attribute(mail[i], 'id');
					var storage = __mail[folder][id] = {}; //Mail information

					var row = document.createElement('tr');
					row.style.cursor = 'pointer';

					$system.node.hover(row, $id + '_hover'); //Give mouse hovered style
					row.onclick = $system.app.method($self.item.show, [folder, id]); //Display the message pane on click

					var preview = $system.text.escape($system.dom.attribute(mail[i], 'preview')); //Grab the message preview
					if(!preview.match(/\S/)) preview = '(' + language.empty + ')';

					$system.tip.set(row, $system.info.id, 'blank', [preview.replace(/\\n/g, '\n')], true); //Show the preview with a tooltip

					for(var j = 0; j < mail[i].attributes.length; j++) //Keep mail attributes
					{
						var parameter = mail[i].attributes[j];
						storage[parameter.name] = parameter.value;
					}

					if(storage.read != '1') $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
					if(storage.marked) $system.node.classes(row, $id + '_mail_marked', true); //For marked mails

					for(var j = 0; j < section.length; j++) //For all the columns
					{
						var display = ''; //Parameter to display

						switch(section[j]) //Pick the parameters to display on the interface
						{
							case 'subject' : display = storage[section[j]]; break;

							case 'date' : display = $system.date.create(storage.sent).format($global.user.pref.format.monthdate); break;

							case 'from' : //Create mail addresses and concatenate
								for(var k = 0; k < part.length; k++)
								{
									var address = $system.dom.tags(mail[i], part[k]);
									storage[part[k]] = [];

									for(var l = 0; l < address.length; l++)
									{
										var real = $system.dom.attribute(address[l], 'address');
										if(!real) continue;

										storage[part[k]].push([real, $system.dom.attribute(address[l], 'name')]);
									}
								}

								display = [];

								for(var k = 0; k < storage.from.length; k++)
								{
									var format = [$system.tip.link($system.info.id, null, 'blank', [$system.text.escape(storage.from[k][0])]), $system.text.escape(storage.from[k][1])];
									display.push(storage.from[k][1] ? $system.text.format('<span style="cursor : help"%%>%%</span>', format) : storage.from[k][0]);
								}

								display = display.join(', ');
							break;
						}

						var cell = document.createElement('td');
						cell.innerHTML = display;

						row.appendChild(cell);
					}

					table.appendChild(row);
				}

				if(typeof callback == 'function') callback();
				_lock = false;

				if(_update[folder]) //If cache should be updated
				{
					delete _cache[folder]; //Remove the entire cache for the folder
					$self.item.get(folder); //Update it

					delete _update[folder];
				}

				return true;
			}

			_lock = true;

			if($system.is.object(request)) return list(folder, page, order, reverse, request); //If updating, use the passed object
			if(_cache[folder][page][order][reverse]) return list(folder, page, order, reverse, _cache[folder][page][order][reverse]); //If already cached, use it

			setTimeout($system.app.method(expire, [folder, page, order, reverse]), _preserve * 60000); //Update local cache after a period
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', folder : folder, page : page, order : order, reverse : reverse ? 1 : 0}, null, $system.app.method(list, [folder, page, order, reverse]));
		}

		this.update = function() //Reload the mails from the server
		{
			if(!$system.is.digit(__selected.folder)) return false;

			var page = $system.node.id($id + '_show').value;
			if(!$system.is.digit(page)) return false;

			$self.item.get(__selected.folder); //Refresh the listing
		}

		this.show = function(folder, message, callback) //Display the mail window
		{
			var log = $system.log.init(_class + '.show');

			if(!$system.is.digit(message)) return log.param();
			if(!__mail[folder] || !__mail[folder][message]) return log.user($global.log.WARNING, 'user/show/error', 'user/show/solution');

			if(!_body[folder]) _body[folder] = {}; //Create cache storage
			var id = $id + '_mail_' + message;

			if($system.node.id(id))
			{
				if($system.node.hidden(id)) $system.window.raise(id);
				return $system.node.fade(id);
			}

			var display = function(folder, message, callback, request)
			{
				_body[folder][message] = request;
				var body = $system.dom.text($system.dom.tags(request.xml, 'body')[0]).replace(/\n/g, '<br />\n');

				$system.node.id($id + '_mail_' + message + '_body').innerHTML = body; //Write out the message body
				$system.app.callback(_class + '.show.display', callback);
			}

			var parameter = __mail[folder][message];

			var section = ['from', 'to', 'cc'];
			var value = {index : message, sent : $system.date.create(parameter.sent).format($global.user.pref.format.full), subject : parameter.subject};

			var replace = function(phrase, match) { return variable[match]; }

			for(var i = 0; i < section.length; i++)
			{
				var list = parameter[section[i]];
				var address = [];

				if($system.is.array(list))
				{
					for(var j = 0; j < list.length; j++)
					{
						var variable = {address : $system.text.escape(list[j][0]), show : $system.text.escape(list[j][1] ? list[j][1] : list[j][0]), name : list[j][1] ? $system.text.escape(list[j][1]) : ''};
						variable.tip = $system.tip.link($system.info.id, null, 'blank', [$system.text.escape(list[j][0])]);

						address.push($self.info.template.address.replace(/%value:(.+?)%/g, replace));
					}
				}

				value[section[i]] = address.join(' ');
			}

			var replace = function(phrase, match) { return value[match]; }
			var template = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

			$system.window.create(id, $self.info.title, template, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);

			if(_body[folder][message]) return display(folder, message, callback, _body[folder][message]);
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.show', message : message}, null, $system.app.method(display, [folder, message, callback]));
		}
	}
