
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _body = {}; //Message body cache

		var _cache = {}; //Listing cache

		this.get = function(account, folder, page, callback, request) //Get list of mails of a folder for an account
		{
			page = 1;
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account) || !$system.is.text(folder) || !$system.is.digit(page)) return log.param();

			//Create cache storage
			if(!_cache[account]) _cache[account] = {};
			if(!_cache[account][folder]) _cache[account][folder] = {};

			$system.node.id($id + '_folder').value = folder;
			__selected = {account : account, folder : folder, page : page};

			var list = function(request) //List the mails upon receiving the contents
			{
				_cache[account][folder][page] = request;

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

				if(!__mail[account]) __mail[account] = {}; //Mail information cache
				if(!__mail[account][folder]) __mail[account][folder] = {};

				var part = ['from', 'to', 'cc'];
				var mail = $system.dom.tags(request.xml, 'mail');

				for(var i = 0; i < mail.length; i++)
				{
					var id = $system.dom.attribute(mail[i], 'id');
					var storage = __mail[account][folder][id] = {}; //Mail information

					var row = document.createElement('tr');
					row.style.cursor = 'pointer';

					$system.node.hover(row, $id + '_hover'); //Give mouse hovered style
					row.onclick = $system.app.method($self.item.show, [account, folder, id]); //Display the message pane on click

					var preview = $system.text.escape($system.dom.attribute(mail[i], 'preview')); //Grab the message preview
					if(!preview.match(/\S/)) preview = '(' + language.empty + ')';

					$system.tip.set(row, $system.info.id, 'blank', [preview.replace(/\\n/g, '\n')], true); //Show the preview with a tooltip

					for(var j = 0; j < mail[i].attributes.length; j++) //Keep mail attributes
					{
						var parameter = mail[i].attributes[j];
						storage[parameter.name] = parameter.value;
					}

					if(!storage.read) $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
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
				return true;
			}

			if($system.is.object(request)) return list(request); //If updating, use the passed object
			if(_cache[account][folder][page]) return list(_cache[account][folder][page]); //If already cached, use it

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', account : account, folder : folder, page : page, order : 'sent', reverse : 0}, null, list);
		}

		this.show = function(account, folder, message, callback) //Display the mail window (folder is also specified in case an unique ID is unavailable on the mail server)
		{
			var log = $system.log.init(_class + '.show');

			if(!$system.is.digit(account) || !$system.is.text(message)) return log.param();
			if(!__mail[account] || !__mail[account][folder] || !__mail[account][folder][message]) return log.user($global.log.WARNING, 'user/show/error', 'user/show/solution');

			//Create cache storage
			if(!_body[account]) _body[account] = {};
			if(!_body[account][folder]) _body[account][folder] = {};

			var id = $id + '_mail_' + message;

			if($system.node.id(id))
			{
				if($system.node.hidden(id)) $system.window.raise(id);
				return $system.node.fade(id);
			}

			var display = function(account, folder, message, callback, request)
			{
				_body[account][folder][message] = request;

				var parameter = __mail[account][folder][message];
				var body = parameter.body = $system.dom.text($system.dom.tags(request.xml, 'body')[0]);

				var section = ['from', 'to', 'cc'];
				var value = {index : message, sent : $system.date.create(parameter.sent).format($global.user.pref.format.full), subject : parameter.subject, body : body.replace(/\n/g, '<br />\n')};

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
				var body = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

				$system.window.create(id, $self.info.title, body, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);

				if(typeof callback == 'function') callback();
				return true;
			}

			if(_cache[account][folder][message]) return display(account, folder, message, callback, _cache[account][folder][message]);
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.show', account : account, folder : folder, message : message}, null, $system.app.method(display, [account, folder, message, callback]));
		}
	}
