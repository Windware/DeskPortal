
	$self.item = new function()
	{
		var _class = $id + '.item';

		var _cache = {}; //Message listing cache

		var _drag; //The message drag parameter

		var _float = 7; //Amount of pixels to move to trigger message move instead of displaying message when dragging them

		var _lock; //Mail drag mouse move event throttling lock

		var _page = {}; //Selected page for each folders

		var _preserve = 5; //Amount of minutes to keep local cache for each folder listings

		var _scroll = {}; //Scroll amount for each folders

		var _stack = {}; //List of selected mails

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

		this.click = function(id) //Either start dragging the mail or display the message window
		{
			var log = $system.log.init(_class + '.click');
			if(!$system.is.array(id)) return log.param();

			$system.event.add(document.body, 'onmousemove', $self.item.drag); //Hook the event for mouse move
			$system.event.add(document.body, 'onmouseup', $self.item.drop); //Hook the event for mouse up

			var event = $system.event.source(arguments);
			var position = $system.event.position(event);

			_drag = {id : id, x : position.x, y : position.y};

			$system.browser.deselect(); //Let go of selected text if any
			$system.gui.select(false); //Make sure text selection doesn't work while dragging

			event.cancelBubble = true;
			return false; //Avoid event success
		}

		this.drag = function(event) //Move the selected mails around
		{
			if(_lock) return true; //If throttled, do not execute anything

			_lock = true; //Lock for a short while
			setTimeout(function() { _lock = false; }, $system.gui.interval); //Give a lock to throttle mouse move event from triggering as often as possible

			if(!event) event = window.event; //NOTE : Not using 'event.source' for a possible performance reason
			if($system.browser.engine != 'khtml' && event.button != $system.browser.click.left) return $self.item.drop();

			var current = $system.event.position(event); //Get the cursor position

			if(!_drag.mover)
			{
				if(Math.abs(current.x - _drag.x) >= _float || Math.abs(current.y - _drag.y) >= _float)
				{
					_drag.mover = document.createElement('img');
					_drag.mover.id = $id + '_drag';

					_drag.mover.style.left = current.x + 5 + 'px';
					_drag.mover.style.top = current.y + 5 + 'px';

					$system.image.set(_drag.mover, $self.info.devroot + 'graphic/drag.png');
					document.body.appendChild(_drag.mover);

					_drag.mover.style.zIndex = ++$system.window.depth;
				}
			}
			else
			{
				_drag.mover.style.left = current.x + 5 + 'px';
				_drag.mover.style.top = current.y + 5 + 'px';
			}

			return true;
		}

		this.drop = function() //Move the message to a folder or display the message window
		{
			$system.event.remove(document.body, 'onmousemove', $self.item.drag); //Remove the mouse move event hook
			$system.event.remove(document.body, 'onmouseup', $self.item.drop); //Remove the mouse up event hook

			$system.gui.select(true); //Let text become selectable again
			$system.node.fade($id + '_drag', true, null, true);

			if(!_drag.mover) return $self.gui.show(_drag.id[0]); //If not dragging, display the message
			var list = $system.node.id($id + '_folder').childNodes; //Folder lists

			for(var i = 0; i < list.length; i++)
			{
				//Check which folder is hilighted by style effect
				if(list[i].nodeType != 1 || list[i].nodeName != 'A' || !$system.node.classes(list[i], $id + '_hilight')) continue;

				var target = list[i].id.replace(RegExp('^' + $id + '_folder_'), '');
				if(target != __selected.folder) $self.item.move(_drag.id, target, $system.app.method($self.item.get, [__selected.folder, 0])); //Move the selected mails if targetting another folder

				break;
			}

			_drag = undefined;
			return true;
		}

		this.get = function(folder, update, callback, request) //Get list of mails of a folder
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.text(folder)) return log.param();

			$self.gui.indicator(true); //Show indicator
			$system.node.hide($id + '_mail_empty', true, true); //Remove the empty notification

			var language = $system.language.strings($id);
			var table = $system.node.id($id + '_read_zone'); //Mail listing table

			if(__selected.folder == folder) _page[folder] = $system.node.id($id + '_show').value; //Get current page selection
			else if(!$system.is.digit(_page[folder])) _page[folder] = 1;

			var previous = __selected; //Remember previous selection
			__selected = {account : __selected.account, folder : folder, page : _page[folder], marked : __filter.marked, unread : __filter.unread, order : __order.item, reverse : __order.reverse, search : $system.node.id($id + '_form').search.value}; //Remember current selection

			var expire = function(folder, page, order, reverse) //Expire the cache
			{
				if(__selected.folder != folder || __selected.page != page || __selected.order != order || __selected.reverse != reverse)
					return __update[folder] = true; //If not currently displayed, flag to note that this folder should be updated on next display

				delete _cache[folder][page][order][reverse]; //Remove the cache
				$self.item.get(folder, 1); //Update the displaying list
			}

			var list = function(folder, page, order, reverse, marked, unread, search, previous, update, request) //List the mails upon receiving the contents
			{
				$self.gui.indicator(false); //Remove indicator
				$system.node.hide($id + '_wait', true, true); //Remove the wait message

				var section = $system.array.list('subject to from date'); //List of data to cache
				var cache = _cache[folder][page][order][reverse][marked][unread][search]; //Page listing cache

				if($system.is.object(request)) //If a new data is passed, create the cache
				{
					if($system.dom.status(request.xml) != '0') //Show message on error but do list if any data is returned
					{
						log.user($global.log.error, 'user/item/list/message', 'user/generic/again/solution');
						$system.gui.alert($id, 'user/item/list/title', 'user/item/list/message', 3);
					}

					cache = _cache[folder][page][order][reverse][marked][unread][search] = {list : [], max : $system.dom.attribute($system.dom.tags(request.xml, 'page')[0], 'total')};
					if(!$system.is.digit(cache.max) || !cache.max) cache.max = 1;

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

						var attachment = $system.dom.tags(list[i], 'attachment');
						__mail[id].attachment = [];

						var field = $system.array.list('id name size type');

						for(var j = 0; j < attachment.length; j++) //Store the attachments info
						{
							__mail[id].attachment[j] = {};
							for(var k = 0; k < field.length; k++) __mail[id].attachment[j][field[k]] = $system.dom.attribute(attachment[j], field[k]);
						}

						__mail[id].preview = $system.dom.text($system.dom.tags(list[i], 'preview')[0]);
					}
				}

				//If displaying state changed, do not show it
				if(__selected.folder != folder || __selected.page != page || __selected.marked != marked) return true;
				if(__selected.unread != unread || __selected.order != order || __selected.reverse != reverse || __selected.search != search) return true;

				__current = cache; //Remember current listing

				if(__selected.page > cache.max) //If the chosen page exceeds the max page (Ex : After moving mails to another folder)
				{
					$system.node.id($id + '_show').value = cache.max;
					__selected.page = cache.max;

					return $self.item.update(); //Show earlier page
				}

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

				row.onmousedown = $system.app.method($system.event.cancel, [row]);
				var column = $system.array.list('check subject from date'); //List of columns to display

				for(var i = 0; i < column.length; i++) //Create table header
				{
					var header = document.createElement('th');
					header.className = $id + '_row_' + column[i];

					var sort = column[i] == 'date' ? 'sent' : column[i];
					$system.tip.set(header, $id, 'sort/' + sort);

					header.style.cursor = 'pointer';
					header.onclick = column[i] != 'check' ? $system.app.method($self.gui.sort, [sort]) : $system.app.method($self.gui.check, []);

					$system.node.text(header, language[column[i]]);

					var sign = document.createElement('span'); //Create an area to put sort sign
					sign.id = $id + '_sign_' + sort;

					if(sort == __order.item) sign.innerHTML = !__order.reverse ? ' &uarr;' : ' &darr;';

					header.appendChild(sign);
					row.appendChild(header);
				}

				var body = document.createElement('tbody');
				body.appendChild(row);

				if(!cache.list.length && !$system.node.id($id + '_mail_empty')) //If empty
				{
					var empty = document.createElement('div');
					empty.id = $id + '_mail_empty';

					$system.node.text(empty, '(' + language.empty + ')');
					table.parentNode.appendChild(empty); //Show it is empty
				}

				for(var i = 0; i < cache.list.length; i++)
				{
					var mail = __mail[cache.list[i]]; //Mail information cache
					if(!mail) continue; //If the mail is deleted, ignore

					var row = document.createElement('tr');
					row.id = $id + '_mail_row_' + mail.id;

					row.style.cursor = 'pointer';
					$system.node.hover(row, $id + '_hover'); //Give mouse hovered style

					if(mail.read != '1') $system.node.classes(row, $id + '_mail_unread', true); //For unread mails
					if(mail.marked == '1') $system.node.classes(row, $id + '_mail_marked', true); //For marked mails
					if(mail.replied == '1') $system.node.classes(row, $id + '_mail_replied', true); //For replied mails

					for(var j = 0; j < column.length; j++) //For all the columns
					{
						var display = '';
						var tip = '';

						switch(column[j]) //Pick the parameters to display on the interface
						{
							case 'check' :
								display = $system.text.format('<input id="%id%_mail_%%_check" type="checkbox" onclick="%top%.%id%.item.select(%%, this.checked)%cancel%" />', [mail.id, mail.id]);
								display = $system.text.template(display, $id);

								tip = '';
							break;

							case 'subject' :
								if(mail.attachment.length)
								{
									var file = []; //List of attachment names and sizes
									for(var k = 0; k < mail.attachment.length; k++) file.push(mail.attachment[k].name + ' (' + Math.ceil(mail.attachment[k].size / 1000) + 'KB)');

									var values = [$system.image.source($id, 'attachment.png'), $system.info.id, $system.tip.link($system.info.id, null, 'blank', [file.join('\\n')], true)];
									display = $system.text.format('<img src="%%" style="cursor : help" class="%%_icon"%% /></span> ', values); //Show attachment presence
								}

								display += tip = mail[column[j]] || '(' + language.empty + ')';
							break;

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

						if(column[j] == 'check') //The checkbox cell
						{
							cell.onmousedown = $system.app.method($system.event.cancel, [cell]);
							cell.onclick = $system.app.method($self.item.select, [mail.id]);
						}
						else cell.onmousedown = $system.app.method($self.item.click, [[mail.id]]); //Either start moving mail or display mail content

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

				if(__update[folder]) //If cache should be updated - TODO : May be better to only delete cache for specific setting
				{
					delete _cache[folder]; //Remove the entire cache for the folder
					delete __update[folder];

					return $self.item.get(folder, 1); //Update it
				}

				return true;
			}

			//Create cache storage
			if(!_cache[folder]) _cache[folder] = {};
			if(!_cache[folder][__selected.page]) _cache[folder][__selected.page] = {};
			if(!_cache[folder][__selected.page][__selected.order]) _cache[__selected.folder][__selected.page][__selected.order] = {};

			var hash = _cache[folder][__selected.page][__selected.order]; //A shortcut

			if(!hash[__selected.reverse]) hash[__selected.reverse] = {};
			if(!hash[__selected.reverse][__selected.marked]) hash[__selected.reverse][__selected.marked] = {};
			if(!hash[__selected.reverse][__selected.marked][__selected.unread]) hash[__selected.reverse][__selected.marked][__selected.unread] = {};

			var items = [folder, __selected.page, __selected.order, __selected.reverse, __selected.marked, __selected.unread, __selected.search, previous, update];
			var run = $system.app.method(list, items);

			if($system.is.object(request)) return run(request); //If updating, use the passed object
			if(!$system.is.digit(update) && $system.is.object(hash[__selected.reverse][__selected.marked][__selected.unread][__selected.search])) return run(null); //If already cached, use it

			if(__refresh[folder]) clearTimeout(__refresh[folder]);
			__refresh[folder] = setTimeout($system.app.method(expire, items), _preserve * 60000); //Update local cache after a period

			if(String(update).match(/^1$/) && $system.is.element(table.firstChild) && table.firstChild.childNodes.length == 1) //If no mails are found, warn the user for the wait
			{
				var note = document.createElement('div');
				note.id = $id + '_wait';

				$system.node.text(note, language.wait);
				table.parentNode.appendChild(note);
			}

			var param = {task : 'item.get', folder : folder, page : __selected.page, order : __selected.order, reverse : __selected.reverse ? 1 : 0, marked : __selected.marked ? 1 : 0, unread : __selected.unread ? 1 : 0, search : __selected.search, update : $system.is.digit(update) ? update : 0};
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

		this.move = function(id, folder, callback) //Move the mails to another folder
		{
			var log = $system.log.init(_class + '.move');
			if(!$system.is.array(id) || !$system.is.digit(folder)) return log.param();

			var notify = function(callback)
			{
				__update[folder] = true; //Set the target folder for update on next access
				$self.folder.get(__belong[folder], 1); //Update the new mail counts

				$system.app.callback(_class + '.move.notify', callback);
			}

			var group = false; //Whether to send the other selected mails or not

			for(var i = 0; i < id.length; i++)
			{
				var mail = __mail[id[i]];
				if(!mail) continue;

				$self.item.clear(id[i], mail.folder); //Remove the mail from the folder listing
				$system.node.hide($id + '_mail_row_' + id[i], true, true); //Remove from the interface

				if(_stack[id[i]]) group = true; //If within the selected items, move others too
			}

			if(group)
			{
				for(var index in _stack)
				{
					var mail = __mail[index];
					if(!mail) continue;

					$self.item.clear(index, mail.folder); //Remove the mail from the folder listing
					$system.node.hide($id + '_mail_row_' + index, true, true); //Remove from the interface

					delete _stack[index];
					id.push(index);
				}
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.move'}, {id : id, folder : folder}, $system.app.method(notify, [callback]));
		}

		this.select = function(id, checked) //Add a mail to the selection
		{
			var log = $system.log.init(_class + '.select');
			if(!$system.is.digit(id)) return log.param();

			var box = $system.node.id($id + '_mail_' + id + '_check');
			box.checked = typeof checked == 'boolean' ? checked : !box.checked;

			$system.node.classes($id + '_mail_row_' + id, $id + '_selected', box.checked);

			if(box.checked) _stack[id] = true;
			else delete _stack[id];

			return true;
		}

		this.trash = function(id, index) //Trash mails
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(id) || !$system.is.digit(index)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.remove)) return false;

			var refresh = function(folder, mode) { return __selected.folder == folder ? $self.item.get(folder, mode) : __update[folder] = true; } //Update the folder

			var update = function(account, folder, request)
			{
				if($system.dom.status(request.xml) != '0')
				{
					refresh(folder, 0); //Recover the mail that failed to be trashed
					return log.user($global.log.error, 'user/item/delete', 'user/generic/again/solution');
				}

				refresh(__special.trash[account], 1); //Update the trash
			}

			$system.node.fade($id + '_display_' + index, true, null, true); //Remove mail window
			if(!__mail[id]) return false;

			var account = __mail[id].account; //Account the mail belongs to
			var folder = __mail[id].folder; //Originating folder

			$self.item.clear(id, folder); //Remove the mail from the folder listing
			refresh(folder); //Update the origin folder

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.trash'}, {id : [id]}, $system.app.method(update, [account, folder]));
		}

		this.update = function(mode) { return $system.is.digit(__selected.folder) ? $self.item.get(__selected.folder, mode) : false; } //Reload the mails from the server
	}
