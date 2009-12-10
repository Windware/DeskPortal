
	$self.folder = new function()
	{
		var _class = $id + '.folder';

		this.change = function(folder) //Change the displaying folder
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.text(folder)) return log.param();

			$self.item.get($system.node.id($id + '_account').value, folder); //Get the folder for current account
		}

		this.list = function(account) //List folders for an account
		{
			var log = $system.log.init(_class + '.list');
			if(!$system.is.digit(account)) return log.param();

			var select = $system.node.id($id + '_folder');
			select.innerHTML = '';

			if(!__account[account] || !__account[account].folder) return false;
			var folder = __account[account].folder;

			for(var i = 0; i < folder.length; i++)
			{
				var option = document.createElement('option');

				option.value = folder[i].name;
				$system.node.text(option, folder[i].name);

				select.appendChild(option);
			}
		}
	}
