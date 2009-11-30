
	$self.run = function(callback) //Create the function selectors from the list in XML
	{
		var language = {local : $system.language.strings($id), system : {}}; //Get the localized strings
		var categories = $system.array.list('currency language strings unit');

		for(var i = 0; i < categories.length; i++) //Concatenate the languge string hashes
		{
			var strings = $system.language.strings($system.info.id, categories[i] + '.xml');
			for(var j in strings) language.system[j] = strings[j];
		}

		var localize = function(text) //Return a localized string
		{
			var replace = function(phrase, match) { return language.local[match] || language.system[match] || match; }
			return text.replace(/^(.+)/, replace);
		}

		var display = {feature : '', method : '', mode : '', mutual : ''}; //List of values to display in the template
		var request = $system.network.item($self.info.root + 'resource/*.xml', true); //Get the XML file

		for(var i = 0; i < request.length; i++) //Create the selections from XML
		{
			if(!request[i].valid()) throw 'File cannot be loaded : ' + request[i];

			var section = request[i].file.replace(/\.xml$/, ''); //Get the file name with no extension
			if(!__operation[section]) __operation[section] = {}; //Create the operation list

			var list = $system.dom.tags(request[i].xml, 'method'); //The method list
			var choice = section == __initial ? ' selected="selected"' : '';

			//Create the method select options
			display.feature += $system.text.format('<option value="%%"%%>%%</option>', [section, choice, language.local[section]]);

			var method = document.createElement('select'); //Select field for methods of a function
			method.setAttribute('onchange', $system.text.format("%%.%%.gui.swap(INDEX, '%%', this.value)", [$global.root, $id, section])); //Set the swap function

			method.id = [$id, 'method', section, 'INDEX'].join('_'); //ID for the select node
			method.name = 'method_' + section;

			method.className = $system.info.id + '_hidden'; //Keep it hidden at start
			method.style.textTransform = 'capitalize'; //Capitalize the words

			for(var j = 0; j < list.length; j++)
			{
				var option = document.createElement('option'); //Create option node
				var name = option.value = $system.dom.attribute(list[j], 'name');

				if(!__operation[section][name]) __operation[section][name] = {};

				$system.node.text(option, localize(option.value));
				method.appendChild(option); //Set the attributes and append to the select node

				var uses = $system.dom.tags(list[j], 'mode'); //Pick the option categories

				var mode = document.createElement('select'); //Create a select node for the categories
				mode.setAttribute('onchange', $system.text.format('%%.%%.gui.save(INDEX)', [$global.root, $id])); //Using setAttribute to sustain it under innerHTML

				mode.id = [$id, 'mode', section, name, 'INDEX'].join('_'); //ID for the select node
				mode.name = 'source_' + section + '_' + name;

				mode.style.textTransform = 'capitalize'; //Capitalize the words

				for(var k = 0 ; k < uses.length; k++)
				{
					var option = document.createElement('option'); //Create option node

					option.value = $system.dom.attribute(uses[k], 'name');
					$system.node.text(option, localize(option.value));

					mode.appendChild(option); //Set attributes and append to the select node
					__operation[section][name][option.value] = $system.dom.attribute(uses[k], 'target'); //Remember its operation mode
				}

				var region = document.createElement('div'); //The grouped region for mode selection forms
				region.id = [$id, 'region', section, name, 'INDEX'].join('_');

				region.className = $system.info.id + '_hidden';
				region.appendChild(mode); //Add to the category area

				if(name == 'language') //Pick the currently used language (select.value does not keep the selection under innerHTML on some engines)
					region.innerHTML = region.innerHTML.replace(RegExp('( value=(")?' + $global.user.language + '\\2)'), '$1 selected="selected"');

				if(!__mutual[section]) __mutual[section] = {};

				if($system.dom.attribute(list[j], 'mutual') == '1') //Add duplicate options for mutual selection mode
				{
					__mutual[section][name] = true; //Indicate that this method uses two select forms

					var replacer = RegExp($system.text.regexp([$id, 'mode', section, name, 'INDEX'].join('_')));
					var selection = region.innerHTML.replace(replacer, [$id, 'mutual', section, name, 'INDEX'].join('_'));

					var mutual = document.createElement('div'); //Same selection with different ID
					mutual.innerHTML = selection.replace('source_' + section + '_' + name, 'target_' + section + '_' + name);

					region.appendChild(mutual);
				}

				if(!__external[section]) __external[section] = {};

				//Set that the results will be shown at an external source
				if($system.dom.attribute(list[j], 'external') == '1') __external[section][name] = true;

				var box = document.createElement('div'); //Temporary object to obtain the innerHTML of the region element
				box.appendChild(region);

				display.mode += box.innerHTML;
			}

			__first[section] = $system.dom.attribute(list[0], 'name'); //Keep the first method of a feature

			var region = document.createElement('div'); //A temporary object to hold the select elements
			region.appendChild(method); //Add to the category area

			display.method += region.innerHTML;
		}

		var replace = function(phrase, match) { return display[match]; }
		$self.info.template.bar = $self.info.template.bar.replace(/%value:(.+?)%/g, replace); //Replace the placeholders with the constructed values

		//Insert the bar into the body with ID 0 (NOTE : Not using '%index%' to avoid syntax error under IE6)
		$system.node.id([$system.info.id, $id, 'body'].join('_')).innerHTML = $self.info.template.bar.replace(/INDEX/g, '0');

		$self.gui.set(0, callback); //Initialize the main bar

		var load = function(request) //Load the rest of clones
		{
			var list = $system.dom.tags(request.xml, 'bar')[0];
			var bar = $system.dom.attribute(list, 'index').split(',');

			for(var i = 0; i < bar.length; i++) if($system.is.digit(bar[i])) $self.gui.create(bar[i]);
		}

		return $system.network.send($self.info.root + 'server/php/front.php', {task : 'run'}, null, load);
	}
