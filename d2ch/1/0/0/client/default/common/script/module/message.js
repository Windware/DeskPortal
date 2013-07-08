
	$self.message = new function()
	{
		var _class = $id + '.message';

		this.get = function(id) //List the messages in a thread
		{
			var log = $system.log.init(_class + '.get');
			if(!$system.is.digit(id)) return log.param();

			var list = function(request)
			{
				if(!$system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/message/get', 'user/message/get/message');

				var message = $system.dom.tags(request.xml, 'message');
				var section = $system.array.list('number mail signature posted'); //Values to use to display messages

				var replace = function(phrase, match) { return __message[id] && __message[id][match] ? __message[id][match] : ''; }
				var language = $system.language.strings($id);

				var linking = function(phrase, link, anchor, sign, index) //Replace the message ID anchor as a message tool tip
				{
					var message = $system.is.digit(index) && __message[counter[index]] ? __message[counter[index]].message : '';
					var tip = $system.tip.link($system.info.id, null, 'blank', [message]);

					return $system.text.format('<a onclick="%%.%%.message.scroll(%%)" %%>%%</a>', [$global.root, $id, counter[index], tip, anchor]);
				}

				var counter = []; //Message index reference
				var posted = {}; //Post count for each user ID

				if($system.dom.attribute(message[0], 'signature')) //If user signature is enabled for the thread
				{
					for(var x = 0; x < message.length; x++)
					{
						var id = $system.dom.attribute(message[x], 'id'); //Unique message ID in the database
						var signature = $system.dom.attribute(message[x], 'signature'); //User ID

						if(!$system.is.array(posted[signature])) posted[signature] = [];
						posted[signature].push(id); //Count the amount of posts each user signature has made
					}
				}

				for(var x = 0; x < message.length; x++)
				{
					var id = $system.dom.attribute(message[x], 'id'); //Unique message ID in the database
					counter[x + 1] = id; //Remember the message index by message ID

					var text = $system.dom.text(message[x]);
					__message[id] = {id : id, message : $system.text.link(text.replace(/([^h])ttp:\/\//g, '$1http://')), filter : '', name : $system.dom.attribute(message[x], 'name'), mail : $system.dom.attribute(message[x], 'mail'), signature : $system.dom.attribute(message[x], 'signature')};

					//Link to the anchored message (NOTE : Includes Japanese full width characters)
					__message[id].message = __message[id].message.replace(/<a .*?href=".+?\/.+?\/(\d+)".*?>((&gt;|>|＞){2}([\d０１２３４５６７８９\-ー]+))<\/a>/ig, linking);

					for(var y = 0; y < section.length; y++) __message[id][section[y]] = $system.dom.attribute(message[x], section[y]);

					if(!__message[id].mail) __message[id].mail = '(' + language.empty + ')'; //The mail address field
					var signature = __message[id].signature;

					if(signature) //If user ID is enabled
					{
						var param = [$global.root, $id, id, signature, $global.root, $id, $id, signature, posted[signature].length];
						__message[id].signature = $system.text.format('<span onmouseover="%%.%%.message.user(%%, \'%%\')" onmouseout="%%.%%.message.user()" class="%%_signature">%% (%%)</span>', param);
					}

					__message[id].posted = $system.date.create(__message[id].posted).format($global.user.pref.format.full);
					var item = document.createElement('div');

					item.className = $id + '_reply';
					item.innerHTML = $self.info.template.message.replace(/%value:(.+?)%/g, replace);

					delete __message[id].filter; //Remove temporary attribute

					__message[id].message = text; //Revert the values back to plain text state
					__message[id].signature = signature;

					zone.appendChild(item);
				}
			}

			var zone = $system.node.id($id + '_message');
			zone.innerHTML = '';

			return $system.network.send($self.info.root + 'server/php/run.php', {task : 'message.get', thread : id}, null, list); //Get list of boards
		}

		this.scroll = function(id) //Scroll to the specified message ID
		{
			var log = $system.log.init(_class + '.scroll');
			if(!$system.is.digit(id)) return log.param();

			var message = $system.node.id($id + '_id_' + id);
			if(!$system.is.element(message)) return false;

			return $system.node.id($id + '_message').scrollTop = message.offsetTop;
		}

		this.user = function(thread, signature) //Show messages for an user by post signature
		{
			var log = $system.log.init(_class + '.user');

			if(!$system.is.digit(thread) || !$system.is.text(signature)) return log.param();
			if(!__message[thread]) return false;

			for(var i = 0; i < __message[thread]; i++)
			{
				if(__message[thread].signature != signature) continue;
			}
		}
	}
