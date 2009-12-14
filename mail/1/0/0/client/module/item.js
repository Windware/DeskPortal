
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _count = 0; //Mail numbering

		this.get = function(account, folder, callback) //Get list of mails of a folder for an account
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account) || !$system.is.text(folder)) return log.param();

			$system.node.id($id + '_folder').value = folder;

			var list = function(request) //List the mails upon receiving the contents
			{
				var language = $system.language.strings($id);

				var section = $system.array.list('subject from date'); //List of columns to display
				var part = $system.array.list('from to cc'); //List of mail address fields

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
				__folder[account] = {}; //Mail folder list cache

				if(!__local[account]) __local[account] = {}; //Local mail numbering
				if(!__mail[account]) __mail[account] = {}; //Mail information cache

				var folder = $system.dom.tags(request.xml, 'folder');

				for(var i = 0; i < folder.length; i++) //For all the folders given
				{
					var name = $system.dom.attribute(folder[i], 'name'); //Folder name

					__folder[account][name] = []; //Mail cache storage for the folder
					__local[account][name] = {};

					if(!__mail[account][name]) __mail[account][name] = {};
					var mail = $system.dom.tags(folder[i], 'mail');

					for(var j = 0; j < mail.length; j++)
					{
						var id = $system.dom.attribute(mail[j], 'id');

						__mail[account][name][id] = {}; //Mail information
						__local[account][name][id] = ++_count; //Give a local numbering to each mail

						var row = document.createElement('tr');
						row.style.cursor = 'pointer';

						$system.node.hover(row, $id + '_hover'); //Give mouse hovered style
						row.onclick = $system.app.method($self.item.show, [account, name, id]); //Display the message pane on click

						var body = $system.text.escape($system.dom.text($system.dom.tags(mail[j], 'body')[0])); //Grab the body message
						if(!body.match(/\S/)) body = '(' + language.empty + ')';

						$system.tip.set(row, $system.info.id, 'blank', [body], true); //Show the body with a tooltip
						__folder[account][name].push(id);

						for(var k = 0; k < mail[j].attributes.length; k++) //Keep mail attributes
						{
							var parameter = mail[j].attributes[k];
							__mail[account][name][id][parameter.name] = parameter.value;
						}

						if(__mail[account][name][id].recent || __mail[account][name][id].unseen) $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
						if(__mail[account][name][id].flagged) $system.node.classes(row, $id + '_mail_marked', true); //For marked mails

						for(var k = 0; k < section.length; k++) //For all the columns
						{
							var display = ''; //Parameter to display

							switch(section[k]) //Pick the parameters to display on the interface
							{
								case 'subject' : display = __mail[account][name][id][section[k]]; break;

								case 'date' : display = $system.date.create(Date.parse(__mail[account][name][id].date) / 1000).format($global.user.pref.format.monthdate); break;

								case 'from' : //Create mail addresses and concatenate
									display = [];

									for(var l = 0; l < part.length; l++)
									{
										var address = $system.dom.tags(mail[j], part[l]);
										__mail[account][name][id][part[l]] = [];

										for(var m = 0; m < address.length; m++)
										{
											var show = $system.dom.attribute(address[m], 'personal');
											var real = $system.dom.attribute(address[m], 'mailbox') + '@' + $system.dom.attribute(address[m], 'host');

											__mail[account][name][id][part[l]].push([real, show]);
											if(part[l] != section[k]) continue;

											show = show ? '<span style="cursor : help"' + $system.tip.link($system.info.id, null, 'blank', [$system.text.escape(real)]) + '>' + $system.text.escape(show) + '</span>' : real;
											display.push(show);
										}
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
				}
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', account : account, folder : folder}, null, list);
		}

		this.show = function(account, folder, message) //Display the mail window (folder is also specified in case an unique ID is unavailable on the mail server)
		{
			var log = $system.log.init(_class + '.show');

			if(!$system.is.digit(account) || !$system.is.text(message)) return log.param();
			if(!__local[account] || !__local[account][folder] || !__local[account][folder][message]) return log.user($global.log.WARNING, 'user/show/error', 'user/show/solution');

			var index = __local[account][folder][message];
			var id = $id + '_mail_' + index;

			if($system.node.id(id))
			{
				if($system.node.hidden(id)) $system.window.raise(id);
				return $system.node.fade(id);
			}

			var display = function(account, folder, message, request)
			{
				var body = __mail[account][folder][message].body = $system.dom.text($system.dom.tags(request.xml, 'body')[0]);

				var section = ['from', 'cc', 'to'];
				var value = {index : index, date : __mail[account][folder][message].date, subject : __mail[account][folder][message].subject, body : body.replace(/\n/g, '<br />\n')};

				var replace = function(phrase, match) { return variable[match]; }

				for(var i = 0; i < section.length; i++)
				{
					var list = __mail[account][folder][message][section[i]];
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
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.show', account : account, folder : folder, message : message}, null, $system.app.method(display, [account, folder, message]));
		}
	}
