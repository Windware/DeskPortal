
	$self.account = new function()
	{
		var _class = $id + '.account';

		var _list; //Account list cache

		this.change = function(account, folder, callback) //Change the displayed account
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(account)) return log.param();

			$system.node.id($id + '_account').value = __selected.account = account;
			if($system.browser.engine == 'trident') document.body.focus(); //Let focus off the selection to allow mouse wheel use on other parts after selection

			__selected.account = account;
			if(account == '0') return $self.folder.get(account, undefined, callback);

			var list = function(account, folder, callback) //Get mails for the specified folder
			{
				folder = $system.is.digit(folder) ? folder : __special.inbox[account];

				if($system.is.digit(folder)) $self.folder.change(folder, callback);
				else $system.app.callback(log.origin, callback);
			}

			return $self.folder.get(account, false, $system.app.method(list, [account, folder, callback])); //List the folders
		}

		this.get = function(cache, list, callback) //Get list of accounts
		{
			var select = $system.node.id($id + '_account');
			var index = select.value || '0'; //Keep the current value

			select.innerHTML = ''; //Clean up the entries

			var language = $system.language.strings($id);
			var defaults = [{key : '0', value : '-----'}];

			for(var i = 0; i < defaults.length; i++) //Create the default options
			{
				var option = document.createElement('option');

				option.value = defaults[i].key;
				$system.node.text(option, defaults[i].value);

				select.appendChild(option);
			}

			var list = function(request)
			{
				$self.gui.indicator(false); //Hide indicator
				_list = $system.is.object(request.xml) && request.xml || request;

				var accounts = $system.dom.tags(_list, 'account');
				var conf = {account : $system.node.id($id + '_conf_form'), folder : $system.node.id($id + '_conf_folder_form_account')}; //Configuration panel forms

				var choice = {};

				for(var area in conf)
				{
					if(!conf[area]) continue; //If configuration pane is loaded

					conf[area] = conf[area].account; //The account selection box
					choice[area] = conf[area].value; //Keep the selected value

					conf[area].innerHTML = '';
					var option = document.createElement('option'); //Create the default selection

					option.value = 0;
					$system.node.text(option, area == 'account' ? '(' + language['new'] + ')' : '-----');

					conf[area].appendChild(option); //NOTE : IE6 failed to set the tag properly with innerHTML
				}

				var selection = [];

				for(var i = 1; i <= __window; i++) //For all of the composing windows
				{
					var form = $system.node.id($id + '_compose_' + i + '_form');
					if(!$system.is.element(form, 'form')) continue;

					selection[i] = form.account.value; //Keep the selection
					form.account.innerHTML = '';
				}

				for(var i = 0; i < accounts.length; i++) //Create the account selection and store the information
				{
					var option = document.createElement('option');
					option.value = $system.dom.attribute(accounts[i], 'id');

					var description = $system.dom.attribute(accounts[i], 'description');
					$system.node.text(option, description);

					select.appendChild(option);
					for(var area in conf) if(conf[area]) conf[area].appendChild(option.cloneNode(true));

					for(var folder in __special) __special[folder][option.value] = $system.dom.attribute(accounts[i], 'folder_' + folder);
					var info = {};

					for(var j = 0; j < accounts[i].attributes.length; j++)
					{
						var point = accounts[i].attributes[j];
						info[point.nodeName] = point.nodeValue;
					}

					info.signature = info.signature.replace(/\\n/g, "\n");
					__account[info.id] = info;

					for(var j = 1; j <= __window; j++) //For all of the composing windows
					{
						if(!$system.window.list[$id + '_display_' + j]) continue; //Check for window existence

						var form = $system.node.id($id + '_compose_' + j + '_form');
						if(!$system.is.element(form, 'form')) continue;
						
						var node = option.cloneNode(true);
						node.appendChild(document.createTextNode(' : ' + info.name + ' (' + info.address + ')'));

						form.account.appendChild(node);
						if(selection[j] == option.value) node.selected = true;
					}

					if(info.base == '1') __default = info.id; //Remember the default account
				}

				for(var area in conf) if(conf[area]) conf[area].value = choice[area];

				select.value = index;
				$system.app.callback(_class + '.get.list', callback);
			}

			if(cache === true && _list) return list(_list);

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.get'}, null, list);
		}

		this.remove = function(id) //Remove an account
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(id)) return log.param();

			var notify = function(id, request)
			{
				switch($system.dom.status(request.xml))
				{
					case '0' :
						$self.account.get(false, null, $system.app.method($self.conf.change, [0])); //Update account lists

						$system.gui.alert($id, 'user/conf/remove/title', 'user/conf/remove/message');
						if(__selected.account == id) $self.account.change(0); //Reset account choice if the displayed account is removed

						for(var folder in __belong)
						{
							if(__belong[folder] != id) continue;

							$self.folder.clear(folder); //Clear mail caches
							delete __belong[folder]; //Delete which account this folder belongs to

							if(__timer[__belong[folder]]) clearInterval(__timer[folder]);
							delete __timer[folder]; //Remove the periodic folder listing timer

							if(__refresh[folder]) clearInterval(__refresh[folder]);
							delete __refresh[folder]; //Remove the periodic item listing timer

							var section = $system.array.list('inbox drafts sent trash'); //Invalidate the special folder ID if set to this folder
							for(var i = 0; i < section.length; i++) for(var account in __special[section]) if(__special[section][account] == folder) __special[section][account] == null;
						}

						delete __account[id]; //Remove account information
						$self.conf.folder(0);
					break;

					default : $system.gui.alert($id, 'user/conf/error/title', 'user/conf/error/remove'); break;
				}
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.remove'}, {id : id}, $system.app.method(notify, [id]));
		}
	}
