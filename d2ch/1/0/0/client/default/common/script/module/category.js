
	$self.category = new function()
	{
		var _class = $id + '.category';

		this.expand = function(id) //Expands or shrinks the category on the main interface
		{
			var log = $system.log.init(_class + '.expand');
			if(!$system.is.digit(id)) return log.param();

			var node = $id + '_category_' + id;
			return $system.node.fade(node, !$system.node.hidden(node));
		}
	}
