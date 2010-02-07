
	$self.conf = new function()
	{
		var _class = $id + '.conf';

		var _name = {}; //Folder names

		var _max = 500; //Max characters allowed for the signature (NOTE : Translation files have the value hard coded)

		var _shown; //Selected folder for folder manipulation

		var _subscribed = {}; //Folder subscription

		var _default = function(xml, removed) //Default action against configuration saves
		{
			switch($system.dom.status(xml))
			{
				case '0' : $system.gui.alert($id, 'user/conf/success/title', 'user/conf/success/message', 3); break;

				default : return $system.gui.alert($id, 'user/conf/error/title', 'user/conf/error/message', 3) && false; break;
			}

			var form = {account : $system.node.id($id + '_conf_folder_form_account').account, folder : $system.node.id($id + '_conf_folder_form_adjust').source};
			var choice = {account : form.account.value, folder : form.folder.value}; //Remember the choices

			$self.conf._2_folder(); //Get account list
			form.account.value = choice.account;

			if(removed === true)
			{
				choice.folder = '0';

				form.folder.value = choice.folder;
				$self.conf.adjust(choice.folder); //Show selected folder
			}

			var run = function()
			{
				if(removed === true) return true;
				form.account.value = choice.account;

				form.folder.value = choice.folder;
				$self.conf.adjust(choice.folder); //Show selected folder
			}

			$self.conf.folder(choice.account, run); //Get folder list
			if(__selected.account == choice.account) $self.folder.get(choice.account, 0); //Update the folder listing on the main interface

			return true;
		}

		var _force = function() //Force the account to be default if no other account is set to be default
		{
			var form = $system.node.id($id + '_conf_form');
			form.base[0].disabled = form.base[1].disabled = false; //Enable the 'default' options

			var base = false;
			for(var id in __account) if(__account[id].base == '1') base = true; //Check if any account is set to be default

			if(base) return;

			form.base[0].checked = true; //If no accounts are set to be default, force the option to be default
			form.base[0].disabled = form.base[1].disabled = true;
		}

		this._1_account = function() //Update account listing
		{
			$self.account.get(true);
			_force();
		}

		this._2_folder = function() { $self.account.get(true); } //Folder tab action

		this.adjust = function(folder) //Set the folder adjust display
		{
			var log = $system.log.init(_class + '.adjust');
			if(!$system.is.digit(folder)) return log.param();

			var form = $system.node.id($id + '_conf_folder_form_adjust');
			var account = $system.node.id($id + '_conf_folder_form_account').account.value;

			$system.node.fade($id + '_conf_folder_list', folder == '0'); //Show or hide the area

			var disable = false; //See if the chosen folder is a special folder
			for(var special in __special) if(__special[special][account] == folder) disable = true;

			for(var i = 0; i < form.elements.length; i++) //Hide some options according to chosen folder
			{
				if($system.node.classes(form.elements[i], $id + '_conf_folder_custom')) form.elements[i].disabled = disable;
				else if($system.node.classes(form.elements[i], $id + '_conf_folder_inbox')) form.elements[i].disabled = __special.inbox[account] == folder;
			}

			var button = _subscribed[folder] == '1' ? 'displayed' : 'hidden';
			return $system.node.id($id + '_conf_folder_' + button).checked = true;
		}

		this.change = function(account) //Change to another account on the account edit screen
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(account)) return log.param();

			var form = $system.node.id($id + '_conf_form');

			for(var i = 0; i < form.elements.length; i++)
			{
				var value = __account[account] && __account[account][form.elements[i].name];

				switch(form.elements[i].type)
				{
					case 'submit' : case 'button' : case 'radio' : continue; break;

					case 'checkbox' : form.elements[i].checked = value == '1'; break;

					default : form.elements[i].value = value || ''; break;
				}
			}

			if(__account[account] && __account[account].preference == 'plain') $system.node.id($id + '_conf_prefer_plain').checked = true;
			else $system.node.id($id + '_conf_prefer_html').checked = true; //Check the read preference value

			form.base[0].disabled = form.base[1].disabled = false; //Enable the 'default' options
			$system.node.hide($id + '_conf_remove', account == 0);

			form.account.value = account;
			$self.conf.type(form.receive_type.value);

			if(account != '0' && __account[account])
			{
				if(__account[account].base == '1') form.base[0].disabled = form.base[1].disabled = true; //Disable changing the default account
				return form.base[1 - __account[account].base].checked = true; //Check the default account option
			}

			form.base[1].checked = true; //Do not set to be default first
			_force(); //If there is no default account, force the account as default

			$self.conf.port('receive');
			$self.conf.port('send');

			return true;
		}

		this.create = function(form) //Create a new folder
		{
			var log = $system.log.init(_class + '.create');

			var form = $system.node.id($id + '_conf_folder_form_create');
			var account = $system.node.id($id + '_conf_folder_form_account').account.value;

			var base = form.base.value;
			var name = form.create.value;

			if(!$system.is.digit(account) || !account) return false; //TODO - show some error
			if(!$system.is.digit(base) || !$system.is.text(name)) return false; //TODO - show some error

			form.create.value = '';

			var done = function(request) { _default(request.xml); }
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.create'}, {account : account, parent : base, name : name}, done);
		}

		this.folder = function(account, callback) //Pick the account for folder options
		{
			var log = $system.log.init(_class + '.folder');
			if(!$system.is.digit(account)) return log.param();

			if(_shown != account)
			{
				$system.node.fade($id + '_conf_folder_area', true); //Hide the options
				$system.node.fade($id + '_conf_folder_list', true); //Hide the folder options
			}

			if(account == '0') return $system.app.callback(_class + '.folder', callback);
			$system.node.hide($id + '_conf_folder_loading', false);

			var update = function(request)
			{
				if(account != _shown) return $system.app.callback(_class + '.folder', callback);

				$system.node.hide($id + '_conf_folder_loading', true);
				var form = {adjust : $system.node.id($id + '_conf_folder_form_adjust'), assign : $system.node.id($id + '_conf_folder_form_assign')};

				form.create = $system.node.id($id + '_conf_folder_form_create');
				form.account = $system.node.id($id + '_conf_folder_form_account');

				var language = $system.language.strings($id, 'conf.xml');

				var clone = {assign : $system.array.list('folder_drafts folder_sent folder_trash'), create : ['base'], adjust : ['source', 'move']};
				for(var i = 0; i < clone.assign.length; i++) form.assign[clone.assign[i]].innerHTML = '<option value="0">-----</option>';

				form.adjust.source.innerHTML = '<option value="0">-----</option>';
				form.adjust.move.innerHTML = form.create.base.innerHTML = '<option value="0">(' + language.top + ')</option>';

				var visible = [true]; //Parent folder visibility

				var construct = function(tree, depth, account) //Create the directory structure tree
				{
					if(!$system.is.object(tree) || !$system.is.digit(depth) || !$system.is.digit(account)) return false;

					var nodes = tree.childNodes;
					if(!nodes) return false;

					for(var i = 0; i < nodes.length; i++)
					{
						if(nodes[i].nodeType != 1 || nodes[i].nodeName != 'folder') continue; //Get the folder's node

						var name = $system.dom.attribute(nodes[i], 'name');
						var option = document.createElement('option'); //Create folder option

						option.value = $system.dom.attribute(nodes[i], 'id');
						$system.node.text(option, name);

						_name[option.value] = name; //Keep the name
						_subscribed[option.value] = $system.dom.attribute(nodes[i], 'subscribed'); //Remember the subscription status

						var special = false; //If this folder is special or not

						if(depth == 0) //On base folders
						{
							//Remember the name and the position of the special folder
						 	for(var j = 0; j < title.length; j++) if(__special[title[j]][account] == option.value) special = j;

							if(special !== false) //When it's a special folder
							{
								all[special] = [option];
								if(_subscribed[option.value]) limited[special] = [option];

								index = special;
							}
							else //If not special
							{
								all.push([option]);
								if(_subscribed[option.value]) limited.push([option]);

								index = all.length - 1;
							}
						}
						else
						{
							all[index].push(option); //For child folders, place them below each base folders

							var skip = false;
							for(var j = 0; j <= depth; j++) if(!visible[j]) skip = true;

							if(!skip && _subscribed[option.value]) limited[index].push(option); //If all parents and itself are subscribed, add the folder

							var spacer = '';
							for(var j = 0; j < depth; j++) spacer += '&nbsp;';

							if(spacer) option.innerHTML = spacer + option.innerHTML;
						}

						visible[depth + 1] = _subscribed[option.value] == '1'; //Keep the parent folder's subscribed status
						if(!construct(nodes[i], depth + 1, account)) return false; //Look through child folders
					}

					return true;
				}

				var index; //Folder counter

				var title = []; //Special folder name list
				for(var folder in __special) title.push(folder);

				var limited = []; //List of subscribed folders
				limited[title.length] = null; //Reserve the space in the array for special folders (So the next 'push' will be appended behind)

				var all = []; //List of all folders
				all[title.length] = null; //Reserve the space in the array for special folders (So the next 'push' will be appended behind)

				var section = $system.browser.engine == 'trident' ? 1 : 0; //IE counts first 'xml' tag as first node

				if(!request.xml || !construct(request.xml.childNodes[section], 0, account))
				{
					$system.app.callback(_class + '.folder', callback);
					return false; //TODO - Show error
				}

				for(var i = 0; i < all.length; i++) if($system.is.array(all[i])) for(var j = 0; j < all[i].length; j++) form.adjust.source.appendChild(all[i][j]);

				for(var i = 0; i < limited.length; i++) //Create the folder listing starting from the special folders
				{
					if(!$system.is.array(limited[i])) continue;

					for(var j = 0; j < limited[i].length; j++)
					{
						if(_subscribed[limited[i][j].value] != '1') continue;
						form.adjust.move.appendChild(limited[i][j].cloneNode(true));

						for(var k = 0; k < clone.assign.length; k++) form.assign[clone.assign[k]].appendChild(limited[i][j].cloneNode(true));
						for(var k = 0; k < clone.create.length; k++) form.create[clone.create[k]].appendChild(limited[i][j].cloneNode(true));
					}
				}

				for(var i = 1; i < title.length; i++) form.assign['folder_' + title[i]].value = __special[title[i]][account]; //Set the special custom folder selected

				$system.app.callback(_class + '.folder', callback);
				return $system.node.fade($id + '_conf_folder_area', false); //Show the options
			}

			_shown = account;
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.folder', account : account, update : 2, subscribed : 0}, null, update);
		}

		this.move = function() //Move a folder to another folder
		{
			var log = $system.log.init(_class + '.move');
			var form = $system.node.id($id + '_conf_folder_form_adjust');

			var folder = form.source.value;
			var target = form.move.value;

			if(!$system.is.digit(folder) || !$system.is.digit(target) || !folder) return false;

			var done = function(request) { _default(request.xml); }
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.move'}, {folder : folder, target : target}, done);
		}

		this.port = function(section) //Set the port number on the current choice
		{
			var log = $system.log.init(_class + '.port');
			if(!$system.array.find(['receive', 'send'], section)) return log.param();

			var form = $system.node.id($id + '_conf_form');

			if(section == 'receive')
			{
				if(form.receive_type.value == 'pop3') form[section + '_port'].value = !form[section + '_secure'].checked ? '110' : '995'; //POP port numbers
				else form[section + '_port'].value = !form[section + '_secure'].checked ? '143' : '993'; //IMAP port numbers
			}
			else form[section + '_port'].value = !form[section + '_secure'].checked ? '25' : '465'; //SMTP port numbers
		}

		this.remove = function() //Delete a folder
		{
			var log = $system.log.init(_class + '.rename');
			var language = $system.language.strings($id, 'conf.xml');

			var form = $system.node.id($id + '_conf_folder_form_adjust');
			var folder = form.source.value;

			if(!$system.is.digit(folder) || !folder || !_name[folder]) return false;
			if(!confirm(language.remove.replace('%%', _name[folder]))) return false;

			var done = function(folder, request)
			{
				if($system.dom.status(request.xml) == '0') $self.folder.clear(folder); //Clear folder caches
				_default(request.xml, true);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.remove'}, {folder : [folder]}, $system.app.method(done, [folder]));
		}

		this.rename = function() //Rename a folder
		{
			var log = $system.log.init(_class + '.rename');
			var form = $system.node.id($id + '_conf_folder_form_adjust');

			var folder = form.source.value;
			var name = form.rename.value;

			if(!$system.is.digit(folder) || !name.match(/\S/) || !folder) return false;
			form.rename.value = '';

			var done = function(request) { _default(request.xml); }
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.rename'}, {folder : folder, name : name}, done);
		}

		this.set = function(form) //Apply account configuration changes
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form')) return log.param();

			var type = form.receive_type.value;

			if(type == 'imap' || type == 'pop3') var required = $system.array.list('description name address receive_host receive_port send_host send_port');
			else var required = $system.array.list('description name address'); //List of option values required for special types (Hotmail, Gmail)

			var option = {}; //List of option values to send

			for(var i = 0; i < required.length; i++) //Check for required fields
			{
				if(form[required[i]].value != '') continue;

				$system.gui.alert($id, 'error', 'fill', 3, null, null, 'conf.xml'); //If any field is left blank that is required, notify it
				return false; //Avoid form submission
			}

			for(var i = 0; i < form.elements.length; i++)
			{
				var item = form.elements[i];

				switch(item.type)
				{
					case 'submit' : continue; break;

					case 'checkbox' : var value = item.checked ? '1' : '0'; break;

					case 'radio' :
						if(!item.checked) continue;
						var value = item.value;
					break;

					case 'textarea' :
						var value = item.value;

						if(value.length > _max) //Check on the signature length
						{
							$system.gui.alert($id, 'user/conf/signature/title', 'user/conf/signature/message', 5, null, [value.length]);
							return false;
						}
					break;

					default : var value = item.value; break;
				}

				option[item.name] = value;
			}

			var update = function(request)
			{
				$system.node.hide($id + '_conf_saving', true);

				switch($system.dom.status(request.xml))
				{
					case '0' : $system.gui.alert($id, 'user/conf/success/title', 'user/conf/success/message', 3); break;

					case '2' : return $system.gui.alert($id, 'user/conf/auth', 'user/conf/read', 5); break;

					case '3' : return $system.gui.alert($id, 'user/conf/auth', 'user/conf/send', 5); break;

					default : return $system.gui.alert($id, 'user/conf/error/title', 'user/conf/error/message', 5); break;
				}

				$self.conf.change(0); //Empty the fields
				$system.node.id($id + '_conf_form').account.value = '0';

				form.base[1].checked = true; //Do not set to be default first
				$self.account.get(false, null, _force); //Update account listing
			}

			$system.node.hide($id + '_conf_saving', false);
			$system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.set'}, option, update);

			return false; //Avoid form submission
		}

		this.special = function() //Set the special folders
		{
			var log = $system.log.init(_class + '.special');
			var form = $system.node.id($id + '_conf_folder_form_assign');

			var account = $system.node.id($id + '_conf_folder_form_account').account.value;
			if(!$system.is.digit(account) || !account) return log.param();

			var list = $system.array.list('folder_drafts folder_sent folder_trash');
			for(var i = 0; i < list.length; i++) if(!$system.is.digit(form[list[i]].value)) return log.param();

			var done = function(request) { if(_default(request.xml)) $self.account.get(false, request.xml); } //Update the account information

			var value = {account : account, drafts : form.folder_drafts.value, sent : form.folder_sent.value, trash : form.folder_trash.value};
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.special'}, value, done);
		}

		this.subscribe = function() //Subscribe or unsubscribe to a folder
		{
			var log = $system.log.init(_class + '.subscribe');
			var form = $system.node.id($id + '_conf_folder_form_adjust');

			var folder = form.source.value;
			var mode;

			for(var i = 0; i < form.subscribed.length; i++) if(form.subscribed[i].checked) mode = form.subscribed[i].value;
			if(!$system.is.digit(folder) || !$system.is.digit(mode) || !folder) return false;

			var done = function(request) { _default(request.xml); }
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.subscribe'}, {folder : folder, mode : mode}, done);
		}

		this.terminate = function() //Terminates an account
		{
			var language = $system.language.strings($id, 'conf.xml');
			var account = $system.node.id($id + '_conf_form').account.value;

			if(!__account[account]) return false;

			var warning = __account[account].type == 'pop3' ? language.pop3 : '';
			if(!confirm(language.terminate.replace('%%', __account[account].description) + '\n' + warning)) return false;

			return $self.account.remove(account);
		}

		this.type = function(type) //Alter values and mail keep option by specified receive type
		{
			var log = $system.log.init(_class + '.type');
			var nodes = $system.node.id($id + '_conf_account').childNodes;

			for(var i = 0; i < nodes.length; i++) //Hide redundant fields for certain account types
			{
				if(nodes[i].nodeType != 1 || nodes[i].nodeName != 'TR') continue;

				if($system.node.classes(nodes[i], $id + '_conf_detail')) $system.node.hide(nodes[i], !$system.array.find(['pop3', 'imap'], type));
				else if($system.node.classes(nodes[i], $id + '_conf_pop3')) $system.node.hide(nodes[i], type != 'pop3');
			}

			$self.conf.port('receive'); //Update the port number
		}
	}
