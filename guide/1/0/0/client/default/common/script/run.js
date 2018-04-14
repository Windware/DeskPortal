$self.run = function()
{
	var language = $system.language.strings($id)

	for(var page in $self.info.template) //Create page index
	{
		var list = document.createElement('li')

		var link = document.createElement('a')
		link.id = $id + '_index_' + page

		link.innerHTML = language[page] || page
		link.onclick = $system.app.method($self.gui.page, [page])

		list.appendChild(link)
		$system.node.id($id + '_list').appendChild(list)
	}

	$self.gui.page('_1_intro') //Show the introduction page
	if($global.demo.mode) $system.node.hide($id + '_demo', false)
}
