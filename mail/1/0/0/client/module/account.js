
	$self.account = new function()
	{
		var _class = $id + '.account';

		this.change = function(account) //Change the displayed account
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.digit(account)) return log.param();

			var list = function() { if(__folder[account].INBOX) $self.folder.change(__folder[account].INBOX.id); } //Get mails for the default mail box
			$self.folder.get(account, list); //List the folders
		}

		this.get = function(callback) //Get list of accounts
		{
			var select = $system.node.id($id + '_account');
			var index = select.value; //Keep the current value

			select.innerHTML = ''; //Clean up the entries

			var language = $system.language.strings($id);
			var defaults = [{key : '', value : '-----'}, {key : '0', value : language.all}];

			for(var i = 0; i < defaults.length; i++) //Create the default options
			{
				var option = document.createElement('option');

				option.value = defaults[i].key;
				$system.node.text(option, defaults[i].value);

				select.appendChild(option);
			}

			var list = function(request)
			{
				var accounts = $system.dom.tags(request.xml, 'account');

				for(var i = 0; i < accounts.length; i++) //Create the account selection and store the information
				{
					var option = document.createElement('option');
					option.value = $system.dom.attribute(accounts[i], 'id');

					var description = $system.dom.attribute(accounts[i], 'description');
					$system.node.text(option, description);

					select.appendChild(option);
					__account[option.value] = description;
				}

				select.value = index;
				$system.app.callback(_class + '.get.list', callback);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.get'}, null, list);
		}
	}
