
	$self.conf = new function() //Configuration panel related
	{
		var _class = $id + '.conf';

		var _previous = {}; //Previously selected info tab

		var _shown = {}; //Currently displayed configuration field

		var _run = {}; //Flag to see if the initialization code already run on the configuration tabs

		var _viewed; //Currnetly displayed page for manual

		this.apply = function(id, form) //Apply change to the general configuration panel
		{
			var log = $system.log.init(_class + '.apply');
			if(!$system.is.id(id) || !$system.is.element(form, 'form')) return log.param();

			var notify = function(request)
			{
				if(request.code === 0)
				{
					var title = 'user/conf/changed/title';
					var message = 'user/conf/changed/message';
				}
				else
				{
					var title = 'user/conf/error/title';
					var message = 'user/conf/error/message';
				}

				$system.gui.alert($id, title, message);
			}

			$system.network.send($system.info.root + 'server/php/front.php', {task : 'conf.apply'}, {name : id.replace(/(_\d+){3}$/, ''), version : form.version.value}, notify);
			return false; //Invalidate form submission
		}

		this.change = function(id, section) //Switch to another configuration tab
		{
			var log = $system.log.init(_class + '.change');
			if(!$system.is.id(id) || !$system.is.text(section)) return log.param();

			if(_shown[id])
			{
				$system.node.hide([$id, 'conf', id, 'field', _shown[id]].join('_'), true);

				var tab = [$id, 'conf', id, 'tab', _shown[id]].join('_');
				$system.node.classes(tab, $id + '_configure_tab_active', false);
			}

			if(!_run[id]) _run[id] = {}; //Hash to store displayed states

			if(!_run[id][section] && $global.top[id] && $global.top[id].conf && typeof $global.top[id].conf[section] == 'function')
			{
				try { $global.top[id].conf[section](); } //Run the custom configuration code

				catch(error) { log.dev($global.log.error, 'dev/tool/script', 'dev/check', [id, section, 'configuration', $system.browser.report(error)]); }

				_run[id][section] = true; //Save the run state
			}

			$system.node.hide([$id, 'conf', id, 'field', section].join('_'), false);

			var tab = [$id, 'conf', id, 'tab', section].join('_');
			$system.node.classes(tab, $id + '_configure_tab_active', true);

			_shown[id] = section; //Remember the currently shown tab
		}

		this.flip = function(id, section, page) //Flip a page of a manual
		{
			var log = $system.log.init(_class + '.flip');
			if(!$system.is.id(id) || !$system.node.id(id) || !$system.is.text(section) || !$system.is.text(page)) return log.param();

			var node = [$system.info.id, id, 'info_page'].join('_');
			var element = $system.node.id(node);

			if(!element) return log.dev($global.log.error, 'dev/tool/element', 'dev/exist', [node]);
			var info = __conf.pages[id];

			if(info && info[section] && info[section][page]) element.innerHTML = info[section][page];
			else return log.dev($global.log.error, 'dev/tool/page', 'dev/tool/page/solution', [[id, section, page].join (' - ')]);

			if(_viewed) $system.node.classes(_viewed, $system.info.id + '_viewed', false); //Revert style class for the previous page link
			element.scrollTop = 0; //Scroll back to top

			if($global.top[id] && $global.top[id].manual && typeof $global.top[id].manual[page] == 'function')
			{
				try { $global.top[id].manual[page](); } //Run the custom script for the manual page

				catch(error) { log.dev($global.log.error, 'dev/tool/script', 'dev/check', [id, page, 'manual', $system.browser.report(error)]); }
			}

			_viewed = [$system.info.id, 'page', id, section, page].join('_'); //Remember the selection
			$system.node.classes(_viewed, $system.info.id + '_viewed', true); //Set style class for the current page link
		}

		this.swap = function(id, panel) //Swap the information window content
		{
			var log = $system.log.init(_class + '.swap');
			if(!$system.is.id(id) || !$system.is.text(panel)) return log.param();

			var node = [$id, id, 'info', panel].join('_'); //Content area ID

			if(!$system.node.id([$id, id, 'tab', panel].join('_'))) //Check if the tab exists
				return log.dev($global.log.warning, 'dev/tool/tab', 'dev/tool/tab/solution', [panel, id]);

			if(_previous[id])
			{
				if(_previous[id].node == node) return true;

				$system.node.hide(_previous[id].node); //Hide the previous tab
				$system.node.classes(_previous[id].tab, $id + '_tab_active', false);

				var image = $system.node.id(_previous[id].tab).firstChild;
				$system.image.set(image, $system.info.devroot + 'graphic/' + image.name + '_grey.png');
			}

			_previous[id] = {node : node, tab : [$id, id, 'tab', panel].join('_')};
			$system.node.classes(_previous[id].tab, $id + '_tab_active', true);

			var image = $system.node.id(_previous[id].tab).firstChild;
			$system.image.set(image, $system.info.devroot + 'graphic/' + image.name + '.png');

			if($system.node.id(node)) return $system.node.hide(node); //If it exists, swap the visibility

			var component = $system.app.component(id);
			var info = $global.top[id].info; //App's info node

			var padding = 10; //Padding for the page

			var content = ''; //Body to display
			var initial; //Function to display the initial page

			switch(panel)
			{
				case 'about' : //Build up the app's information from source files
					var replace = function(phrase, match)
					{
						switch(match)
						{
							case 'desc' : return info.desc.replace(/\n/g, '<br />\n'); break; //Application description

							case 'developer' : //Developers
								var people = $system.dom.tags(info.meta, match);
								var list = '';

								for(var i = 0; i < people.length; i++) //Make name and a email address tip
								{
									var mail = $system.dom.attribute(people[i], 'contact');
									mail = mail ? $system.tip.link($system.info.id, null, 'blank', [mail]) : '';

									var web = $system.dom.attribute(people[i], 'site');

									var values = [web, $system.tip.link($system.info.id, null, 'blank', [web]), language.web];
									web = web ? $system.text.format(' (<a href="%%" target="_blank"%%>%%</a>)', values) : '';

									var cursor = mail ? ' style="cursor : help"' : '';
									var values = [cursor, mail, $system.dom.attribute(people[i], 'name'), web];

									list += $system.text.format('<p><span%%%%>%%</span>%%</p>', values);
								}

								return list ? list : language.none;
							break;

							case 'country' : //Country specifier
								var country = $system.dom.tags(info.meta, match);
								var description = $system.language.strings($id, 'country.xml');

								var list = '';

								for(var i = 0; i < country.length; i++)
									list += '<p>' + description[$system.dom.attribute(country[i], 'name')] + '</p>';

								return list ? list : language.all;
							break;

							case 'translation' : //Available languages
								var translation = $system.dom.tags(info.meta, 'language');
								var description = $system.language.strings($id, 'language.xml');

								var list = '';

								for(var i = 0; i < translation.length; i++)
								{
									list += '<h3 class="' + $system.info.id + '_language">' + description[$system.dom.attribute(translation[i], 'name')] + '</h3>';
									var author = $system.dom.tags(translation[i], 'translator');

									for(var j = 0; j < author.length; j++)
									{
										var mail = $system.dom.attribute(author[j], 'contact');
										mail = mail ? $system.tip.link($system.info.id, null, 'blank', [mail]) : '';

										var web = $system.dom.attribute(author[j], 'site');

										var values = [web, $system.tip.link($system.info.id, null, 'blank', [web]), language.web];
										web = web ? $system.text.format(' (<a href="%%" target="_blank"%%>%%</a>)', values) : '';

										var cursor = mail ? ' style="cursor : help"' : '';
										var values = [cursor, mail, $system.dom.attribute(author[j], 'name'), web];

										list += $system.text.format('<p><span%%%%>%%</span>%%</p>', values);
									}
								}

								return list;
							break;

							case 'version' : return info.version.replace(/_/g, '.'); break; //Application version
						}
					}

					var language = $system.language.strings($id);
					var name = $system.language.strings($id, 'language.xml'); //Language names

					content = $system.info.template[panel].replace(/%value:(.+?)%/g, replace); //Replace the placeholders
				break;

				case 'conf' : //Configuration panel
					//Get all of the template from the application
					var request = $system.network.item(info.devroot + 'template/conf/', true);
					var length = parseInt(100 / (request.length + 1), 10) + '%';

					var regions = ''; //Template placeholders
					var tabs = '';

					for(var i = 0; i < request.length; i++) //For all of the configuration templates
					{
						if(!request[i].valid()) return log.dev($global.log.error, 'dev/tool/conf', 'dev/tool/conf/solution', [request[i].file]);

						var localized = $system.language.strings(id, 'conf.xml'); //Load application localized strings
						var name = request[i].address.replace(/.+\/(.+)\.[^\.]+$/, '$1'); //Name of the configuration file

						var html = '<div id="%system%_conf_%id%_field_%%" class="%system%_hidden">' + request[i].text + '</div>\n';
						regions += $system.text.format($system.language.apply(id, html, 'conf.xml'), [name]);

						html = '<td id="%system%_conf_%id%_tab_%section%" style="width : %length%" onclick="%top%.%system%.conf.change(\'%id%\', \'%section%\')" class="%system%_configure_tab"%lock%>%name%</td>';
						tabs += $system.text.format($system.text.template(html, id), {section : name, length : length, name : (localized[name] || name)});
					}

					var available = ''; //List of available version options

					var app = $system.app.component(id)[0]; //Name of the application
					var list = $global.app[app].version; //Get available versions

					for(var major in list) for(var minor in list[major]) for(var i = 0; i < list[major][minor]; i++) //Craft the version list
						available += $system.text.format('<option value="%%">%%</option>', [[major, minor, i].join('_'), [major, minor, i].join('.')]);

					content = $system.language.apply(id, $system.info.template[panel], 'conf.xml'); //Create the pane from the template
					content = $system.text.replace(content, $system.array.list('%app% %tabs% %regions% %versions%'), [id, tabs, regions, available]);

					initial = $system.app.method($system.conf.change, [id, 'general']); //Show the general config tab
				break;

				case 'history' : //Load the version history
					padding = 0; //Strip padding for histories, since the inner node will have the margins

					var app = $system.app.component(id); //Name of the application
					var selector = document.createElement('select');

					if(!$global.app[app[0]]) $global.app[app[0]] = {};

					if(!$global.app[app[0]].version) //If version info is not found, load it
					{
						var request = $system.network.send($system.info.root + 'server/php/front.php', {task : 'conf.swap', app : app[0]}, null, null); //TODO - Should be asynchronized
						var available = $system.dom.tags(request.xml, 'version'); //Get available versions

						var list = $global.app[app[0]].version = {}; //Dump the versions in the hash

						for(var i = 0; i < available.length; i++)
						{
							var major = $system.dom.attribute(available[i], 'major');
							var minor = $system.dom.attribute(available[i], 'minor');
							var rev = $system.dom.attribute(available[i], 'revisions');

							if(!list[major]) list[major] = {};
							list[major][minor] = rev;
						}
					}

					var version = $global.app[app[0]].version;

					for(var major in version)
					{
						for(var minor in version[major])
						{
							for(var i = 0; i < version[major][minor]; i++)
							{
								var option = document.createElement('option');
								option.value = [major, minor, i].join('_');

								$system.node.text(option, [major, minor, i].join('.'));
								selector.appendChild(option);
							}
						}
					}

					content = $system.info.template.history.replace('%value:option%', selector.innerHTML).replace(/%value:id%/g, id);
					initial = $system.app.method($system.tool.history, [id, app[1]]); //Show the current version history
				break;

				case 'manual' : //For documents requiring custom pages, build the index
					padding = 0; //Strip padding for manual page

					var index = document.createElement('div'); //Create index link area
					var container = document.createElement('ol');

					var language = $system.language.strings(id, panel + '.xml');

					for(var name in __conf.pages[id][panel])
					{
						var dot = document.createElement('li');
						var link = document.createElement('a');

						link.id = [$system.info.id, 'page', id, panel, name].join('_');
						$system.node.text(link, language[name] || name);

						var variables = [$global.root, $system.info.id, id, panel, name]; //Set as an attribute to keep it inside 'innerHTML'
						link.setAttribute('onclick', $system.text.format("%%.%%.conf.flip('%%', '%%', '%%')", variables));

						if(!initial) initial = $system.app.method($system.conf.flip, [id, panel, name]); //Set the function for initial page

						dot.appendChild(link);
						container.appendChild(dot);
					}

					index.appendChild(container);
					content = $system.info.template.page.replace('%index%', index.innerHTML); //Apply the index list
				break;

				default : return false; break; //Quit as error for wrong pages
			}

			var zone = document.createElement('table'); //Create the tab content

			zone.id = node;
			zone.className = $system.info.id + '_full';

			zone.cellPadding = padding;
			zone.cellSpacing = 0;

			var body = document.createElement('tbody');
			body.className = $system.info.id + '_full';

			var row = document.createElement('tr');
			var cell = document.createElement('td');

			cell.className = $system.info.id + '_info_zone ' + $system.info.id + '_full';

			var source = [/%id%/g, /%name%/g, /%app%/g, /%version%/g, /%browser%/g]; //Replace the variables
			var replacer = [id, $global.top[id].info.title, component[0], component[1], navigator.userAgent];

			cell.innerHTML = $system.text.replace(content, source, replacer);

			row.appendChild(cell);
			body.appendChild(row);
			zone.appendChild(body);

			$system.node.id([$id, id, 'info_content'].join('_')).appendChild(zone); //Attach the node
			if(typeof initial == 'function') initial(); //Run the page initialization function

			return true;
		}
	}
