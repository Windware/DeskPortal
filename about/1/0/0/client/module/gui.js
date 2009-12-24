
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		this.language = function(language)
		{
			var log = $system.log.init(_class + '.language');

			if(!$system.is.language(language))
			{
				log.user($global.log.error, 'user/switch', 'user/switch/solution');
				return log.param();
			}

			location.href = location.href.replace(/\?.*/, '') + '?' + language; //Swap the address to specify the language
		}

		this.show = function() //Swap the details on and off
		{
			var node = $id + '_more'; //The node to hide and show
			var state = $system.node.hidden(node); //The hidden state of the area

			var element = $system.node.id($id + '_click');

			var alter = function() //Action to take after the fade completes
			{
				$system.tip.set(element, $id, state ? 'less' : 'more'); //Switch the tooltip

				var strings = $system.language.strings($id); //Get the language strings
				$system.node.text(element, strings[state ? 'hide' : 'more']); //Switch the HTML text
			}

			$system.node.fade(node, !state, alter); //Swap the node visibility
		}
	}
