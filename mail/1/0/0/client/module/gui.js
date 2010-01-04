
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _limit = 50; //Maximum search string length

		var _process = 0; //Amount of process to count to show the indicator

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
				form.mail.value = address;
			}

			if($system.node.hidden(app))
			{
				$system.node.fade(app);
				$system.window.raise(app);
			}

			$system.app.load(app, $system.app.method(open, [address, name])); //Load the address book app
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

		this.indicator = function(on) //Manage indicator to show progress
		{
			_process += on ? 1 : -1;
			if(_process < 0) _process = 0;

			return $system.node.hide($system.node.id($id + '_indicator'), !_process);
		}

		this.sort = function(section) //Sort the columns
		{
			var log = $system.log.init(_class + '.filter');
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
