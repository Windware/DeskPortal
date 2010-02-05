
	$self.item = new function()
	{
		var _class = $id + '.item';

		this.remove = function(year, month, day) //Removes the schedule off a day
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.digit(year) || !$system.is.digit(month) || !$system.is.digit(day)) return log.param();

			var language = $system.language.strings($id);
			if(!confirm(language.confirm.replace('%%', day))) return false;

			var index = __index(year, month, day);
			$system.window.fade([$id, 'edit', index].join('_'), true, null, true); //Remove the day's schedule register window entirely

			delete __opened[index]; //Remove from the list of opened dates
			delete __schedules[index]; //Remove from the internal schedule list

			var update = function(request)
			{
				var date = $system.date.create([year, month, day]);
				log.user($global.log.notice, 'user/remove', '', [date.format($global.user.pref.format.date)]);

				var cell = $system.node.id([$id, 'date', index].join('_'));
				if(!cell) return; //If the displayed month has switched, do not try to update the date cell

				//Unregister from a schedule set day
				cell.style.backgroundColor = '';
				$system.node.classes(cell, $id + '_registered', false);

				$system.tip.remove(cell); //Remove the summary tip
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.remove'}, {year : year, month : month, day : day}, update);
		}

		this.set = function(year, month, day, discard, initial) //Apply the edited information for the day
		{
			var log = $system.log.init(_class + '.set');
			if(!$system.is.digit(year) || !$system.is.digit(month) || !$system.is.digit(day)) return log.param();

			var form = __node(year, month, day, 'form');
			if(!$system.is.element(form, 'form')) return log.param();

			if(!form.title.value) return $system.gui.alert($id, 'user/title', 'user/title/solution', 3);

			if(!form.start_hour.value != !form.start_minute.value || !form.end_hour.value != !form.end_minute.value)
				return $system.gui.alert($id, 'user/date', 'user/date/solution', 3); //Avoid having only part of a time set

			var date = $system.date.create([year, month, day]);

			if(discard !== true)
			{
				//Set the read only text values
				$system.node.text(__node(year, month, day, 'title'), form.title.value);
				__node(year, month, day, 'content').innerHTML = $system.text.escape(form.content.value).replace(/\n/g, '<br />\n');

				var full = [String(year), month < 10 ? '0' + month : month, day < 10 ? '0' + day : day].join('-'); //The full date format
				var post = {day : full, start : '', end : ''}; //Send the form parameters

				var params = $system.array.list('title category content');
				for(var i = 0; i < params.length; i++) post[params[i]] = form[params[i]].value;

				if(!$system.is.digit(form.start_hour.value) || !$system.is.digit(form.start_minute.value)) post.start = '';
				else post.start = form.start_hour.value + ':' + form.start_minute.value;

				if(!$system.is.digit(form.end_hour.value) || !$system.is.digit(form.end_minute.value)) post.end = '';
				else post.end = form.end_hour.value + ':' + form.end_minute.value;

				if(!post.start && !post.end) __node(year, month, day, 'time').innerHTML = ''; //Clean up invalid time
				else $system.node.text(__node(year, month, day, 'time'), post.start + ' - ' + post.end); //Display the time specified

				if(form.category.value != '0') //Display the category name if set
					$system.node.text(__node(year, month, day, 'category'), form.category.options[form.category.selectedIndex].text);

				var cell = $system.node.id([$id, 'date', __index(year, month, day)].join('_')); //Date cell
				if(cell) $system.node.classes(cell, $id + '_registered', true); //Set it as a schedule registered day
			}
			else var cell = null;

			$system.node.hide(form, true); //Hide the input forms
			$system.node.fade(__node(year, month, day, 'display').id, false); //Show the read only content area

			if(discard === true || initial === true) return true; //If cancelling or loading for first time, do not save

			//If not discarding and not the initial loading sequence, save the information
			var notify = function(cell, date, request) //Notify the user
			{
				log.user($global.log.notice, $system.dom.status(request.xml) == '0' ? 'user/save' : 'user/save/fail', '', [date.format($global.user.pref.format.date)]);
				__schedules[__index(year, month, day)] = post; //Update the internal schedule data (Adds redundant 'day' inside 'post' but ignoring)

				if(!cell) return; //If the month has swapped for the cell to have disappeared, quit

				cell.style.backgroundColor = __cats.indexed[form.category.value] ? '#' + __cats.indexed[form.category.value].color : '';
				__summary(year, month, day, cell); //Re-apply the summary
			}

			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.set'}, post, $system.app.method(notify, [cell, date]));
		}

		this.show = function(year, month, day) //Show the schedule pane
		{
			var log = $system.log.init(_class + '.show');
			if(!$system.is.digit(year) || !$system.is.digit(month) || !$system.is.digit(day)) return log.param();

			//Drop the preceding 0
			month = Number(month);
			day = Number(day);

			var index = __index(year, month, day);
			var id = $id + '_edit_' + index; //Window ID

			if($system.node.id(id)) return $system.window.fade(id); //Fade the pane if already created

			if(year != __current.year || month != __current.month) //Load the month for schedule info if it differs
				return $self.gui.list(year, month, $system.app.method($self.item.show, [year, month, day]));

			var append = function(year, month, day) //Set the categories and the types in the select element
			{
				var cats = [{id : 0, name : '---', color : ''}].concat($self.group.get()); //Get the category list
				var form = __node(year, month, day, 'form'); //The edit form

				for(var i = 0; i < cats.length; i++)
				{
					var option = document.createElement('option');

					option.value = cats[i].id;
					$system.node.text(option, cats[i].name);

					form.category.appendChild(option);
				}

				var schedule = __schedules[index]; //Schedule information of the day

				if(schedule) //If the schedule exists for the day
				{
					var section = $system.array.list('title category content'); //Set the form field values
					for(var i = 0; i < section.length; i++) form[section[i]].value = schedule[section[i]];

					//NOTE : IE refuses to set the form category value but sets time selections below fine
					if($system.browser.engine == 'trident')
					{
						var fix = function() //Repeat the same process under a timer
						{
							form.category.value = schedule.category;
							if(form.category.value != '0') $system.node.text(__node(year, month, day, 'category'), form.category.options[form.category.selectedIndex].text); //Display the category name if set
						}

						setTimeout(fix, 0);
					}

					section = ['start', 'end']; //For both start and end time

					for(var i = 0; i < section.length; i++)
					{
						if(!schedule[section[i]].match(/^\d+:\d+$/)) continue;
						var time = schedule[section[i]].split(':'); //Split the hour and the minute

						form[section[i] + '_hour'].value = Number(time[0]); //Set hour (Remove 0 pad)
						form[section[i] + '_minute'].value = time[1]; //Set minute
					}

					$self.item.set(year, month, day, false, true); //Apply the schedule information
				}
			}

			var language = $system.language.strings($id);
			var title = $self.info.title + ' : ' + language['schedule'];

			var source = [/%value:day%/g, /%value:id%/g, /%value:param%/g];
			var replace = [$system.date.create([year, month, day]).format($global.user.pref.format.date), index, [year, month, day].join(', ')];

			__opened[index] = true; //Remember the list of opened dates

			var body = $system.text.replace($self.info.template.day, source, replace);
			return $system.window.create(id, title, body, $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 450, undefined, false, false, true, null, $system.app.method(append, [year, month, day]), true);
		}
	}
