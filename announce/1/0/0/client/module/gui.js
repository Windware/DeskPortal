
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _locked = false; //Lock state while the news is loading

		var _news = {}; //Announcement caches

		var _node = $id + '_box'; //The ID of the node to place the news in

		this.fetch = function(year, month, execute) //Get the news remotely
		{
			var log = $system.log.init(_class + '.fetch');
			if(typeof execute != 'function' || !String(year).match(/^\d{4}$/) || !$system.is.digit(month) || month < 1 || month > 12) return log.param();

			var cache = year + '/' + month; //ID of the cache reference
			if($system.is.object(_news[cache])) return execute(year, month, _news[cache]); //If cache exists, use it

			var completion = function(year, month, execute, request) //Function to be called upon request completion
			{
				_news[cache] = request; //Store the cache
				execute(year, month, request); //Run the callback function

				log.user($global.log.info, 'user/get', '', [year, $system.date.month.full[month]]);
			}

			var callback = $system.app.method(completion, [year, month, execute]); //The feed to get to show the news from
			return $system.network.send($self.info.root + 'server/php/front.php', {task : 'gui.fetch', year : year, month : month, language : $global.user.language}, null, callback);
		}

		this.swap = function(year, month, callback) //Swap the news to another month's : TODO - Cache instead of getting every time
		{
			var log = $system.log.init(_class + '.swap');

			if(year === undefined || month === undefined) //If unspecified, get current date
			{
				var now = $system.date.create();

				year = now.year();
				month = now.month();
			}

			if(!String(year).match(/^\d{4}$/) || !$system.is.digit(month) || month < 1 || month > 12) return log.param();

			if(_locked) return log.dev($global.log.notice, 'dev/remote', 'dev/remote/solution'); //Wait until the loading completes for next transformation request
			_locked = true; //Set the locked state

			var day = $system.date.create([year, month]);
			$system.node.text($id + '_month', day.format($global.user.pref.format.month)); //Display the current month at the top

			var language = $system.language.strings($system.info.id); //Get language file
			$system.node.text(_node, language.loading); //Notice the user it is loading

			var display = function(callback, year, month, request) //Load the announcement fetched remotely
			{
				//Objects to indicate the months before and next of the current month
				var neighbor = {before : {year : year, month : month - 1}, next : {year : year, month : month + 1}};
				var section = ['before', 'next']; //List to use for naming convention

				var language = $system.language.strings($id); //Get the language file

				for(var i = 0; i < section.length; i++)
				{
					var target = neighbor[section[i]];

					if(target.month > 12) //If it's next year, set to January
					{
						target.year++;
						target.month = 1;
					}
					else if(target.month < 1) //If it's last year, set to December
					{
						target.year--;
						target.month = 12;
					}

					var link = $system.node.id($id + '_' + section[i]); //The link node for the neighbor month

					link.onclick = $system.app.method($self.gui.swap, [target.year, target.month]); //Switch the link
					$system.node.text(link, $system.text.format(language[section[i]])); //Switch the text
				}

				_locked = false; //Let go of the locking

				var field = $system.node.id(_node); //The place to put news
				field.innerHTML = '';

				var container = document.createElement('div');
				container.id = $id + '_container';

				field.appendChild(container);
				$system.node.fade(container.id, false);

				if(!request.valid()) //If request fails
				{
					container.innerHTML = language.failed; //Notice the user it failed
					return log.dev($global.log.error, 'dev/load', 'dev/load/solution', [year, month]);
				}

				var entries = $system.dom.tags(request.xml, 'news'); //List of news entries

				if(!entries)
				{
					container.innerHTML = language.failed; //Notice the user it failed
					return log.dev($global.log.error, 'dev/parse', 'dev/parse/solution', [$system.browser.report(error)]);
				}

				if(!entries.length) var output = language.none; //If there are no news, indicate so
				else
				{
					var output = ''; //The announcements to output
					var sections = $system.array.list('subject date link description');

					for(var i = 0; i < entries.length; i++) //Get the displaying components
					{
						var components = {}; //News components
						for(var j = 0; j < sections.length; j++) components[sections[j]] = $system.dom.text($system.dom.tags(entries[i], sections[j])[0], true);

						components.date = $system.date.create(components.date).format($global.user.pref.format.monthdate); //Format the date
						components.description = $system.text.escape(components.description); //Escape the HTML code

						output += $system.text.format($self.info.template.news, components); //Concatenate the entries
					}
				}

				container.innerHTML = output; //Write out the news
				$system.app.callback(log.origin + '.display', callback);
			}

			$self.gui.fetch(year, month, $system.app.method(display, [callback])); //Get the news remotely
		}
	}
