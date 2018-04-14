$self.gui = new function()
{
	var _class = $id + '.gui'

	var _interval = 10 //Seconds to wait till auto save

	var _timer = {} //Timer for auto save

	this.create = function() //Pop a new window to add a new note
	{
		var log = $system.log.init(_class + '.create')
		var id = $id + '_new'

		var focus = function() { if($system.node.id($id + '_new_name')) $system.node.id($id + '_new_name').focus() } //Focus on the input field

		if($system.node.id(id)) return $system.window.fade(id, undefined, focus, true)
		$system.window.create(id, $self.info.title, $self.info.template['new'], 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 300, undefined, true, false, true, focus, $self.gui.fill, true)
	}

	this.expand = function(id, show) //Expands or shrinks a group of memos
	{
		var log = $system.log.init(_class + '.expand')
		if(!$system.is.digit(id)) return log.param()

		var group = $id + '_zone_' + id

		if(show === true && !$system.node.hidden(group)) return true
		var state = show === true || $system.node.hidden(group) //Hidden state

		if(state) __expansion[id] = true //Keep the expanded group list
		else delete __expansion[id]

		var tag = $id + '_tag_' + id
		$system.tip.set(tag, $id, state ? 'shrink' : 'expand') //Swap the tip

		$system.node.id(tag).className = $id + (state ? '_shown' : '_closed') //Adapt the look
		return $system.node.fade(group, !state) //Change its visibility
	}

	this.fill = function() //Set the groups in the new memo window
	{
		var log = $system.log.init(_class + '.fill')

		var node = $system.node.id($id + '_set_groups') //Group field
		if(!$system.is.element(node)) return

		var language = $system.language.strings($id, 'conf.xml')
		var groups = $self.group.get()

		node.innerHTML = '' //Clean up the previous entries if any

		for(var i = 0; i < groups.length; i++) //Set the group list
			node.innerHTML += $system.text.format($self.info.template.choice, {
				group  : groups[i].id,
				name   : $system.text.escape(groups[i].name),
				checked: '',
			})
	}

	this.info = function(id) //Load the info pane
	{
		var log = $system.log.init(_class + '.info')
		if(!$system.is.digit(id)) return log.param()

		var node = $id + '_edit_' + id
		if($system.node.id(node)) return $system.window.fade(node) //Fade in or out after created

		var groups = $self.group.get()
		var list = '' //List of groups

		for(var i = 0; i < groups.length; i++)
		{
			//If the category matches, keep it checked
			var checked = __relation[groups[i].id] && __relation[groups[i].id].indexed[id] ? ' checked="checked"' : ''
			list += $system.text.format($self.info.template.pick, {
				group: groups[i].id,
				name : $system.text.escape(groups[i].name),
				info : id,
				check: checked,
			})
		}

		var updated = $system.date.create(__memos[id].last).format($global.user.pref.format.date + ' ' + $global.user.pref.format.time)

		var values = {id: id, group: list, name: $system.text.escape(__memos[id].name), last: updated}
		var replace = function(phrase, match) { return values[match] } //Replace variables

		var language = $system.language.strings($id)
		var title = $self.info.title + ' ' + language['info']

		$system.window.create(node, title, $self.info.template.edit.replace(/%value:(\w+?)%/g, replace), $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 500, undefined, true, false, true, null, null, true)
		__opened[id] = true //Track the opened window
	}

	this.prepare = function(id) //Prepare for memo saving
	{
		var field = $system.node.id($id + '_field_' + id) //Change the look of the field on edit

		//NOTE : Not logging due to being intensively triggered
		if(__content[id] == field.value) return

		if(_timer[id]) clearTimeout(_timer[id]) //Let go of the old timer

		//If key is not pressed for a while, save the content
		_timer[id] = setTimeout($system.app.method($self.item.save, [id]), _interval * 1000)

		if($system.is.element(field)) $system.node.classes(field, $id + '_altered', true) //Change the look
		__content[id] = field.value //Set its new content cache
	}
}
