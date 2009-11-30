
	$self.conf = new function()
	{
		var _class = $id + '.conf';

		this._1_display = function() //Check by the configured values
		{
			var log = $system.log.init(_class + '._1_display');

			var form = $system.node.id($id + '_conf_form');
			form.date.checked = !!$global.user.conf[$id].date;
		}

		this.apply = function(form) //Save the configuration
		{
			var log = $system.log.init(_class + '.apply');
			if(!$system.is.element(form, 'form')) return log.param();

			$system.app.conf($id, {date : form.date.checked ? 1 : 0});
			$self.gui.date(form.date.checked); //Update the date display

			log.user($global.log.info, 'user/change');
			return false;
		}
	}
