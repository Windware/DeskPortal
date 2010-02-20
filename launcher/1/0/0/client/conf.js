
	$self.conf = new function()
	{
		this._1_display = function() { $system.node.id($id + '_display').checked = !!$global.user.conf[$id].display; } //Check the option box

		this.save = function(form) //Save the display option
		{
			if(!$system.is.element(form, 'form')) return false;
			var state = form.display.checked ? 1 : 0;

			$system.node.fade($id + '_name', !state);
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.save'}, {display : state});
		}
	}
