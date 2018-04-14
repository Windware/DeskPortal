$self.style = new function() //Style related class
{
	var _class = $id + '.style'

	var _name = '%%_style_%%_%%' //DOM ID string for a stylesheet

	this.add = function(id, sheet) //Load a style sheet for an already loaded application
	{
		var log = $system.log.init(_class + '.add')
		if(!String(sheet).match(/\.css$/i) || !$system.is.id(id)) return log.param()

		//Make an unique node ID for the style sheet
		var node = $system.text.format(_name, [$id, id, sheet.replace(/\.css$/i, '')])

		var head = $system.dom.tags(document, 'head')[0] //The 'head' node
		var list = head.getElementsByTagName('style') //'head' node does not have 'getElementById'

		for(var i = 0; i < list.length; i++)
		{
			if(list[i].id == node) //Report about duplicate style sheet but report it as success
				return log.dev($global.log.info, 'dev/style/exist', 'dev/style/exist/solution', [id, sheet], null, true)
		}

		var root = $global.user.conf[id] && $global.user.conf[id].theme ? $global.user.conf[id].theme : $system.app.path(id) + 'client/default/common'
		var style = root + '/style/' + sheet

		var request = $system.network.item(style)

		if(!request.made) //Grab the stylesheet if it is not retrieved
		{
			$system.network.fetch([style])
			request = $system.network.item(style)
		}

		if(!request.valid()) return log.dev($global.log.info, 'dev/style/load', 'dev/style/load/solution', [id, sheet])
		var css = document.createElement('style') //Create a 'style' node

		//Set its properties
		css.id = node
		css.type = 'text/css'

		var body = 'td#' + [$id, id, 'body'].join('_')
		var content = $system.text.template(request.text, id).replace(/%self%/g, body) //Replace variables

		//Create style sheet declarations according to engine implementation
		if(css.styleSheet) css.styleSheet.cssText = content //For IE
		else if(css.appendChild) css.appendChild(document.createTextNode(content)) //For others
		else return log.dev($global.log.error, 'dev/style/capability', 'dev/style/capability/solution')

		head.appendChild(css) //Append the style sheet node to the 'head' node
	}

	this.remove = function(id, sheet) //Remove an appended style sheet
	{
		var log = $system.log.init(_class + '.remove')
		if(!String(sheet).match(/\.css$/i) || !$system.is.id(id)) return log.param()

		$system.node.remove($system.text.format(_name, [$id, id, sheet])) //Remove the style sheet node
	}
}
