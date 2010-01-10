
	$self.conf = new function()
	{
		var _class = $id + '.conf';

		this.account = function() //Apply account configuration changes
		{
			var log = $system.log.init(_class + '.account');
			var form = $system.node.id($id + '_conf_form');

			var required = $system.array.list('description name address receive_host receive_port send_host send_port');
			var option = {}; //List of option values to send

			for(var i = 0; i < form.elements.length; i++)
			{
				var item = form.elements[i];

				if($system.array.find(required, item.name) && item.value == '')
				{
					$system.gui.alert($id, 'error', 'fill', 3, null, null, 'conf.xml'); //If any field is left blank that is required, notify it
					return false; //Avoid form submission
				}

				if(item.type == 'checkbox') var value = item.checked ? '1' : '0';
				else var value = item.value;

				option[item.name] = value.substr(0, 500); //Limit the string length (Mainly for signature)
			}

			var update = function()
			{
			}

			$system.network.send($self.info.root + 'server/php/front.php', {task : 'conf.account'}, option, update);
			return false; //Avoid form submission
		}

		//this.keep = function(state) { $system.node.id($id + '_conf_duration').disabled = !state; } //Change the POP3 mail keep configuration state

		this.port = function(section) //Set the port number on the current choice
		{
			var log = $system.log.init(_class + '.type');
			if(!$system.array.find(['receive', 'send'], section)) return log.param();

			var form = $system.node.id($id + '_conf_form');

			if(section == 'receive')
			{
				if(form.receive_type.value == 'pop3') form[section + '_port'].value = !form[section + '_secure'].checked ? '110' : '995'; //POP port numbers
				else form[section + '_port'].value = !form[section + '_secure'].checked ? '143' : '993'; //IMAP port numbers
			}
			else form[section + '_port'].value = !form[section + '_secure'].checked ? '25' : '465'; //SMTP port numbers
		}

		this.type = function(type) //Alter values and mail keep option by specified receive type
		{
			var log = $system.log.init(_class + '.type');
			var nodes = $system.node.id($id + '_conf_account').childNodes;

			for(var i = 0; i < nodes.length; i++) //Hide redundant fields for certain account types
			{
				if(nodes[i].nodeType != 1 || nodes[i].nodeName != 'TR') continue;

				if($system.node.classes(nodes[i], $id + '_conf_detail')) $system.node.hide(nodes[i], !$system.array.find(['pop3', 'imap'], type));
				else if($system.node.classes(nodes[i], $id + '_conf_pop3')) $system.node.hide(nodes[i], type != 'pop3');
			}

			$self.conf.port('receive'); //Update the port number
		}
	}
