
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _count = 0; //Mail numbering

		this.get = function(account, folder, callback) //Get list of mails
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(account) || !$system.is.text(folder)) return log.param();

			var list = function(request) //List the mails upon receiving the contents
			{
				var language = $system.language.strings($id);
				var section = $system.array.list('subject from date'); //List of columns to display

				var table = $system.node.id($id + '_read_mail'); //Display table
				while(table.firstChild) table.removeChild(table.firstChild); //Clean up the field (innerHTML on table breaks khtml)

				var row = document.createElement('tr');
				row.id = $id + '_read_header';

				for(var i = 0; i < section.length; i++) //Set headers
				{
					var header = document.createElement('th');
					header.onclick = $system.app.method($self.gui.sort, [section[i]]);

					$system.node.text(header, language[section[i]]);
					row.appendChild(header);
				}

				table.appendChild(row);
				__folder[account] = {}; //Mail list cache

				var folder = $system.dom.tags(request.xml, 'folder');

				for(var i = 0; i < folder.length; i++) //For all the folders given
				{
					var name = $system.dom.attribute(folder[i], 'name'); //Folder name
					__folder[account][name] = []; //Mail cache storage for the folder

					var mail = $system.dom.tags(folder[i], 'mail');

					for(var j = 0; j < mail.length; j++)
					{
						var id = $system.dom.attribute(mail[j], 'message_id');

						__mail[id] = {}; //Mail information
						__local[id] = ++_count; //Give a local numbering to each mail

						var row = document.createElement('tr');
						row.style.cursor = 'pointer';

						$system.node.hover(row, $id + '_hover'); //Give mouse hovered style
						row.onclick = $system.app.method($self.item.show, [id]); //Display the message pane on click

						__mail[id].body = $system.text.escape($system.dom.text($system.dom.tags(mail[j], 'body')[0])); //Grab the body message

						var body = __mail[id].body.replace(/^((.*\n){1,5})(.|\n)+$/, '$1'); //Limit the message tip to first 5 lines
						if(RegExp.$3) body += '...'; //Note the body is cropped

						if(!body.match(/\S/)) body = '(' + language.empty + ')';
						$system.tip.set(row, $system.info.id, 'blank', [body], true); //Show the body with a tooltip

						__folder[account][name].push(id);

						for(var k = 0; k < mail[j].attributes.length; k++) //Keep mail attributes
						{
							var parameter = mail[j].attributes[k];
							__mail[id][parameter.name] = parameter.value;
						}

						if(__mail[id].recent || __mail[id].unseen) $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
						if(__mail[id].flagged) $system.node.classes(row, $id + '_mail_marked', true); //For marked mails

						for(var k = 0; k < section.length; k++) //For all the columns
						{
							var display = ''; //Parameter to display

							switch(section[k]) //Pick the parameters to display on the interface
							{
								case 'action' :
									var cell = document.createElement('td');
									cell.appendChild(action.cloneNode(true));

									row.appendChild(cell); //Give action box
									continue;
								break;

								case 'subject' : display = __mail[id][section[k]]; break;

								case 'date' : display = $system.date.create(Date.parse(__mail[id].date) / 1000).format($global.user.pref.format.monthdate); break;

								case 'from' : case 'to' : //Create mail addresses and concatenate
									display = [];
									var address = $system.dom.tags(mail[j], section[k]);

									for(var l = 0; l < address.length; l++)
									{
										var show = $system.dom.attribute(address[l], 'personal');
										var real = $system.dom.attribute(address[l], 'mailbox') + '@' + $system.dom.attribute(address[l], 'host');

										if(show) show = '<span style="cursor : help"' + $system.tip.link($system.info.id, null, 'blank', [real]) + '>' + show + '</span>';
										else show = real;

										display.push(show);
									}

									if(section[k] == 'to')
									{
										var address = $system.dom.tags(mail[j], 'cc');

										for(var l = 0; l < address.length; l++)
										{
											var show = $system.dom.attribute(address[l], 'personal');
											var real = $system.dom.attribute(address[l], 'mailbox') + '@' + $system.dom.attribute(address[l], 'host') + ' (cc)';

											if(show) show = '<span style="cursor : help"' + $system.tip.link($system.info.id, null, 'blank', [real]) + '>' + show + '</span>';
											else show = real;

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

		this.list = function(account, folder) //List items of a specified folder
		{
		}

		this.show = function(message) //Display the mail window
		{
			var log = $system.log.init(_class + '.show');
			if(!$system.is.text(message)) return log.param();

			var index = __local[message];
			if(!$system.is.digit(index)) return false;

			var id = $id + '_mail_' + index;

			if($system.node.id(id))
			{
				if($system.node.hidden(id)) $system.window.raise(id);
				return $system.node.fade(id);
			}

			var value = {index : index, subject : __mail[message].subject, body : __mail[message].body.replace(/\n/g, '<br />\n')};

			var replace = function(phrase, match) { return value[match]; }
			var body = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

			$system.window.create(id, $self.info.title, body, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, true, true, true, null, null, true);
		}
	}
