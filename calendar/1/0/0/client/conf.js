
	$self.conf = new function()
	{
		var _class = $id + '.conf';

		this._1_category = function() //Category tab action
		{
			var log = $system.log.init(_class + '._1_category');
			var language = $system.language.strings($id, 'conf.xml');

			//Get the category listings with the initial new register option
			var cats = [{id : 0, name : '(' + language['new'] + ')'}].concat($self.group.get());
			var node = $system.node.id($id + '_pick_category'); //The select form

			node.innerHTML = ''; //Remove the previous entries

			for(var i = 0; i < cats.length; i++) //Set the category options
			{
				var option = document.createElement('option');

				$system.node.text(option, cats[i].name);
				option.value = cats[i].id;

				node.appendChild(option);
			}

			var appearance = $system.node.id($id + '_pick_color'); //Set color list
			appearance.innerHTML = ''; //Remove the previous entries

			//Some color list. Each pair is the difference against the 'red' value for 'green' and 'blue'
			var variant = [[0, 0], [17, 34], [51, 17], [-17, -34], [-17, -85], [-51, -51]];

			//Set the color namings
			var naming = $system.array.list('grey blue green brown yellow red');
			var language = $system.language.strings($system.info.id, 'color.xml');

			for(var i = 0; i < variant.length; i++) //For all the color variants
			{
				var index = 1;

				for(var j = 220; j > 70; j -= 50) //Have them in different bright level
				{
					var color = [j, j + variant[i][0], j + variant[i][1]]; //Get its RGB value
					color = $system.text.format('rgb(%%, %%, %%)', color)

					var option = document.createElement('option'); //Create the color option
					option.value = color;

					option.style.backgroundColor = color; //Set the color code
					$system.node.text(option, language[naming[i]] + index++); //Describe the color

					appearance.appendChild(option);
				}
			}

			$self.conf.preview(); //Preview the new color
		}

		this.display = function(id) //Display the chosen category information
		{
			var log = $system.log.init(_class + '.display');
			if(!$system.is.digit(id)) return log.param();

			var cats = $self.group.get(true); //Get the category listings

			$system.node.id($id + '_pick_name').value = cats[id] ? cats[id].name : '';
			$system.node.id($id + '_pick_color').value = cats[id] ? cats[id].color : '';

			$system.node.hide($id + '_pick_delete', id == 0); //Display or hide the delete button
			$self.conf.preview(); //Preview the new color
		}

		this.preview = function() //Preview the selected color
		{
			var log = $system.log.init(_class + '.preview');

			var preview = $system.node.id($id + '_pick_preview'); //Color preview area
			preview.style.backgroundColor = $system.node.id($id + '_pick_color').value; //Set a preview
		}

		this.refresh = function(xml) //Refresh category list for all occurances
		{
			var log = $system.log.init(_class + '.refresh');
			$self.group.get(false, xml); //Update the category list from the server

			$self.conf._1_category(); //Update the select form listing
			$self.gui.refresh(); //Refresh the calendar

			$self.conf.preview(); //Preview the new color
		}
	}
