
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _body = {}; //Message body cache

		var _cache = {}; //Message listing cache

		var _fresh = {}; //Flag to indicate if a folder has been updated

		var _page = {}; //Selected page for each folders

		var _preserve = 5; //Amount of minutes to keep local cache for each folder listings

		var _scroll = {}; //Scroll amount for each folders

		var _update = {}; //Flag to indicate that a folder should be updated from the server

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

			var expire = function(length, folder, page, order, reverse) //Expire the cache
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

				if($system.dom.status(request.xml) != 0) return false; //TODO - Show some error
				_cache[folder][page][order][reverse][marked][unread][search] = request;

				//If displaying state changed, do not show it
				if(__selected.folder != folder || __selected.page != page || __selected.marked != marked) return true;
				if(__selected.unread != unread || __selected.order != order || __selected.reverse != reverse || __selected.search != search) return true;

				var section = $system.array.list('subject to from date'); //List of data to use
				var detail = $system.array.list('to'); //List of tips to display
				var column = $system.array.list('subject from date'); //List of columns to display

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

				var row = document.createElement('tr');
				row.id = $id + '_read_header';

				for(var i = 0; i < column.length; i++) //Set headers
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

					var info = ''; //Tip to display when mouse is over

					for(var j = 0; j < section.length; j++) //For all the columns
					{
						var display, tip;

						switch(section[j]) //Pick the parameters to display on the interface
						{
							case 'subject' : display = tip = storage[section[j]] || '(' + language.empty + ')'; break;

							case 'date' : display = tip = $system.date.create(storage.sent).format($global.user.pref.format.monthdate); break;

							case 'from' : case 'to' : case 'cc' : //Create mail addresses and concatenate
								var address = $system.dom.tags(mail[i], section[j]);
								storage[section[j]] = [];

								for(var k = 0; k < address.length; k++)
								{
									var real = $system.dom.attribute(address[k], 'address');
									if(real) storage[section[j]].push([real, $system.dom.attribute(address[k], 'name')]);
								}

								display = [];
								tip = [];

								for(var k = 0; k < storage[section[j]].length; k++)
								{
									var address = storage[section[j]][k];
									var format = [$system.tip.link($system.info.id, null, 'blank', [$system.text.escape(address[0])]), $system.text.escape(address[1])];

									display.push(address[1] ? $system.text.format('<span style="cursor : help"%%>%%</span>', format) : address[0]);
									tip.push(address[1] ? address[1] + ' (' + address[0] + ')' : address[0]);
								}

								display = display.join(', ');
								tip = tip.join(', ');
							break;

							default : continue; break;
						}

						if($system.array.find(detail, section[j])) info += $system.text.format('<strong>%%</strong> : %%\n', [language[section[j]], tip]); //Create row tip
						if(!$system.array.find(column, section[j])) continue; //If not defined to be a column cell, don't crete cell for that item

						var cell = document.createElement('td');
						cell.innerHTML = display;

						row.appendChild(cell);
					}

					$system.tip.set(row, $system.info.id, 'blank', [info], true);
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
			if(!_cache[folder][__selected.page]) _cache[folder][__selected.page] = {};
			if(!_cache[folder][__selected.page][__selected.order]) _cache[__selected.folder][__selected.page][__selected.order] = {};

			var hash = _cache[folder][__selected.page][__selected.order]; //A little shortcut

			if(!hash[__selected.reverse]) hash[__selected.reverse] = {};
			if(!hash[__selected.reverse][__selected.marked]) hash[__selected.reverse][__selected.marked] = {};
			if(!hash[__selected.reverse][__selected.marked][__selected.unread]) hash[__selected.reverse][__selected.marked][__selected.unread] = {};

			var items = [folder, __selected.page, __selected.order, __selected.reverse, __selected.marked, __selected.unread, __selected.search, previous, !!update];
			var run = $system.app.method(list, items);

			if($system.is.object(request)) return run(request); //If updating, use the passed object

			var stored = hash[__selected.reverse][__selected.marked][__selected.unread][__selected.search];
			if(update !== true && $system.is.object(stored)) return run(stored); //If already cached, use it

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
				var message = $system.dom.status(request.xml) == '0' ? [$global.log.notice, 'user/item/mark'] : [$global.log.error, 'user/item/mark/error', 'user/generic/solution'];
				log.user(message[0], message[1], message[2]);
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.mark'}, {id : id, mode : mode ? 1 : 0}, notify);
		}

		this.trash = function(id, account) //Trash mails
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.array(id)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.trash)) return false;

			var update = function(request)
			{
				if($system.dom.status(request.xml) != 0) return log.user($global.log.error, 'user/item/delete', 'user/generic/solution');
				$system.node.fade($id + '_mail_' + id); //Remove mail window
				//TODO - Remove it off the list locally without updating from server
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.trash'}, {id : id, account : $system.is.digit(account) ? account : ''}, update);
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

			$system.node.classes(row, $id + '_mail_unread', false); //Remove the unread style
			var parameter = __mail[id];

			var section = ['from', 'to', 'cc'];
			var value = {index : id, sent : $system.date.create(parameter.sent).format($global.user.pref.format.full), subject : parameter.subject || '(' + language.empty + ')'};

			if(parameter.marked == '1') value.marked = ' checked="checked"';
			else value.unmarked = ' checked="checked"';

			var replace = function(phrase, match) { return variable[match] || ''; }

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

				value[section[i]] = address.join(', ');
			}

			value.body = $system.network.form($self.info.root + 'server/php/front.php?task=item.show&message=' + id);

			var replace = function(phrase, match) { return value[match] || ''; }
			var template = $self.info.template.mail.replace(/%value:(.+?)%/g, replace);

			$system.window.create(node, $self.info.title, template, 'cccccc', 'ffffff', '000000', '333333', false, undefined, undefined, 600, undefined, false, true, true, null, null, true);
		}
	}
