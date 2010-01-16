
	$self.account = new function()
	{
		var _class = $id + '.account';

		var _cache; //Account list cache

		var _interval = 280000; //Interval is set to below 5 minutes to make POP/IMAP before SMTP work for servers having valid window of 5 minutes

		this.change = function(account) //Change the displayed account
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(account)) return false;

			__selected.account = account;
			if($system.browser.engine == 'trident') document.body.focus(); //Let focus off the selection to allow mouse wheel use on other parts after selection

			if(account == '0') return $self.folder.get(account);

			var list = function(value) { if($system.is.digit(__special.inbox[value])) $self.folder.change(__special.inbox[value]); } //Get mails for the default mail box
			$self.folder.get(account, !!__active[account], $system.app.method(list, [account])); //List the folders

			if(__active[account] || __account[account].type == 'pop3') return true;
			var update = $system.app.method($self.folder.get, [account, true]);

			__active[account] = setInterval(update, _interval); //Get folders updated periodically for IMAP
			update();
		}

		this.get = function(cache, callback) //Get list of accounts
		{
			var select = $system.node.id($id + '_account');
			var index = select.value || ''; //Keep the current value

			select.innerHTML = ''; //Clean up the entries

			var language = $system.language.strings($id);
			var defaults = [{key : '0', value : '-----'}];//, {key : '0', value : language.all}];

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
				_cache = request.xml || request;

				var accounts = $system.dom.tags(_cache, 'account');
				var conf = {account : $system.node.id($id + '_conf_form'), folder : $system.node.id($id + '_conf_folder_form_account')}; //Configuration panel forms

				for(var area in conf)
				{
					if(!conf[area]) continue; //If configuration pane is loaded
					conf[area] = conf[area].account; //Account configuration selection box

					var start = area == 'account' ? '(' + language['new'] + ')' : '-----';
					conf[area].innerHTML = '<option value="0">' + start + '</option>';
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
				}

				select.value = index;
				$system.app.callback(_class + '.get.list', callback);
			}

			if(cache === true && _cache) return list(_cache);

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.get'}, null, list);
		}
	}
