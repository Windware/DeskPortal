$self.group = new function()
{
	var _class = $id + '.group'

	this.get = function(indexed, list) //Get the category list, either indexed by category ID or in the order returned by the server
	{ //TODO - try async
		var log = $system.log.init(_class + '.get')
		if(__cats !== undefined && list === undefined) return indexed ? __cats.indexed : __cats.ordered

		if(!$system.is.object(list)) //Get a fresh list if not provided
		{
			var request = $system.network.send($self.info.root + 'server/php/run.php', {task: 'group.get'}, null, null)
			if(!request.valid()) return []

			list = request.xml
		}

		__cats = {indexed: {}, ordered: []}

		var attribute = $system.array.list('id name color')
		list = $system.dom.tags(list, 'category') //Get the attributes from the XML

		for(var i = 0; i < list.length; i++)
		{
			var param = {}
			for(var j = 0; j < attribute.length; j++) param[attribute[j]] = $system.dom.attribute(list[i], attribute[j])

			__cats.ordered.push(param)
			__cats.indexed[$system.dom.attribute(list[i], 'id')] = param
		}

		return indexed ? __cats.indexed : __cats.ordered
	}

	this.remove = function() //Removes the chosen group
	{
		var log = $system.log.init(_class + '.remove')

		var category = $system.node.id($id + '_pick_category').value
		if(!$system.is.digit(category)) return log.param()

		var language = $system.language.strings($id, 'conf.xml')
		if(!confirm(language.confirm)) return false

		var removal = function(request)
		{
			log.user($global.log.notice, 'user/category/remove', '', [__cats.indexed[category] ? __cats.indexed[category].name : category])

			//Clear out the input fields
			$system.node.id($id + '_pick_name').value = ''
			$system.node.id($id + '_pick_color').value = ''

			$system.node.hide($id + '_pick_delete', true) //Remove the delete button

			$self.conf.refresh(request.xml) //Update the category list
			$system.node.id($id + '_pick_category').value = 0 //Set to the top option
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'group.remove'}, {category: category}, removal)
	}

	this.set = function(form) //Submit the category edit
	{
		var log = $system.log.init(_class + '.set')
		if(!$system.is.element(form, 'form')) return log.param()

		if(form.name.value == '') return false //TODO - Make an alert
		var values = {id: form.category.value, name: form.name.value, color: form.color.value}

		var refresh = function(form, request)
		{
			log.user($global.log.notice, form.category.value == '0' ? 'user/category/add' : 'user/category/update', '', [form.name.value])
			form.category.value = 0 //Reset the form fields for new registration

			form.name.value = ''
			form.color.value = ''

			$system.node.hide($id + '_pick_delete', true) //Remove the delete button
			$self.conf.refresh(request.xml) //Update the category list
		}

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'group.set'}, values, $system.app.method(refresh, [form]))
		return false //Keep the form from getting submit
	}
}

