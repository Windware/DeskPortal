
	$self.item = new function()
	{
		var _view;

		this.addressbook = function() //Opens up the address book to get address from
		{
		}

		this.account = function() //Opens up the address book to get address from
		{
		}

		this.create = function(address) //Start composing a mail on the address (FIXME : Function name used in addressbook app)
		{
		}

		this.display = function(section) //Display a pane
		{
			return;
			if(section == _view) return;
			if(_view) $system.node.fade($id + '_pane_' + _view, true); //Let go of the last view

			$system.node.fade($id + '_pane_' + section, false); //Display the chosen view
			_view = section; //Remember the current selection
		}

		this.edit = function()
		{
		}

		this.load = function()
		{
		}

		this.preview = function()
		{
		}

		this.write = function()
		{
		}

		this.send = function()
		{ //TODO - Send the mail a few minutes after the submission and make it able to cancel the send during the time

		}
	}
