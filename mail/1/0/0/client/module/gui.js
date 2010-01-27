
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _limit = 50; //Maximum search string length

		var _order = {}; //Mail order for specific mail windows

		var _process = 0; //Amount of process to count to show the indicator

		var _quote = '> '; //Quote marker to use in reply

		var _submit = {}; //Remember mail send form submission

		var _body = function(id, index, compose, field) //Create the mail window content
		{
			if(!$system.is.digit(id)) id = 0;
			if(!id && !compose || !$system.is.digit(index)) return false;

			var language = $system.language.strings($id);
			var section = ['from', 'to', 'cc'];

			if(!compose) //For displaying the mail
			{
				var template = 'mail'; //The HTML template to use
				var value = {id : id, index : index, account : __mail[id].account, sent : $system.date.create(__mail[id].sent).format($global.user.pref.format.full), subject : __mail[id].subject || '(' + language.empty + ')'};

				if(__mail[id].marked == '1') value.marked = ' checked="checked"';
				else value.unmarked = ' checked="checked"';

				var replace = function(phrase, match) { return variable[match] || ''; }

				for(var i = 0; i < section.length; i++)
				{
					var list = __mail[id][section[i]];
					if(!$system.is.array(list)) continue;

					var address = [];

					for(var j = 0; j < list.length; j++)
					{
						var variable = {address : $system.text.escape(list[j].address), show : $system.text.escape(list[j].name ? list[j].name : list[j].address), name : list[j].name ? $system.text.escape(list[j].name) : ''};
						variable.tip = $system.tip.link($system.info.id, null, 'blank', [$system.text.escape(list[j].address)]);

						address.push($self.info.template.address.replace(/%value:(.+?)%/g, replace));
					}

					value[section[i]] = address.join(', ');
				}

				var attachment = [];

				for(var i = 0; i < __mail[id].attachment.length; i++)
				{
					var text = $system.text.template('<a class="%id%_mail_attachment" href="%%"%%>%%</a> (%%KB)', $id);
					var address = $system.network.form($self.info.root + 'server/php/front.php?task=gui._body&id=' + __mail[id].attachment[i].id);

					attachment.push($system.text.format(text, [address, $system.tip.link($id, null, 'attachment'), __mail[id].attachment[i].name, Math.ceil(__mail[id].attachment[i].size / 1000)]));
				}

				value.attachment = attachment.join(', ');
				value.body = $system.network.form($self.info.root + 'server/php/front.php?task=gui.show&message=' + id);
			}
			else //For the composing screen
			{
				var template = 'compose'; //The HTML template to use
				var value = {id : id, index : index, action : $system.network.form($self.info.root + 'server/php/front.php?task=gui.send')};

				if(id) //Get values from the mail if specified
				{
					var account = __mail[id].account; //The account the mail belongs to

					if($system.is.array(field) && field.length) //If the reply to address field is specified
					{
						value.subject = 'Re : ' + __mail[id].subject.replace(/^\s*re\s*:\s*/i, ''); //Strip previous reply marker

						value.to = [];
						var section = ['from', 'to', 'cc'];

						for(var i = 0; i < field.length; i++) //Grab the mail addresses from the specified fields
						{
							if(!$system.array.find(section, field[i]) || !__mail[id][field[i]]) continue;
							var address = __mail[id][field[i]];

							for(var j = 0; j < address.length; j++)
							{
								if(address[j].name.length && address[j].name != address[j].address) value.to.push(address[j].name + ' <' + address[j].address + '>');
								else value.to.push(address[j].address); //Put the user name in front if it exists and is not same as the mail address
							}
						}

						value.to = value.to.join(', ');
					}
					else value.subject = 'Fw : ' + __mail[id].subject; //If forwarding, put the forward prefix
				}
				else var account = __selected.account ? __selected.account : 1; //FIXME - Pick the default account

				for(var i in __account)
				{
					var selection = i == account ? ' selected="selected"' : '';
					value.account += $system.text.format('<option value="%%"%%>%% : %% (%%)</option>', [i, selection, __account[i].description, __account[i].name, __account[i].address]);
				}
			}

			var replace = function(phrase, match) { return value[match] || value[match] == 0 ? value[match] : ''; }
			return $self.info.template[template].replace(/%value:(.+?)%/g, replace);
		}

		this.add = function(address, name) //Add email address to address book app
		{
			var log = $system.log.init(_class + '.add');
			if(!$system.is.text(address) || !$system.is.text(name, true)) return log.param();

			//TODO - It cannot open the address book version the user uses, since it may have different API and node names,
			//which may open a different address book app on top of an already displayed version
			var app = 'addressbook_1_0_0'; //The address book version this function supports

			var open = function(address, name)
			{
				var pane = $system.node.id(app + '_edit_0'); //New address window
				if(!pane || $system.node.hidden(pane)) $global.top[app].item.edit(); //If not loaded or visible, bring it to the front

				$system.window.raise(app + '_edit_0'); //Load it to the front
				var form = $system.node.id(app + '_form_edit');

				if(name) form.name.value = name; //Load the values in the edit form
				form.mail_main.value = address;
			}

			if($system.node.hidden(app))
			{
				$system.node.fade(app);
				$system.window.raise(app);
			}

			$system.app.load(app, $system.app.method(open, [address, name])); //Load the address book app
		}

		this.auto = function() //Automatically save the draft
		{
		}

		this.check = function() //Select or deselect all mails
		{
			var form = $system.node.id($id + '_mail_list');
			var state = false; //Wether to check or uncheck all checkboxes

			for(var i = 0; i < form.elements.length; i++)
			{
				if(form.elements[i].checked) continue;

				state = true; //If any of it is unchecked, try to check them all
				break;
			}

			for(var i = 0; i < form.elements.length; i++) //Pick the mail ID and check the row
			{
				var id = form.elements[i].id.replace(RegExp('^' + $id + '_mail_(\\d+)_check$'), '$1');
				$self.item.select(id, state);
			}

			return true;
		}

		this.complete = function(index, frame) //Called when the 'iframe' loads after sending the mail
		{
			var log = $system.log.init(_class + '.complete');

			if(!$system.is.digit(index) || !frame.contentWindow) return log.param();
			if(!$system.is.digit(_submit[index])) return false; //Quit if never submitted

			var state = frame.contentWindow.document.body.innerHTML;
			if(!$system.is.digit(state)) state = 1;

			var mode = $system.array.list('success error big smtp imap');
			mode = mode[state]; //Find out the status

			if(!mode) mode = 'error';
			var timer;

			if(mode == 'success') //When succeeded
			{
				timer = 3; //Clear the message
				$system.window.fade($id + '_display_' + index, true, null, true); //Remove the composing window

				if(__special.sent[_submit[index]] == __selected.folder) $self.item.update(1); //Update the sent folder
				else __update[__special.sent[_submit[index]]] = true; //Or mark the sent folder to be updated on next access
			}
			else
			{
				$system.node.hide($id + '_compose_' + index + '_sending', true); //Hide the send message

				var form = $system.node.id($id + '_compose_' + index + '_form');
				for(var i = 0; i < form.elements.length; i++) form.elements[i].disabled = false; //Enable the forms again
			}

			return $system.gui.alert($id, 'user/gui/send/' + mode, 'user/gui/send/' + mode + '/message', timer); //Show the status
		}

		this.compose = function(id, index, field, address) //Compose a mail
		{
			if(!$system.is.digit(index)) return $self.gui.show(id, true); //Create the mail window to compose if no window is specified
			if(!$system.is.digit(id) || !id) return false;

			var node = $system.window.list[$id + '_display_' + index]; //Window object
			if(!node) return false;

			node.body.innerHTML = _body(id, index, true, field);

			if(id) //If a mail is specified, put the quotes in
			{
				var insert = function(id, index, request)
				{
					var form = $system.node.id($id + '_compose_' + index + '_form');
					if(!form) return false;

					var sender = __mail[id].from[0]; //Set the sender name
					sender = sender.name.length ? sender.name + ' (' + sender.address + ')' : sender.address;

					var language = $system.language.strings($id);

					var source = _quote + language.from + ' : ' + sender + '\n' + _quote + language.date + ' : ' + __mail[id].sent;
					form.body.innerHTML = '\n\n' + source + '\n' + _quote + '\n' + _quote + request.text.replace(/\n/g, '\n' + _quote); //Fill the composing field with the mail quoted
				}

				//Load the plain text version of the mail
				$system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.compose', id : id}, null, $system.app.method(insert, [id, index]));
			}

			return true;
		}

		this.create = function(account, subject, to, cc, bcc) //Create a new mail
		{
			var index = $self.gui.show(null, true);
			if(!index) return false;

			var form = $system.node.id($id + '_compose_' + index + '_form');

			if($system.is.digit(account) && account) form.account.value = account;
			if($system.is.text(subject)) form.subject.value = account; //Set values

			var target = {to : to, cc : cc, bcc : bcc};

			for(var section in target) //Set the address fields
			{
				if(!$system.is.array(target[section])) continue;
				var address = [];

				for(var i = 0; i < target[section].length; i++) address.push(target[section][i]);
				form[section].value = address.join(', ');
			}
		}

		this.file = function(index) //Load a file for attachment
		{
		}

		this.filter = function(section, value) //Filters the list of mails
		{
			var log = $system.log.init(_class + '.filter');

			switch(section)
			{
				case 'marked' : case 'unread' : if(typeof value != 'boolean') return log.param(); break;

				default : return log.dev($global.log.error, 'dev/gui/filter', 'dev/gui/filter/solution'); break;
			}

			__filter[section] = value;
			$self.item.update(); //Update the listing

			return false; //Avoid form submission
		}

		this.format = function(node) //Turn links and mail addresses clickable
		{
			var log = $system.log.init(_class + '.format');
			if(!$system.is.element(node)) return log.param();

			node.innerHTML = $system.text.link($system.text.mail(node.innerHTML));
		}

		this.indicator = function(on) //Manage indicator to show progress
		{
			_process += on ? 1 : -1;
			if(_process < 0) _process = 0;

			return $system.node.id($id + '_indicator').style.visibility = _process ? '' : 'hidden';
		}

		this.load = function(id, index, frame) //Load the inline images on HTML messages
		{
			var log = $system.log.init(_class + '.load');
			if(!$system.is.digit(id) || !$system.is.digit(index) || !$system.is.element(frame, 'iframe')) return log.param();

			var indicator = $system.node.id($id + '_mail_' + index + '_loading');
			if(!__mail[id] || !$system.is.element(indicator)) return false;

			indicator.style.visibility = 'hidden'; //Remove the loading indicator
			$self.folder.get(__mail[id].account, 1); //Update the unread counts in the folder list

			var image = frame.contentWindow.document.getElementsByTagName('img');

			for(var i = 0; i < image.length; i++)
			{
				var source = $system.network.form($self.info.root + 'server/php/front.php?task=gui.load&id=' + id + '&cid=$1');
				image[i].src = image[i].src.replace(/^cid:(.+)$/, source); //Set the embedded image target
			}
		}

		this.next = function(id, index, distance) //Show next or previous mail
		{
			var log = $system.log.init(_class + '.next');
			if(!$system.is.digit(id) || !$system.is.digit(index) || !$system.is.digit(distance, true)) return log.param();

			if(!_order[index]) return false; //If no order information is found, quit

			var node = $system.window.list[$id + '_display_' + index]; //Window object
			if(!node) return false;

			for(var i = 0; i < _order[index].length; i++)
			{
				if(id != _order[index][i]) continue; //Find the current mail ID in the mail list

				var target = i; //Current mail position
				do { target += distance; } while(target >= 0 && target < _order[index].length && !__mail[_order[index][target]])  //Find the next or previous mail until it finds one

				break;
			}

			if(!__mail[_order[index][target]]) return true; //If no mail is found, quit
			return node.body.innerHTML = _body(_order[index][target], index);
		}

		this.save = function() //Save the mail as a draft
		{
		}

		this.send = function(index, id) //Send the mail
		{
			var language = $system.language.strings($id);

			var form = $system.node.id($id + '_compose_' + index + '_form');
			if(!$system.is.element(form, 'form')) return false;

			var section = ['to', 'cc', 'bcc'];
			var warn = {}; //The field with invalid parameters

			if(!$system.is.digit(form.account.value) || !form.account.value) warn.account = true; //Check account selection
			var exist = false; //Whether an address is specified or not

			for(var i = 0; i < section.length; i++) //Check on address fields
			{
				if(!form[section[i]].value.length) continue;
				var address = form[section[i]].value.split(/\s*,\s*/);

				for(var j = 0; j < address.length; j++)
				{
					if(!address[j].match(/.@./)) warn[section[i]] = true;
					else exist = true;
				}
			}

			if(!exist) warn.to = true; //If no address is specified, warn on the 'to' field

			for(var i = 0; i < form.elements.length; i++) $system.node.classes(form.elements[i], $id + '_form_warn', !!warn[form.elements[i].name]);
			for(var field in warn) return false; //If invalid fields exist, quit

			_submit[index] = form.account.value; //Remember the form has been submitted
			if(!confirm(language.confirm)) return false;

			$system.node.hide($id + '_compose_' + index + '_sending', false); //Show the send message

			form.submit(); //Submit the form directly inside an 'iframe' to have file uploading work
			for(var i = 0; i < form.elements.length; i++) form.elements[i].disabled = true; //Disable the form for multiple submission

			return true;
		}

		this.show = function(id, compose) //Display the mail window
		{
			var log = $system.log.init(_class + '.show');

			if(!$system.is.digit(id))
			{
				if(!compose) return log.param();

				id = null;
				__window++;
			}
			else
			{
				if(!__mail[id]) return log.user($global.log.warning, 'user/show/error', 'user/show/solution');

				$system.node.classes($id + '_mail_row_' + id, $id + '_mail_unread', false); //Remove the unread style
				__mail[id].read = '1';

				_order[++__window] = __current.list; //Remember the list order for this window
			}

			$system.window.create($id + '_display_' + __window, $self.info.title + ' [No. ' + (id || 0) + ']', _body(id, __window, compose), 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);
			return __window; //Return the window ID
		}

		this.sort = function(section) //Sort the columns
		{
			var log = $system.log.init(_class + '.sort');
			if(!$system.is.text(section)) return log.param();

			__order = {item : section, reverse : __order.item == section && !__order.reverse}; //Set order option
			var header = $system.array.list('from to cc');

			$self.item.update(); //Update the listing

			for(var i = 0; i < header.length; i++)
			{
				var sign = $system.node.id($id + '_sign_' + header[i]);
				if(!$system.is.element(sign)) continue;

				if(section != header[i]) sign.innerHTML = '';
				else sign.innerHTML = !__order.reverse ? ' &uarr;' : ' &darr;';
			}
		}
	}
