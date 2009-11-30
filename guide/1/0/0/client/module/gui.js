
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _previous; //Previously selected index link

		this.page = function(name) //Swap to another page
		{
			var log = $system.log.init(_class + '.page');
			if(!$system.is.text(name) || !$self.info.template[name]) return log.param();

			if(_previous) $system.node.classes(_previous, $id + '_active', false); //Revert the look
			var index = $id + '_index_' + name; //Index node ID

			$system.node.classes(index, $id + '_active', true); //Make it look active

			var page = $system.node.id($id + '_page');
			$system.node.fade(page.id, false);

			page.innerHTML = $self.info.template[name]; //Set the page
			page.scrollTop = 0; //Show the top part of page

			_previous = index; //Remember current index
		}
	}
