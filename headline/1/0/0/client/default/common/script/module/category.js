
	$self.category = new function()
	{
		var _class = $id + '.category';

		var _category; //List of categories

		this.get = function(indexed, list) //Get the current category listing
		{
			var log = $system.log.init(_class + '.get');
			if(_category !== undefined && list === undefined) return indexed ? _category.indexed : _category.ordered;

			if(!$system.is.object(list)) //Get a fresh list if not provided TODO - Try async?
			{
				var request = $system.network.send($self.info.root + 'server/php/run.php', {task : 'category.get'}, null, null);
				if(!request.valid()) return false;

				list = request.xml;
			}

			_category = {indexed : {}, ordered : []};
			list = $system.dom.tags(list, 'category'); //Get the attributes from the XML

			for(var i = 0; i < list.length; i++)
			{
				var param = {id : $system.dom.attribute(list[i], 'id'), name : $system.dom.attribute(list[i], 'name')};

				_category.ordered.push(param);
				_category.indexed[$system.dom.attribute(list[i], 'id')] = param;
			}

			return indexed ? _category.indexed : _category.ordered;
		}

		this.remove = function() //Removes the chosen category
		{
			var log = $system.log.init(_class + '.remove');

			var category = $system.node.id($id + '_pick_category').value;
			if(!$system.is.digit(category)) return log.param();

			var language = $system.language.strings($id, 'conf.xml');
			if(!confirm(language['category/confirm'])) return false;

			var removal = function(request)
			{
				log.user($global.log.notice, 'user/category/remove', '', [_category.indexed[category] ? _category.indexed[category].name : category]);

				//Clear out the input fields
				$system.node.id($id + '_pick_name').value = '';
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				$self.category.update(request.xml); //Update the category list
				$system.node.id($id + '_pick_category').value = 0; //Set to the top option

				$self.gui.filter(); //Get the list of feeds when a category has been removed
			}

			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'category.remove'}, {category : category}, removal);
		}

		this.set = function(form) //Sets a category
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form') || !$system.is.digit(form.category.value) || !$system.is.text(form.name.value, true)) return log.param();

			var name = form.name.value; //Keep the input name
			if(!name) return false;

			var update = function(request)
			{
				log.user($global.log.notice, form.category.value == '0' ? 'user/category/add' : 'user/category/update', '', [name]);
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				form.name.value = ''; //Empty the text box
				$self.category.update(request.xml);
			}

			$system.network.send($self.info.root + 'server/php/run.php', {task : 'category.set'}, {id : form.category.value, name : name}, update);
			return false; //Avoid the form from being submitted
		}

		this.update = function(xml) //Update the category listing in various locations
		{
			var log = $system.log.init(_class + '.update');
			var cats = [{id : 0, name : '-----'}].concat($self.category.get(false, xml)); //Update the category list

			//Update the category list on the configuration panel
			if($system.is.element($system.node.id($id + '_pick_category'), 'select')) $self.conf._2_category();

			var form = $system.node.id($id + '_entries');

			for(var i = 0; i < form.elements.length; i++)
			{
				var select = form.elements[i];
				if(select.id == $id + '_page_selector') continue;

				picked = select.value; //Remember the current selection
				select.innerHTML = ''; //Clear out the category options

				for(var j = 0; j < cats.length; j++) //Create HTML category list
				{
					var option = document.createElement('option');

					option.value = cats[j].id;
					$system.node.text(option, cats[j].name);

					select.appendChild(option);
				}

				select.value = picked;
			}

			var language = $system.language.strings($id);
			var misc = [{id : '', name : '(' + language.all + ')'}, {id : 0, name : '(' + language.unset + ')'}];

			var cats = misc.concat($self.category.get(false, xml)); //List of registered categories for filtering

			var filter = $system.node.id($id + '_filter_category'); //Filter category list
			filter.innerHTML = ''; //Clear out the current list

			for(var i = 0; i < cats.length; i++)
			{
				var option = document.createElement('option'); //Create category option

				//Set values
				option.value = cats[i].id;
				$system.node.text(option, cats[i].name);

				filter.appendChild(option); //Attach to the select element
			}

			if(__filter.category) filter.value = __filter.category; //Pick the current selection
		}
	}
