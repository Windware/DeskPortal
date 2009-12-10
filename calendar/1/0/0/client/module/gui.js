
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _loading; //Flag set while waiting for response

		this.edit = function(year, month, day) //Show the edit input forms of the schedule
		{
			var log = $system.log.init(_class + '.edit');
			if(!$system.is.digit(year) || !$system.is.digit(month) || !$system.is.digit(day)) return log.param();

			$system.node.hide(__node(year, month, day, 'display'), true); //Hide the content

			$system.node.fade(__node(year, month, day, 'away').id, false); //Make the cancel/delete buttons visible
			$system.node.fade(__node(year, month, day, 'form').id, false); //Show the input forms

			return true;
		}

		this.list = function(year, month, callback) //Set the calendar to a specific month
		{
			var log = $system.log.init(_class + '.get');

			if(_loading) return false; //Wait till the previous operation completes
			if($system.is.digit(year) != $system.is.digit(month)) return log.param(); //Don't let only one of them to be specified

			var today = $system.date.create(); //Find out today
			today = {year : today.year(), month : today.month(), date : today.date()}; //Keep the values precalculated

			//Set the displaying month
			if($system.is.digit(year)) __current = $system.date.create([year, month, 1]);
			else __current = $system.date.create([today.year, today.month, 1]);

			var show = __current.format($global.user.pref.format.month);
			$system.node.text($id + '_month', show); //Set current month

			//Keep the values not to make calls to the date object repeatedly
			__current = {year : __current.year(), month : __current.month(), day : __current.day()};

			var previous = {year : __current.year, month : __current.month - 1}; //Set the previous month
			previous.node = $system.node.id($id + '_previous');

			if(previous.month == 0) //Adjust for previous year
			{
				previous.year--;
				previous.month = 12;
			}

			var language = $system.language.strings($id);

			previous.node.onclick = $system.app.method($self.gui.list, [previous.year, previous.month]);
			$system.tip.set(previous.node, $id, 'switch', [$system.date.month.full[previous.month]]); //Give a tip

			var next = {year : __current.year, month : __current.month + 1}; //Set the next month
			next.node = $system.node.id($id + '_next');

			if(next.month == 13) //Adjust for next year
			{
				next.year++;
				next.month = 1;
			}

			next.node.onclick = $system.app.method($self.gui.list, [next.year, next.month]);
			$system.tip.set(next.node, $id, 'switch', [$system.date.month.full[next.month]]); //Give a tip

			var set = function(callback, request)
			{
				var list = $system.dom.tags(request.xml, 'schedule'); //Current month's schedule data
				var sections = $system.array.list('title category type start end'); //Schedule information

				for(var i = 0; i < list.length; i++)
				{
					var day = $system.dom.attribute(list[i], 'day');
					__schedules[day] = {}; //Create a hash to include the day's schedule information

					for(var j = 0; j < sections.length; j++) __schedules[day][sections[j]] = $system.dom.attribute(list[i], sections[j]);
					__schedules[day].content = $system.dom.text(list[i]);
				}

				var field = $system.node.id($id + '_display'); //Area where the calendar goes to
				while(field.firstChild) field.removeChild(field.firstChild); //Clean up the field (innerHTML on table breaks khtml)

				var weeks = document.createElement('tr'); //First line of the table for week day names

				for(var i = 0; i <= 6; i++) //For each of the week days
				{
					var day = document.createElement('th'); //Create a header cell
					$system.node.text(day, $system.date.week.less[i]); //Insert localized name

					if(i == 0) //Settings for Sunday
					{
						$system.node.classes(day, $id + '_sunday', true);
						day.style.width = '15%';
					}
					else if(i == 6) //Settings for Saturday
					{
						$system.node.classes(day, $id + '_saturday', true);
						day.style.width = '15%';
					}
					else day.style.width = '14%';

					weeks.appendChild(day); //Append the cell to the row
				}

				field.appendChild(weeks); //Set the row to the area

				var index = 0; //To count days
				var last = (new Date(__current.year, __current.month, 0)).getDate(); //Find out the last day of the month

				var category = $self.group.get(true); //Get the category list indexed

				for(var i = 1; i <= 6; i++) //At maximum of 6 week rows
				{
					var row = document.createElement('tr'); //Create the week's row

					for(var j = 0; j <= 6; j++) //For every day in a week
					{
						var cell = document.createElement('td'); //Create a date cell

						if((i != 1 || j >= __current.day) && ++index <= last) //Only within actual days
						{
							var id = __index(__current.year, __current.month, index);
							$system.node.hover(cell, $id + '_active'); //Make hover color change IE compatible

							cell.id = [$id, 'date', id].join('_'); //Set an unique ID
							$system.node.classes(cell, $id + '_box', true); //Set a class for styling

							$system.node.text(cell, index); //Give the date

							//Set action for setting schedule
							cell.onclick = $system.app.method($self.item.show, [__current.year, __current.month, index]);

							cell.style.cursor = 'pointer'; //Change the mouse cursor
							cell.onmousedown = $system.app.method($system.event.cancel, [cell]); //Do not initiate window dragging

							if(j == 0) $system.node.classes(cell, $id + '_sunday', true); //Give weekend colors
							else if(j == 6) $system.node.classes(cell, $id + '_saturday', true);

							if(__current.year == today.year && __current.month == today.month && today.date == index)
								$system.node.classes(cell, $id + '_today', true); //Hilight today

							__summary(__current.year, __current.month, index, cell);

							if(__schedules[id])
							{
								var division = __schedules[id].category;

								if(division > 0 && category[division]) cell.style.backgroundColor = category[division].color;
								$system.node.classes(cell, $id + '_registered', true);
							}
						}

						row.appendChild(cell);
					}

					field.appendChild(row);
					if(index >= last) break; //If the end of the month is reached, do not add an empty row
				}

				_loading = false;
				$system.node.fade(field.id, false);

				log.user($global.log.info, 'user/display', '', [show]);
				if(typeof callback == 'function') callback();
			}

			_loading = true; //Request the schedule listing
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'item.get', year : __current.year, month : __current.month}, null, $system.app.method(set, [callback]));
		}

		this.refresh = function() //Refresh the calendar
		{
			var log = $system.log.init(_class + '.refresh');
			if(!__current.year || !__current.month) return false;

			var cats = {ordered : [{id : 0, name : '---', color : ''}].concat($self.group.get())}; //Get the category list
			cats.indexed = $self.group.get(true); //Get the indexed category list

			for(var day in __opened) //For all of the opened days
			{
				var form = $system.node.id([$id, 'day', day, 'form'].join('_'));
				if(!$system.is.element(form, 'form')) continue;

				form.category.innerHTML = ''; //Clean up the category list
				var sched = __schedules[day];

				for(var i = 0; i < cats.ordered.length; i++)
				{
					var option = document.createElement('option');

					option.value = cats.ordered[i].id;
					$system.node.text(option, cats.ordered[i].name);

					form.category.appendChild(option);
				}

				if(!sched) continue; //Update the category display on the edit window if it has a schedule

				if(sched.category) form.category.value = sched.category;
				$system.node.text([$id, 'day', day, 'category'].join('_'), cats.indexed[sched.category] && cats.indexed[sched.category].name || '');
			}

			return $self.gui.list(__current.year, __current.month);
		}
	}
