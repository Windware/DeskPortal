
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		this.expand = function(category) //Show or hide a category
		{
			var region = $id + '_list_' + category; //The category app area
			if(!$system.node.id(region)) return false;

			var hidden = $system.node.hidden(region);
			$system.tip.set([$id, 'category', category].join('_'), $id, hidden ? 'close' : 'open', [__apps[category].name]);

			$system.node.fade(region); //Open up the area
			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'gui.expand'}, {category : category, state : hidden ? 1 : 0}); //Save the state
		}

		this.launch = function(id) { return $system.node.id(id) ? $system.tool.fade(id) : $system.tool.create(id); } //Load or close an application

		this.logout = function() //Logs the user out
		{
			var language = $system.language.strings($id);
			if(confirm(language.confirm)) $system.user.logout(true);
		}
	}
