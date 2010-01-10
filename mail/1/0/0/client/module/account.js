
	$self.account = new function()
	{
		var _class = $id + '.account';

		var _interval = 280000; //Interval is set to below 5 minutes to make POP/IMAP before SMTP work for servers having valid window of 5 minutes

		this.change = function(account) //Change the displayed account
		{
			var log = $system.log.init(_class + '.change');

			if(!$system.is.element(account)) return log.param();
			if(!$system.is.digit(account.value)) return false;

			__selected.account = account.value;
			if($system.browser.engine == 'trident') document.body.focus(); //Let focus off the selection to allow mouse wheel use on other parts after selection

			var list = function(value) { if($system.is.digit(__inbox[value])) $self.folder.change(__inbox[value]); } //Get mails for the default mail box
			$self.folder.get(account.value, !!__active[account], $system.app.method(list, [account.value])); //List the folders

			if(__active[account]) return true;
			var update = $system.app.method($self.folder.get, [account.value, true]);

			__active[account] = setInterval(update, _interval); //Get folders updated periodically
			update();
		}

		this.get = function(callback) //Get list of accounts
		{
			var select = $system.node.id($id + '_account');
			var index = select.value || ''; //Keep the current value

			select.innerHTML = ''; //Clean up the entries

			var language = $system.language.strings($id);
			var defaults = [{key : '', value : '-----'}];//, {key : '0', value : language.all}];

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

				var accounts = $system.dom.tags(request.xml, 'account');

				for(var i = 0; i < accounts.length; i++) //Create the account selection and store the information
				{
					var option = document.createElement('option');
					option.value = $system.dom.attribute(accounts[i], 'id');

					var description = $system.dom.attribute(accounts[i], 'description');
					$system.node.text(option, description);

					select.appendChild(option);

					__box[option.value] = {}; //Remember special folder names
					for(var j = 0; j < __special.length; j++) __box[option.value][__special[j]] = $system.dom.attribute(accounts[j], 'folder_' + __special[j]);
				}

				select.value = index;
				$system.app.callback(_class + '.get.list', callback);
			}

			$self.gui.indicator(true); //Show indicator
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.get'}, null, list);
		}
	}
