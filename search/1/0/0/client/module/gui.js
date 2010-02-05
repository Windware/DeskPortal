
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _search; //Currently used search keywords

		var _alert = function(zone, reason) //Show alert on various problems
		{
			var language = $system.language.strings($id);
			zone.innerHTML = '';

			var area = document.createElement('p');
			area.id = $id + '_alert';

			$system.node.text(area, language['alert/' + reason]);
			zone.appendChild(area);

			return false; //Return false to invalidate form submission
		}

		var _display = function(zone, update, log, request) //Display the returned result
		{
			if(!$system.is.element(zone) || !$system.is.object(request)) return false;
			zone.innerHTML = ''; //Clear the field

			$system.node.fade(zone.id, false);

			var result = $system.dom.tags(request.xml, 'app');
			if(!result.length) return _alert(zone, 'none');

			log.user($global.log.info, 'user/search', '', [_search]);
			var language = $system.language.strings($id);

			for(var i = 0; i < result.length; i++)
			{
				var name = $system.dom.attribute(result[i], 'name'); //The reported app/db version
				var amount = $system.dom.attribute(result[i], 'page'); //The reported app/db version

				var app = name.split('_');

				var used = $global.user.used[app[0]]; //The version of the app the user uses
				var major = used.replace(/^(\d+).+/, '$1'); //The major version of the app used

				if(app[1] != major) continue; //If the reported version differs from the currently chosen version, drop

				var list = result[i].childNodes; //Get the hit result information
				var id = app[0] + '_' + used; //The application ID to load

				if(update) var pack = $system.node.id($id + '_section_' + name); //On partial updating
				else //If on initial search
				{
					var header = document.createElement('h3');
					header.id = $id + '_header_' + name;

					$system.node.text(header, $global.app[id].title);
					zone.appendChild(header);

					var pack = document.createElement('ul');
					pack.id = $id + '_section_' + name;
				}

				for(var j = 0; j < list.length; j++)
				{
					if(list[j].nodeType != 1) continue; //Drop any unnecessary nodes
					var extras = {}; //Extra parameters sent

					for(var k = 0; k < list[j].attributes.length; k++)
					{
						var key = list[j].attributes[k].nodeName;
						if(key != 'text') extras[key] = list[j].attributes[k].nodeValue;
					}

					var text = $system.text.escape($system.dom.attribute(list[j], 'text')); //The reference text

					var date = $system.date.create($system.dom.attribute(list[j], 'date'));
					if(date.valid) text += $system.text.format(' <a class="%%_%%">(%%)</a>', [$id, 'date', date.format($global.user.pref.format.date)]);

					var bullet = document.createElement('li');
					var link = document.createElement('a');

					switch(name) //App specific display
					{
						case 'mail_1' : //Put the sender address in the link's tip
							$system.tip.set(link, $system.info.id, 'blank', [language.from + ' : ' + (extras.user ? extras.user + ' (' + extras.from + ')' : extras.from)]);
						break;
					}

					link.innerHTML = text;
					link.onclick = $system.app.method($self.display.load, [app[0], major, list[j].nodeName, extras]);

					bullet.appendChild(link);
					pack.appendChild(bullet);
				}

				if(update) //Move to the top of the app's result on page flip
				{
					var section = $system.node.id($id + '_header_' + name);
					$system.node.id($id + '_result').scrollTop = section.offsetTop - 5; //Scroll a little bit back to be roomy

					return;
				}

				zone.appendChild(pack);

				var page = document.createElement('p'); //Create the page selection line
				page.className = $id + '_paging';

				var alter = function(amount, select, name, _search) //Alter the page flip selection
				{
					var current = Number(select.value);
					select.value = current + amount;

					if(current != select.value) $self.gui.page(_search, name, select.value); //If the selection changed, flip the page
				}

				var select = document.createElement('select'); //Page selections

				$system.tip.set(select, $id, 'page');
				select.setAttribute('onchange', $system.text.format("%%.%%.gui.page('%%', '%%', this.value)", [$global.root, $id, _search, name]));

				var link = document.createElement('a'); //Create the link for previous page

				$system.tip.set(link, $id, 'previous');
				$system.node.text(link, '<< ' + language.previous + ' ');

				link.onclick = $system.app.method(alter, [-1, select, name, _search]);
				page.appendChild(link);

				page.appendChild(document.createTextNode('['));

				for(var j = 1; j <= amount; j++) //Give selectable page listing
				{
					var option = document.createElement('option');
					option.value = j;

					$system.node.text(option, j);
					select.appendChild(option);
				}

				page.appendChild(select);
				page.appendChild(document.createTextNode(' / ' + amount + ']'));

				var link = document.createElement('a'); //Create the link for next page

				$system.tip.set(link, $id, 'next');
				$system.node.text(link, ' ' + language.next + ' >>');

				link.onclick = $system.app.method(alter, [1, select, name, _search]);
				page.appendChild(link);

				zone.appendChild(page);
				$system.node.id($id + '_result').scrollTop = 0; //Scroll to top
			}
		}

		this.page = function(search, name, page) //Change the result page of a section
		{
			var log = $system.log.init(_class + '.page');
			if(!$system.is.text(search) || !$system.is.text(name) || !$system.is.digit(page)) return log.param();

			var zone = $system.node.id($id + '_section_' + name);
			if(!$system.is.element(zone)) return false;

			$system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.page', search : search, area : [name], page : page}, null, $system.app.method(_display, [zone, true, log]));
		}

		this.search = function(form) //Start the search
		{ //TODO - Calculate the percentage of probability sorted (hit / most hit %) and have option only to show higher probability hits
			var log = $system.log.init(_class + '.search');
			if(!$system.is.element(form, 'form')) return log.param();

			var zone = $system.node.id($id + '_result'); //Result display area
			_search = form.search.value;

			if(_search.length <= 1) return _alert(zone, 'length');
			var area = []; //List of targets to search from

			for(var i = 0; i < form.elements.length; i++)
			{
				if(form.elements[i].type != 'checkbox') continue; //Look for checkboxes
				if(form.elements[i].checked) area.push(form.elements[i].name); //Add to the target if checked
			}

			if(!area.length) return _alert(zone, 'select');

			//Send the search request
			$system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.search', area : area, search : _search}, null, $system.app.method(_display, [zone, false, log]));
			return false; //Reject the form's submission
		}
	}
