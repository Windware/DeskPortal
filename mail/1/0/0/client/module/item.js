
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _body = {}; //Message body cache

		var _cache = {}; //Message listing cache

		var _fresh = {}; //Flag to indicate if a folder has been updated

		var _lock = false; //Do not allow updating multiple folders simultaneously

		var _page = {}; //Selected page for each folders

		var _preserve = 5; //Amount of minutes to keep local cache for each folder listings

		var _scroll = {}; //Scroll amount for each folders

		var _timer = {}; //Periodic folder update timer

		var _update = {}; //Flag to indicate that a folder should be updated from the server

		this.get = function(folder, update, callback, request) //Get list of mails of a folder
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.text(folder)) return log.param();

			if(_lock)
			{
				$system.app.callback(_class + '.get', callback);
				return false; //Wait for previous operation
			}

			var table = $system.node.id($id + '_read_zone'); //Mail listing table
			_lock = true; //Wait until loading completes

			if(__selected.folder == folder) _page[folder] = $system.node.id($id + '_show').value; //Get current page selection
			else if(!$system.is.digit(_page[folder])) _page[folder] = 1;

			var previous = __selected; //Remember previous selection
			__selected = {folder : folder, page : _page[folder], marked : __filter.marked, unread : __filter.unread, order : __order.item, reverse : __order.reverse}; //Remember current selection

			var search = $system.node.id($id + '_form').search.value; //Search value

			var expire = function(folder, page, order, reverse) //Expire the cache
			{
				if(__selected.folder != folder || __selected.page != page || __selected.order != order || __selected.reverse != reverse)
					return _update[folder] = true; //If not currently displayed, flag to note that this folder should be updated on next display

				delete _cache[folder][page][order][reverse]; //Remove the cache
				$self.item.get(folder, true); //Update the displaying list
			}

			var list = function(folder, page, order, reverse, marked, unread, search, previous, update, request) //List the mails upon receiving the contents
			{
				if($system.dom.status(request.xml) != 0) return _lock = false; //TODO - Show some error
				_cache[folder][page][order][reverse][marked][unread][search] = request;

				var language = $system.language.strings($id);
				var section = $system.array.list('subject from date'); //List of columns to display

				var max = $system.dom.attribute($system.dom.tags(request.xml, 'page')[0], 'total');
				if(!$system.is.digit(max)) max = 1;

				var zone = $system.node.id($id + '_paging');
				zone.innerHTML = '';

				var select = document.createElement('select');

				select.id = $id + '_show'; //NOTE : Using 'id' instead of 'name' since IE6 cannot set 'name' on a dynamically created select object
				select.onchange = $system.app.method($self.item.get, [__selected.folder]);

				for(var i = 1; i <= max; i++)
				{
					var option = document.createElement('option');

					option.value = i;
					$system.node.text(option, i);

					select.appendChild(option);
				}

				select.value = page;

				zone.appendChild(select);
				zone.appendChild(document.createTextNode(' / ' + max));

				var row = document.createElement('tr');
				row.id = $id + '_read_header';

				for(var i = 0; i < section.length; i++) //Set headers
				{
					var header = document.createElement('th');
					header.className = $id + '_row_' + section[i];

					var sort = section[i] == 'date' ? 'sent' : section[i];
					$system.tip.set(header, $id, 'sort/' + sort);

					header.style.cursor = 'pointer';
					header.onclick = $system.app.method($self.gui.sort, [sort]);

					$system.node.text(header, language[section[i]]);

					var sign = document.createElement('span'); //Create an area to put sort sign
					sign.id = $id + '_sign_' + sort;

					if(sort == __order.item) sign.innerHTML = !__order.reverse ? ' &uarr;' : ' &darr;';

					header.appendChild(sign);
					row.appendChild(header);
				}

				var body = document.createElement('tbody');
				body.appendChild(row);

				var part = ['from', 'to', 'cc'];
				var mail = $system.dom.tags(request.xml, 'mail');

				for(var i = 0; i < mail.length; i++)
				{
					var id = $system.dom.attribute(mail[i], 'id');
					var storage = __mail[id] = {}; //Mail information

					var row = document.createElement('tr');
					row.style.cursor = 'pointer';

					$system.node.hover(row, $id + '_hover'); //Give mouse hovered style
					row.onclick = $system.app.method($self.item.show, [id, row]); //Display the message pane on click

					for(var j = 0; j < mail[i].attributes.length; j++) //Keep mail attributes
					{
						var parameter = mail[i].attributes[j];
						storage[parameter.name] = parameter.value;
					}

					if(storage.read != '1') $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
					if(storage.marked == '1') $system.node.classes(row, $id + '_mail_marked', true); //For marked mails
					if(storage.replied == '1') $system.node.classes(row, $id + '_mail_replied', true); //For replied mails

					for(var j = 0; j < section.length; j++) //For all the columns
					{
						var display = ''; //Parameter to display

						switch(section[j]) //Pick the parameters to display on the interface
						{
							case 'subject' : display = storage[section[j]] || '(' + language.empty + ')'; break;

							case 'date' : display = $system.date.create(storage.sent).format($global.user.pref.format.monthdate); break;

							case 'from' : //Create mail addresses and concatenate
								for(var k = 0; k < part.length; k++)
								{
									var address = $system.dom.tags(mail[i], part[k]);
									storage[part[k]] = [];

									for(var l = 0; l < address.length; l++)
									{
										var real = $system.dom.attribute(address[l], 'address');
										if(!real) continue;

										storage[part[k]].push([real, $system.dom.attribute(address[l], 'name')]);
									}
								}

								display = [];

								for(var k = 0; k < storage.from.length; k++)
								{
									var format = [$system.tip.link($system.info.id, null, 'blank', [$system.text.escape(storage.from[k][0])]), $system.text.escape(storage.from[k][1])];
									display.push(storage.from[k][1] ? $system.text.format('<span style="cursor : help"%%>%%</span>', format) : storage.from[k][0]);
								}

								display = display.join(', ');
							break;
						}

						var cell = document.createElement('td');
						cell.innerHTML = display;

						row.appendChild(cell);
					}

					body.appendChild(row);
				}

				if(previous && $system.is.digit(previous.folder)) //If a folder was previously selected
				{
					if(!_scroll[previous.folder]) _scroll[previous.folder] = {};
					_scroll[previous.folder][previous.page] = {order : previous.order, reverse : previous.reverse, position : table.parentNode.scrollTop}; //Remember the scroll height
				}

				while(table.firstChild) table.removeChild(table.firstChild); //Clean up the listing (innerHTML on table breaks khtml)
				table.appendChild(body);

				var scroll = _scroll[folder] && _scroll[folder][page]; //Check if the scroll position cache is still valid

				if(scroll) //If the scroll position is declared
				{
					scroll = scroll.order == order && scroll.reverse == reverse && scroll.position; //Check if order has changed

					if($system.is.digit(scroll)) table.parentNode.scrollTop = scroll; //Recover the scroll height
					else delete _scroll[folder]; //If any of the display order changes, discard the positions
				}

				if(!scroll) table.parentNode.scrollTop = 0; //Move to top of page

				_lock = false;
				$system.app.callback(_class + '.get', callback);

				if(!_fresh[folder]) //If never updated
				{
					_fresh[folder] = true;
					return $self.item.get(folder, true); //Update it
				}

				if(_update[folder]) //If cache should be updated
				{
					delete _cache[folder]; //Remove the entire cache for the folder
					delete _update[folder];

					return $self.item.get(folder, true); //Update it
				}

				return true;
			}

			//Create cache storage
			if(!_cache[folder]) _cache[folder] = {};
			if(!_cache[folder][_page[folder]]) _cache[folder][_page[folder]] = {};
			if(!_cache[folder][_page[folder]][__order.item]) _cache[folder][_page[folder]][__order.item] = {};

			var hash = _cache[folder][_page[folder]][__order.item]; //A little shortcut

			if(!hash[__order.reverse]) hash[__order.reverse] = {};
			if(!hash[__order.reverse][__filter.marked]) hash[__order.reverse][__filter.marked] = {};
			if(!hash[__order.reverse][__filter.marked][__filter.unread]) hash[__order.reverse][__filter.marked][__filter.unread] = {};

			var items = [folder, _page[folder], __order.item, __order.reverse, __filter.marked, __filter.unread, search, previous, !!update];
			var run = $system.app.method(list, items);

			if($system.is.object(request)) return run(request); //If updating, use the passed object

			var stored = hash[__order.reverse][__filter.marked][__filter.unread][search];
			if(update !== true && $system.is.object(stored)) return run(stored); //If already cached, use it

			if(_timer[folder]) clearTimeout(_timer[folder]);
			_timer[folder] = setTimeout($system.app.method(expire, items), _preserve * 60000); //Update local cache after a period

			var param = {task : 'item.get', folder : folder, page : _page[folder], order : __order.item, reverse : __order.reverse ? 1 : 0, marked : __filter.marked ? 1 : 0, unread : __filter.unread ? 1 : 0, search : search, update : update === true ? 1 : 0};
			return $system.network.send($self.info.root + 'server/php/front.php', param, null, run);
		}

		this.update = function() { return $system.is.digit(__selected.folder) ? $self.item.get(__selected.folder) : false; } //Reload the mails from the server

		this.show = function(id, row, callback) //Display the mail window
		{
			var log = $system.log.init(_class + '.show');

			if(!$system.is.digit(id)) return log.param();
			if(!__mail[id]) return log.user($global.log.warning, 'user/show/error', 'user/show/solution');

			var language = $system.language.strings($id);
			var node = $id + '_mail_' + id;

			if($system.node.id(node))
			{
				if($system.node.hidden(node)) $system.window.raise(node);
				return $system.node.fade(node);
			}

			var display = function(id, callback, request)
			{
				var node = $system.node.id($id + '_mail_' + id + '_body');
				if(!node) return; //If the window has disappeared already, don't bother

				_body[id] = request;
				var body = $system.text.escape($system.dom.text($system.dom.tags(request.xml, 'body')[0])).replace(/\n/g, '<br />\n');

				node.innerHTML = body; //Write out the message body
				$system.app.callback(_class + '.show.display', callback);
			}

			$system.node.classes(row, $id + '_mail_unread', false); //Remove the unread style
			var parameter = __mail[id];

			var section = ['from', 'to', 'cc'];
			var value = {index : id, sent : $system.date.create(parameter.sent).format($global.user.pref.format.full), subject : parameter.subject || '(' + language.empty + ')'};

			var replace = function(phrase, match) { return variable[match]; }

			for(var i = 0; i < section.length; i++)
			{
				var list = parameter[section[i]];
				var address = [];

				if($system.is.array(list))
				{
					for(var j = 0; j < list.length; j++)
					{
						var variable = {address : $system.text.escape(list[j][0]), show : $system.text.escape(list[j][1] ? list[j][1] : list[j][0]), name : list[j][1] ? $system.text.escape(list[j][1]) : ''};
						variable.tip = $system.tip.link($system.info.id, null, 'blank', [$system.text.escape(list[j][0])]);

						address.push($self.info.template.address.replace(/%value:(.+?)%/g, replace));
					}
				}

				value[section[i]] = address.join(' ');
			}

			var replace = function(phrase, match) { return value[match]; }
			var template = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

			$system.window.create(node, $self.info.title, template, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);

			if(_body[id]) return display(id, callback, _body[id]);
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.show', message : id}, null, $system.app.method(display, [id, callback]));
		}
	}
