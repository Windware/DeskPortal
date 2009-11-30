
	$self.gui = new function() //TODO : make user alert on errors
	{
		var _class = $id + '.gui';

		this.filter = function(callback, quick) //Filter the headline entries
		{
			var log = $system.log.init(_class + '.filter');
			if(!$system.is.digit(__selected)) quick = true; //If nothing selected, do not fetch the content

			var form = $system.node.id($id + '_selection_filter'); //Set filter options

			__filter.marked = form.marked.checked ? 1 : 0;
			__filter.unread = form.unread.checked ? 1 : 0;

			__filter.category = form.category.value;
			__filter.search = form.search.value;

			if(!quick) $self.entry.get(__selected, __feed[__selected].page, callback); //Reload the current entries
			return false; //Do not let the form to be submitted to a new page
		}

		this.page = function(id) //Flip the page of a feed's entries
		{
			var log = $system.log.init(_class + '.page');
			if(!$system.is.digit(id)) return log.param();

			var select = $system.node.id($id + '_page_selector');
			if(!$system.is.element(select)) return false;

			return $self.entry.get(id, select.value);
		}

		this.period = function(period) //Swap the display period option
		{
			var log = $system.log.init(_class + '.period');
			if(!String(period).match(/[012]/) || !__selected) return log.param();

			__apply(__selected); //Store the selected values to internal hash
			__swap(period); //Swap the necessary select form element

			//Keep the settings per feed
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.period'}, {id : __selected, mode : period}, $self.gui.span);
		}

		this.scroll = function(id) //Scroll to a specified displayed entry
		{
			var log = $system.log.init(_class + '.scroll');

			if($system.is.digit(id))
			{
				var line = $system.node.id($id + '_line_' + id);
				if(!$system.is.element(line)) return false;

				var position = line.offsetTop;
			}
			else var position = 0;

			$system.node.id($id + '_entries').scrollTop = position;
		}

		this.source = function(address) //Open the source site
		{
			var log = $system.log.init(_class + '.source');

			var event = $system.event.source(arguments);
			event.cancelBubble = true; //Avoid triggering viewing the feed

			if(!$system.is.address(address)) return log.param();
			return window.open(address); //Display the linked page
		}

		this.span = function(id, quick) //Change the display span
		{
			var log = $system.log.init(_class + '.span');
			var form = $system.node.id($id + '_selection_span'); //Option form

			//Apply the form value to the filter list
			for(var i = 0; i < __section.length; i++) __filter[__section[i]] = form[__section[i]].value;

			if($system.is.digit(id)) var index = id;
			else if($system.is.digit(__selected)) var index = __selected;
			else return false;

			__apply(index); //Keep the display period information for the feed
			return quick ? true : $self.entry.get(index); //Update the entries list
		}

		this.view = function(id, address, line) //Show the headline feed's original page
		{
			var log = $system.log.init(_class + '.view');
			if(!$system.is.address(address)) return log.param();

			window.open(address, $id + '_source_' + id); //Display the linked page
			$system.node.classes(line, $id + '_read', true);

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.view'}, {id : id, mode : 1});
		}
	}
