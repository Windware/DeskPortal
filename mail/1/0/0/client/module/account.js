
	$self.account = new function()
	{
		var _class = $id + '.account';

		this.change = function(account) //Change the displayed account
		{
			var log = $system.log.init(_class + '.change');

			if(!$system.is.digit(account))
			{
			}

			$self.item.get(account, 'INBOX'); //Get mails for default mail box

			$self.folder.list(account); //List the folders
			$system.node.id($id + '_folder').value = 'INBOX';
		}

		this.get = function(callback) //Get list of accounts
		{
			var log = $system.log.init(_class + '.folder');

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
				var section = ['name', 'count', 'recent']; //Folder parameters
				var accounts = $system.dom.tags(request.xml, 'account');

				for(var i = 0; i < accounts.length; i++) //Create the account selection and store the information
				{
					var option = document.createElement('option');
					option.value = $system.dom.attribute(accounts[i], 'id');

					var description = $system.dom.attribute(accounts[i], 'description');
					$system.node.text(option, description);

					select.appendChild(option);
					__account[option.value] = {description : description, folder : []}; //Keep the account information

					var folder = $system.dom.tags(accounts[i], 'folder');

					for(var j = 0; j < folder.length; j++)
					{
						var categorized = {};
						for(var k = 0; k < section.length; k++) categorized[section[k]] = $system.dom.attribute(folder[j], section[k]);

						__account[option.value].folder.push(categorized); //Folder information
					}
				}

				select.value = index;
				if(typeof callback == 'function') callback();
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'account.get'}, null, list);
		}
	}
