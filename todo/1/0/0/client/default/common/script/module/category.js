
	$self.category = new function()
	{
		var _class = $id + '.category';

		var _cats;

		this.get = function(indexed, list) //Get the current category listing
		{
			var log = $system.log.init(_class + '.get');
			if(_cats !== undefined && list === undefined) return indexed ? _cats.indexed : _cats.ordered;

			if(!$system.is.object(list)) //Get a fresh list if not provided : TODO - Possibly go async
			{
				var request = $system.network.send($self.info.root + 'server/php/run.php', {task : 'category.get'}, null, null);
				if(!request.valid()) return []; //TODO : Show alert

				list = request.xml;
			}

			_cats = {indexed : {}, ordered : []};

			var attribute = ['id', 'name'];
			list = $system.dom.tags(list, 'category'); //Get the attributes from the XML

			for(var i = 0; i < list.length; i++)
			{
				var param = {id : $system.dom.attribute(list[i], 'id'), name : $system.dom.attribute(list[i], 'name')};

				_cats.ordered.push(param);
				_cats.indexed[$system.dom.attribute(list[i], 'id')] = param;
			}

			return indexed ? _cats.indexed : _cats.ordered;
		}

		this.refresh = function(xml) //Refresh category list on all occurances
		{
			var log = $system.log.init(_class + '.refresh');

			$self.category.get(false, xml); //Update the category list
			$self.conf._1_category(); //Update the configuration tab select form listing

			var update = function(select) //Update select form
			{
				select = $system.node.id(select);
				if(!$system.is.element(select)) return false;

				var choice = select.value; //Remember the choice
				var category = [{id : 0, name : '---'}].concat($self.category.get());

				select.innerHTML = ''; //Clear the current list

				for(var i = 0; i < category.length; i++)
				{
					var option = document.createElement('option');

					option.value = category[i].id;
					$system.node.text(option, category[i].name);

					if(option.value == choice) option.selected = true;
					select.appendChild(option);
				}
			}

			var language = $system.language.strings($id, 'conf.xml');

			update($id + '_set_category'); //Category selection on new entry window
			for(var id in __opened) update($id + '_info_category_' + id); //Update the categories on each window
		}

		this.remove = function() //Removes the chosen category
		{
			var log = $system.log.init(_class + '.remove');

			var select = $system.node.id($id + '_pick_category');
			if(!$system.is.element(select) || !$system.is.digit(select.value)) return log.param();

			var language = $system.language.strings($id, 'conf.xml');
			if(!confirm(language.confirm)) return false;

			var removal = function(request)
			{
				log.user($global.log.notice, 'user/category/remove', '', [_cats.indexed[select.value] ? _cats.indexed[select.value].name : select.value]);

				//Clear out the input fields
				$system.node.id($id + '_pick_name').value = '';
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				$system.node.id($id + '_pick_category').value = 0; //Set to the top option

				$self.category.refresh(request.xml); //Update the category list
				$self.item.get(); //Update the listing with new category names
			}

			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'category.remove'}, {category : select.value}, removal);
		}

		this.set = function(form) //Set a category
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.element(form, 'form')) return log.param();

			if(form.name.value == '') return false;
			var values = {id : form.category.value, name : form.name.value};

			var set = function(request)
			{
				log.user($global.log.notice, form.category.value != '0' ? 'user/category/update' : 'user/category/create', '', [form.name.value]);
				$system.node.hide($id + '_pick_delete', true); //Remove the delete button

				form.category.value = 0; //Reset the form fields for new registration
				form.name.value = '';

				$self.category.refresh(request.xml); //Update the category list
				$self.item.get(); //Update the listing with new category names

				return false; //Keep the form from getting submitted
			}

			$system.network.send($self.info.root + 'server/php/run.php', {task : 'category.set'}, values, set);
			return false; //Invalidate the form submission
		}
	}
