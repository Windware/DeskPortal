$self.run = function(callback)
{
	var list = function(callback, request)
	{
		if($system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/run/list', 'user/run/list/message')

		var category = $system.dom.tags(request.xml, 'category') //List of categories
		var zone = $system.node.id($id + '_list')

		for(var x = 0; x < category.length; x++)
		{
			var id = $system.dom.attribute(category[x], 'id') //Category ID
			var link = document.createElement('a')

			link.className = $id + '_category'
			link.onclick = $system.app.method($self.category.expand, [id])

			__category[id] = $system.dom.attribute(category[x], 'name')

			$system.node.text(link, __category[id])
			zone.appendChild(link)

			var board = $system.dom.tags(category[x], 'board') //List of boards
			var section = document.createElement('div')

			section.id = $id + '_category_' + id
			section.className = $system.info.id + '_hidden'

			for(var y = 0; y < board.length; y++)
			{
				var sub = document.createElement('a')
				var index = $system.dom.attribute(board[y], 'id')

				__board[index] = $system.dom.attribute(board[y], 'name')
				sub.onclick = $system.app.method($self.thread.get, [index, sub])

				sub.className = $id + '_board'
				$system.node.text(sub, __board[index])

				section.appendChild(sub)
			}

			zone.appendChild(section)
		}
	}

	return $system.network.send($self.info.root + 'server/php/run.php', {task: 'run'}, null, $system.app.method(list, [callback])) //Get list of boards
}
