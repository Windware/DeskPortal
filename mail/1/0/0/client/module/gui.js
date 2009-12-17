
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		this.add = function(address, name) //Add email address to address book
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
				form.mail.value = address;
			}

			if($system.node.hidden(app))
			{
				$system.node.fade(app);
				$system.window.raise(app);
			}

			$system.app.load(app, $system.app.method(open, [address, name])); //Load the address book app
		}

		this.filter = function() //Filters the list of mails
		{
		}

		this.sort = function(section) //Sort the columns
		{
		}
	}
