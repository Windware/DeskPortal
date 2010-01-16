
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _cache = {}; //Message listing cache

		var _fresh = {}; //Flag to indicate if a folder has been updated

		var _page = {}; //Selected page for each folders

		var _preserve = 5; //Amount of minutes to keep local cache for each folder listings

		var _scroll = {}; //Scroll amount for each folders

		var _update = {}; //Flag to indicate that a folder should be updated from the server

		this.clear = function(id, folder) //Remove a mail from folder listing cache
		{
			var log = $system.log.init(_class + '.clear');

			if(!$system.is.digit(id) || !$system.is.digit(folder)) return log.param();
			if(!$system.is.object(_cache[folder])) return false;

			var remove = function(cache) //Go through the cache listing
			{
				for(var section in cache)
				{
					if($system.is.array(cache[section])) //On the mail list, remove the mail entry
					{
						for(var i = 0; i < cache[section].length; i++) if(id == cache[section][i]) return delete cache[section][i];
					}
					else if($system.is.object(cache[section])) remove(cache[section]);
				}
			}

			return remove(_cache[folder]); //NOTE : Likely to return 'undefined'
		}

		this.create = function(account, to, cc, bcc, subject, message) //Create a new mail
		{
			alert(to);
		}

		this.edit = function(id) //Edit a mail
		{
			alert(id);
		}

		this.get = function(folder, update, callback, request) //Get list of mails of a folder
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.text(folder)) return log.param();

			$self.gui.indicator(true); //Show indicator

			var language = $system.language.strings($id);
			var table = $system.node.id($id + '_read_zone'); //Mail listing table

			if(__selected.folder == folder) _page[folder] = $system.node.id($id + '_show').value; //Get current page selection
			else if(!$system.is.digit(_page[folder])) _page[folder] = 1;

			var previous = __selected; //Remember previous selection
			__selected = {account : __selected.account, folder : folder, page : _page[folder], marked : __filter.marked, unread : __filter.unread, order : __order.item, reverse : __order.reverse, search : $system.node.id($id + '_form').search.value}; //Remember current selection

			var expire = function(folder, page, order, reverse) //Expire the cache
			{
				if(__selected.folder != folder || __selected.page != page || __selected.order != order || __selected.reverse != reverse)
					return _update[folder] = true; //If not currently displayed, flag to note that this folder should be updated on next display

				delete _cache[folder][page][order][reverse]; //Remove the cache
				$self.item.get(folder, true); //Update the displaying list
			}

			var list = function(folder, page, order, reverse, marked, unread, search, previous, update, request) //List the mails upon receiving the contents
			{
				$self.gui.indicator(false); //Remove indicator
				$system.node.hide($id + '_wait', true, true); //Remove the wait message

				if($system.dom.status(request.xml) != '0') //Show message on error but do list if any data is returned
				{
					log.user($global.log.error, 'user/item/list', 'user/generic/again/solution');
					//TODO - $system.gui.alert();
				}

				var section = $system.array.list('subject to from date'); //List of data to cache
				var cache = _cache[folder][page][order][reverse][marked][unread][search]; //Page listing cache

				if($system.is.object(request)) //If a new data is passed, create the cache
				{
					cache = _cache[folder][page][order][reverse][marked][unread][search] = {list : [], max : $system.dom.attribute($system.dom.tags(request.xml, 'page')[0], 'total')};
					if(!$system.is.digit(cache.max)) cache.max = 1;

					var list = $system.dom.tags(request.xml, 'mail');

					for(var i = 0; i < list.length; i++)
					{
						var id = $system.dom.attribute(list[i], 'id');
						cache.list.push(id); //List the mails in the page cache

						__mail[id] = {id : id, folder : folder};

						for(var j = 0; j < list[i].attributes.length; j++) //Keep mail attributes
						{
							var parameter = list[i].attributes[j];
							__mail[id][parameter.name] = parameter.value;
						}

						for(var j = 0; j < section.length; j++) //For all the columns
						{
							switch(section[j])
							{
								case 'from' : case 'to' : case 'cc' :
									var address = $system.dom.tags(list[i], section[j]);
									__mail[id][section[j]] = [];

									for(var k = 0; k < address.length; k++) //Store all the address fields
										__mail[id][section[j]].push({address : $system.dom.attribute(address[k], 'address'), name : $system.dom.attribute(address[k], 'name')});
								break;
							}
						}

						__mail[id].preview = $system.dom.text($system.dom.tags(list[i], 'preview')[0]);
					}
				}

				//If displaying state changed, do not show it
				if(__selected.folder != folder || __selected.page != page || __selected.marked != marked) return true;
				if(__selected.unread != unread || __selected.order != order || __selected.reverse != reverse || __selected.search != search) return true;

				var zone = $system.node.id($id + '_paging');
				zone.innerHTML = '';

				var select = document.createElement('select');

				select.id = $id + '_show'; //NOTE : Using 'id' instead of 'name' since IE6 cannot set 'name' on a dynamically created select object
				select.onchange = $system.app.method($self.item.get, [__selected.folder]);

				for(var i = 1; i <= cache.max; i++)
				{
					var option = document.createElement('option');

					option.value = i;
					$system.node.text(option, i);

					select.appendChild(option);
				}

				select.value = page;
				zone.appendChild(select);

				var row = document.createElement('tr');
				row.id = $id + '_read_header';

				var column = $system.array.list('subject from date'); //List of columns to display

				for(var i = 0; i < column.length; i++) //Create table header
				{
					var header = document.createElement('th');
					header.className = $id + '_row_' + column[i];

					var sort = column[i] == 'date' ? 'sent' : column[i];
					$system.tip.set(header, $id, 'sort/' + sort);

					header.style.cursor = 'pointer';
					header.onclick = $system.app.method($self.gui.sort, [sort]);

					$system.node.text(header, language[column[i]]);

					var sign = document.createElement('span'); //Create an area to put sort sign
					sign.id = $id + '_sign_' + sort;

					if(sort == __order.item) sign.innerHTML = !__order.reverse ? ' &uarr;' : ' &darr;';

					header.appendChild(sign);
					row.appendChild(header);
				}

				var body = document.createElement('tbody');
				body.appendChild(row);

				for(var i = 0; i < cache.list.length; i++)
				{
					var mail = __mail[cache.list[i]]; //Mail information cache
					if(!mail) continue; //If the mail is deleted, ignore

					var row = document.createElement('tr');
					row.id = $id + '_mail_row_' + mail.id;

					row.style.cursor = 'pointer';
					$system.node.hover(row, $id + '_hover'); //Give mouse hovered style

					row.onclick = $system.app.method($self.item.show, [mail.id]); //Display the message pane on click

					if(mail.read != '1') $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
					if(mail.marked == '1') $system.node.classes(row, $id + '_mail_marked', true); //For marked mails
					if(mail.replied == '1') $system.node.classes(row, $id + '_mail_replied', true); //For replied mails

					for(var j = 0; j < column.length; j++) //For all the columns
					{
						var display, tip;

						switch(column[j]) //Pick the parameters to display on the interface
						{
							case 'subject' : display = tip = mail[column[j]] || '(' + language.empty + ')'; break;

							case 'date' : display = tip = $system.date.create(mail.sent).format($global.user.pref.format.monthdate); break;

							case 'from' : case 'to' : case 'cc' : //Create mail addresses and concatenate
								display = [];
								tip = [];

								for(var k = 0; k < mail[column[j]].length; k++)
								{
									var address = mail[column[j]][k];
									var format = [$system.tip.link($system.info.id, null, 'blank', [$system.text.escape(address.address)]), $system.text.escape(address.name)];

									display.push(address.name ? $system.text.format('<span style="cursor : help"%%>%%</span>', format) : address.address);
									tip.push(address.name ? address.name + ' (' + address.address + ')' : address.address);
								}

								display = display.join(', ');
								tip = tip.join(', ');
							break;

							default : continue; break;
						}

						var cell = document.createElement('td');
						cell.innerHTML = display;

						row.appendChild(cell);
					}

					$system.tip.set(row, $system.info.id, 'blank', [$system.text.escape(mail.preview) || '(' + language.empty + ')'], true); //Give message preview tip
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
				$system.app.callback(_class + '.get', callback);

				if(!_fresh[folder]) //If never updated
				{
					_fresh[folder] = true;
					return __account[__selected.account].type == 'pop3' && __special.inbox[__selected.account] != folder ? true : $self.item.get(folder, true); //Update it
				}

				if(_update[folder]) //If cache should be updated - TODO : May be better to only delete cache for specific setting
				{
					delete _cache[folder]; //Remove the entire cache for the folder
					delete _update[folder];

					return $self.item.get(folder, __account[__selected.account].type != 'pop3'); //Update it
				}

				return true;
			}

			//Create cache storage
			if(!_cache[folder]) _cache[folder] = {};
			if(!_cache[folder][__selected.page]) _cache[folder][__selected.page] = {};
			if(!_cache[folder][__selected.page][__selected.order]) _cache[__selected.folder][__selected.page][__selected.order] = {};

			var hash = _cache[folder][__selected.page][__selected.order]; //A little shortcut

			if(!hash[__selected.reverse]) hash[__selected.reverse] = {};
			if(!hash[__selected.reverse][__selected.marked]) hash[__selected.reverse][__selected.marked] = {};
			if(!hash[__selected.reverse][__selected.marked][__selected.unread]) hash[__selected.reverse][__selected.marked][__selected.unread] = {};

			var items = [folder, __selected.page, __selected.order, __selected.reverse, __selected.marked, __selected.unread, __selected.search, previous, !!update];
			var run = $system.app.method(list, items);

			if($system.is.object(request)) return run(request); //If updating, use the passed object
			if(update !== true && $system.is.object(hash[__selected.reverse][__selected.marked][__selected.unread][__selected.search])) return run({}); //If already cached, use it

			if(__refresh[folder]) clearTimeout(__refresh[folder]);
			__refresh[folder] = setTimeout($system.app.method(expire, items), _preserve * 60000); //Update local cache after a period

			if(update === true && $system.is.element(table.firstChild) && table.firstChild.childNodes.length == 1) //If no mails are found, warn the user for the wait
			{
				var note = document.createElement('div');
				note.id = $id + '_wait';

				$system.node.text(note, language.wait);
				table.parentNode.appendChild(note);
			}

			var param = {task : 'item.get', folder : folder, page : __selected.page, order : __selected.order, reverse : __selected.reverse ? 1 : 0, marked : __selected.marked ? 1 : 0, unread : __selected.unread ? 1 : 0, search : __selected.search, update : update === true ? 1 : 0};
			return $system.network.send($self.info.root + 'server/php/front.php', param, null, run);
		}

		this.mark = function(id, mode) //Flag a mail as marked or not
		{
			var log = $system.log.init(_class + '.mark');
			if(!$system.is.array(id)) return log.param();

			var notify = function(request) //Show result
			{
				if($system.dom.status(request.xml) == '0')
				{
					var message = [$global.log.notice, 'user/item/mark'];

					__mail[id].marked = mode ? 1 : 0;
					$system.node.classes($id + '_mail_row_' + id, $id + '_mail_marked', !!mode); //Change the style
				}
				else var message = [$global.log.error, 'user/item/mark/error', 'user/generic/again/solution'];

				log.user(message[0], message[1], message[2]);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.mark'}, {id : id, mode : mode ? 1 : 0}, notify);
		}

		this.trash = function(id, account) //Trash mails
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.array(id)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.remove)) return false;

			var update = function(request)
			{
				if($system.dom.status(request.xml) != '0')
				{
					$self.item.update(true); //Recover the mail that failed to be trashed
					return log.user($global.log.error, 'user/item/delete', 'user/generic/again/solution');
				}
			}

			$system.node.fade($id + '_mail_' + id, true, null, true); //Remove mail window

			var mail = __mail[id];
			$self.item.clear(id, mail.folder); //Remove the mail from the folder listing

			$self.item.update();

			if(mail) _update[__special.trash[mail.account]] = true; //Set the trash for update on next access
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.trash'}, {id : id, account : $system.is.digit(account) ? account : ''}, update);
		}

		this.update = function(update) { return $system.is.digit(__selected.folder) ? $self.item.get(__selected.folder, update) : false; } //Reload the mails from the server

		this.show = function(id) //Display the mail window
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

			$system.node.classes($id + '_mail_row_' + id, $id + '_mail_unread', false); //Remove the unread style
			__mail[id].read = '1';

			var section = ['from', 'to', 'cc'];
			var value = {index : id, sent : $system.date.create(__mail[id].sent).format($global.user.pref.format.full), subject : __mail[id].subject || '(' + language.empty + ')'};

			if(__mail[id].marked == '1') value.marked = ' checked="checked"';
			else value.unmarked = ' checked="checked"';

			var replace = function(phrase, match) { return variable[match] || ''; }

			for(var i = 0; i < section.length; i++)
			{
				var list = __mail[id][section[i]];
				var address = [];

				if($system.is.array(list))
				{
					for(var j = 0; j < list.length; j++)
					{
						var variable = {address : $system.text.escape(list[j].address), show : $system.text.escape(list[j].name ? list[j].name : list[j].address), name : list[j].name ? $system.text.escape(list[j].name) : ''};
						variable.tip = $system.tip.link($system.info.id, null, 'blank', [$system.text.escape(list[j].address)]);

						address.push($self.info.template.address.replace(/%value:(.+?)%/g, replace));
					}
				}

				value[section[i]] = address.join(', ');
			}

			value.body = $system.network.form($self.info.root + 'server/php/front.php?task=item.show&message=' + id);

			var replace = function(phrase, match) { return value[match] || ''; }
			var template = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

			return $system.window.create(node, $self.info.title, template, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);
		}
	}
