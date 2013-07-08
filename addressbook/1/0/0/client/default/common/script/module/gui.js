
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _timer; //Key-up timer for searching

		this.close = function(id) //Close the edit window
		{
			$system.window.fade($id + '_edit_' + id, true, null, true);
			delete __opened[id]; //Let go from the list of opened window
		}

		this.column = function(section, on) //Change the display columns
		{
			var log = $system.log.init(_class + '.column');
			if(!$system.is.text(section) || typeof on != 'boolean') return log.param();

			if($system.node.id($id + '_display_' + section)) //Set the checked list parameter
			{
				if(on) __column[section] = true;
				else delete __column[section];
			}

			$self.item.get(undefined, true); //Refresh the listing

			var displayed = []; //List of checked boxes
			for(var name in __column) displayed.push(name);

			log.user($global.log.info, 'user/column');
			return $system.app.conf($id, {column : displayed.join(',')});
		}

		this.group = function(node, empty, select, callback) //Set groups on a select node
		{
			var log = $system.log.init(_class + '.group');
			if(!$system.is.text(node)) return log.param();

			var node = $system.node.id(node);
			if(!$system.is.element(node)) return false;

			var list = function(node, empty, select, callback, group)
			{
				var chosen = node.value; //Keep the selection
				node.innerHTML = ''; //Clear up the options

				var language = $system.language.strings($id);

				var common = [{id : 0, name : '(' + language['uncategorized'] + ')'}];
				if(empty) common = [{id : '', name : '(' + language['select'] + ')'}].concat(common);

				var entries = common.concat(group.ordered); //Get the groups in the order with common entries
				var pick = select === undefined ? chosen : select; //Select what was selected or to the value specified

				for(var i = 0; i < entries.length; i++)
				{
					var option = document.createElement('option');

					option.value = entries[i].id;
					$system.node.text(option, entries[i].name);

					if(option.value == pick) option.selected = true;
					node.appendChild(option);
				}

				if(typeof callback == 'function') callback();
			}

			return $self.group.get($system.app.method(list, [node, empty, select, callback])); //Get the groups
		}

		this.header = function() //Clear and put the table header on
		{
			var language = $system.language.strings($id);
			var table = $system.node.id($id + '_entries');

			//NOTE : khtml doesn't like innerHTML to be used on table elements
			while(table.firstChild) table.removeChild(table.firstChild); //Clean up the area

			var row = document.createElement('tr'); //Create the header row
			row.className = $id + '_header'; //Set a class for styling

			for(var i = 0; i < __all.length; i++) //Sustain the order by looking at the master list
			{
				if(!__column[__all[i]]) continue; //If not supposed to be listed, skip
				var cell = document.createElement('th');

				$system.node.text(cell, language[__all[i]]);
				row.appendChild(cell);
			}

			table.appendChild(row);
		}

		this.mail = function(address) //Compose mail for the address
		{
			var log = $system.log.init(_class + '.mail');
			if(!$system.is.text(address, false, /.@./)) return log.param();

			var app = 'mail_1_0_0'; //Launch the mailer and start composing

			if(!$system.app.library(app)) return false;
			return $system.app.load(app, $system.app.method($global.top[app].gui.create, [null, null, [address]]));
		}

		this.search = function(phrase) //Searches through the contents displayed
		{
			__search = phrase; //Set the search phrase
			$self.item.get(undefined, true); //Refresh the list

			return false; //Stop the form submission
		}

		this.web = function(address) //Open the web site
		{
			if(!$system.is.address(address)) address = 'http://' + address;
			window.open(address); //Display the linked site
		}
	}
