
	$self.conf = new function()
	{
		var _class = $id + '.conf';

		this._1_display = function() //Check by the configured values
		{
			var log = $system.log.init(_class + '._1_display');
			var form = $system.node.id($id + '_conf_form');

			var conf = $global.user.conf;
			form.period.value = conf[$id].period;

			form.level.value = conf['system_static'].log;
			form.dev.checked = !!conf['system_static'].debug;
		}

		this.apply = function(form) //Save the configuration values
		{
			var log = $system.log.init(_class + '.apply');
			if(!$system.is.element(form, 'form')) return log.param();

			$system.app.conf($id, {period : form.period.value});
			$system.app.conf('system_static', {log : form.level.value, debug : form.dev.checked ? 1 : 0});

			log.user($global.log.notice, 'user/change');
			return false;
		}
	}
