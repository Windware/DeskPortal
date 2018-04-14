$self.gui = new function() //NOTE - Using node's ID to identify the select box, since IE6 can't define 'name' on a created select box
{
	var _class = $id + '.gui'

	var _previous = {} //Previously selected options

	var _selected = {} //Currently selected option

	this.first = {} //First entry for each categories

	this.create = function(index) //Create a new bar
	{
		var log = $system.log.init(_class + '.create')
		log.user($global.log.info, 'user/create')

		if(!$system.is.digit(index)) do
		{ index = ++__number }
		while($system.node.id($id + '_window_' + index)) //Check available window ID

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'gui.create'}, {index: index}) //Save the presence
		return $system.window.create($id + '_window_' + index, $self.info.title, $self.info.template.bar.replace(/INDEX/g, index), $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, $self.info.left, $self.info.top, $self.info.width, $self.info.height, $self.info.center, $self.info.up, undefined, undefined, $system.app.method($self.gui.set, [index]))
	}

	this.process = function(index) //Execute the operation (By using individual feature script)
	{
		var log = $system.log.init(_class + '.process')

		if(!_selected[index] || !_selected[index].feature || !_selected[index].method)
			return log.dev($global.log.warning, 'dev/select', 'dev/select/solution')

		var feature = _selected[index].feature
		if(!$system.is.object($self[feature])) return log.dev($global.log.error, problem, '')

		var method = _selected[index].method
		if(typeof $self[feature][method] != 'function') return log.dev($global.log.error, 'dev/function', 'dev/function/solution', [feature, method])

		var form = $system.node.id($id + '_form_' + index)
		if(!$system.is.element(form, 'form') || !$system.is.element(form.input)) return log.dev($global.log.error, 'dev/input', 'dev/input/solution')

		var language = $system.language.strings($id)
		log.user($global.log.info, 'user/run', '', [language[feature.toLowerCase()]])

		var mode = $system.node.id([$id, 'mode', feature, method, index].join('_'))
		if(!$system.is.element(mode)) return log.dev($global.log.error, 'dev/form', 'dev/form/solution')

		if(__mutual[feature][method]) //If mutual target mode
		{
			var target = $system.node.id([$id, 'mutual', feature, method, index].join('_')) //Find the target select node
			if(!$system.is.element(target)) return log.dev($global.log.error, 'dev/mode', 'dev/mode/solution')

			target = target.value //Get its value
			if(mode.value == target.value) return log.user($global.log.notice, 'dev/same', 'dev/same/solution')
		}

		try
		{ var result = $self[feature][method](form.input.value, mode.value, target) } //Execute the specific feature method

		catch(error)
		{ return log.dev($global.log.error, 'dev/execute', 'dev/execute/solution', [feature, method, error]) }

		var node = $system.node.id($id + '_result_' + index)

		if(typeof result != 'boolean') node.innerHTML = (result !== undefined) ? result : language.error //If there is a result to display inline, show it
		else if(!result) node.innerHTML = language.error //Display error when something goes wrong

		return false //Invalidate the submit action
	}

	this.remove = function(index) //Remove a bar
	{
		var log = $system.log.init(_class + '.remove')
		if(!$system.is.digit(index)) return log.param()

		log.user($global.log.info, 'user/remove')

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'gui.remove'}, {index: index}) //Remove the bar entry
		return $system.window.fade($id + '_window_' + index, true, undefined, true)
	}

	this.save = function(index) //Save the function selections
	{
		var log = $system.log.init(_class + '.save')
		if(!$system.is.digit(index)) return log.param()

		var form = $system.node.id($id + '_form_' + index)
		if(!$system.is.element(form, 'form')) return false

		var param = {index: index, feature: form.feature.value} //Selection values to save

		param.method = $system.node.id([$id, 'method', param.feature, index].join('_')).value
		param.source = $system.node.id([$id, 'mode', param.feature, param.method, index].join('_')).value

		var target = $system.node.id([$id, 'mutual', param.feature, param.method, index].join('_'))
		param.target = target ? target.value : '' //If the target selection exists, keep its value

		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'gui.save'}, param) //Save the choice
	}

	this.set = function(index, callback) //Initialize a new bar
	{
		var log = $system.log.init(_class + '.set')
		if(!$system.is.digit(index)) return log.param()

		var controller = $system.node.id($id + '_control_' + index) //The add/delete button
		if(!$system.is.element(controller)) return false

		if(index == 0) //If it is the main bar
		{
			$system.tip.set(controller, $id, 'add') //Give the tip on add sign

			controller.innerHTML = '+' //Set the sign
			controller.onclick = $self.gui.create //Set the add function

			var node = $id
		}
		else
		{
			$system.tip.set(controller, $id, 'delete') //Give the tip on delete sign

			controller.innerHTML = '-' //Set the sign
			controller.onclick = $system.app.method($self.gui.remove, [index])

			var node = $id + '_window_' + index
		}

		var set = function(node, callback, request)
		{
			var select = $system.dom.tags(request.xml, 'select')[0]
			var feature = $system.dom.attribute(select, 'feature')

			if(feature)
			{
				var method = $system.dom.attribute(select, 'method')
				var source = $system.dom.attribute(select, 'source')
				var target = $system.dom.attribute(select, 'target')
			}
			else feature = __initial //Pick the default initial choice

			$self.gui.swap(index, feature, method, source, target, true) //Set the choices
			$system.app.callback(log.origin, callback)

			if($system.browser.os == 'iphone') //Fix iPhone bug when selection boxes overlap without redrawing them
			{
				$system.node.hide(node, true)
				setTimeout($system.app.method($system.node.hide, [node, false]), 0) //Directly reverting the state does not fix
			}
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {
			task : 'gui.set',
			index: index,
		}, null, $system.app.method(set, [node, callback])) //Get the choices
	}

	this.swap = function(index, feature, method, source, target, quick, deep) //Swap the bar option
	{
		var log = $system.log.init(_class + '.swap')
		if(!$system.is.digit(index)) return log.param()

		var form = $system.node.id($id + '_form_' + index)
		if(!$system.is.element(form, 'form')) return false

		if(method === undefined) //If changing the feature
		{
			var id = [$id, 'method', feature, index].join('_')
			var depth = 0 //Indicate this is a feature

			_selected[index] = {feature: feature} //Keep the current selection
			form.feature.value = feature //Select the choice

			if(!deep) $self.gui.swap(index, feature, $system.node.id(id).value, undefined, undefined, quick) //Show the modes from the selected method
		}
		else //If changing the method of a feature
		{
			//Display the method if the feature has never been set first
			if(!_selected[index] || _selected[index].feature != feature) $self.gui.swap(index, feature, undefined, undefined, undefined, quick, true) //Set the feature
			$system.node.id([$id, 'method', feature, index].join('_')).value = method

			var id = [$id, 'region', feature, method, index].join('_')
			var depth = 1 //Indicate this is a method

			_selected[index].method = method //Keep the current selection

			var node = $system.node.id($id + '_result_' + index)
			$system.node.hide(node.id, !__mutual[feature][method])

			if(__mutual[feature][method]) //For having multiple selections
			{
				if(!__external[feature][method]) node.innerHTML = '&#160;' //Clear out the display (Keep a character to avoid getting flattened)
				else //Show the redirection message
				{
					var language = $system.language.strings($id)
					node.innerHTML = '' //Clear the display

					var notice = document.createElement('em') //Create a notice element

					$system.tip.set(notice, $id, 'external')
					notice.innerHTML = '(' + language.external + ')'

					node.appendChild(notice) //Put it inside the result box
				}
			}

			if(source !== undefined) $system.node.id([$id, 'mode', feature, method, index].join('_')).value = source //Set source selection

			if(target) //Set target selection
			{
				var mutual = $system.node.id([$id, 'mutual', feature, method, index].join('_'))
				if(mutual) mutual.value = target
			}

			if(!quick) $self.gui.save(index) //Save the current selection
		}

		if(!_previous[index]) _previous[index] = {} //Create the object to remember the last selection of a window
		if(_previous[index][depth]) $system.node.hide(_previous[index][depth], true) //Let go of the previous entry

		$system.node.fade(id, false)
		_previous[index][depth] = id //Remember the current visible option

		return true
	}
}
