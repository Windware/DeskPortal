
	$self.dom = new function() //DOM manipulating class
	{
		this.attribute = function(node, name) //Finds an attribute's value on a node
		{
			if(!$system.is.object(node) || !node.attributes || !$system.is.text(name)) return '';

			var attribute = node.attributes.getNamedItem(name); //Get the attribute value
			return attribute && typeof attribute.nodeValue == 'string' ? attribute.nodeValue : ''; //Return the value
		}

		this.dump = function(object, match, detail) //Dump an object's elements as a concatenated string
		{
			var list = ''; //Text to return

			if(!$system.is.object(object)) return alert('(Not an object)'); //If not an object, quit
			if(match !== undefined && !$system.is.text(match) && !(match instanceof RegExp)) return alert('(Invalid second paramter)');

			if(typeof match == 'string') match = RegExp($system.text.regexp(match)); //Make sure escape strings don't act funny

			if(object instanceof Array) //If it is an array
			{
				//Iterate over and add the content
				for(var i = 0; i < object.length; i++) if(!match || object[i].match(match)) list += object[i] + '\n';
			}
			else //If an object
			{
				for(var i in object) //Iterate over the object's members
				{
					if(match && !i.match(match)) continue; //If not matching, try next

					//If needs detailed dump, dump the content of a function too
					var functions = (detail || typeof object[i] != 'function') ? object[i] : '(function)';
					list += i + ' : ' + functions + '\n';
				}
			}

			return alert(list);
		}

		this.status = function(xml, key) //Return the status string returned from server
		{
			if(!$system.is.object(xml)) return false;
			var states = $system.dom.tags(xml, 'status'); //Check the value on the specified key

			if(key === undefined) return $system.dom.attribute(states[0], 'value');
			for(var i = 0; i < states.length; i++) if($system.dom.attribute(states[i], 'key') == key) return $system.dom.attribute(states[i], 'value');

			return false;
		}

		this.tags = function(dom, tag) //Returns list of nodes by name
		{
			if(!$system.is.object(dom) || !$system.is.text(tag)) return [];

			if($system.browser.engine == 'trident') //Checking for the method breaks IE, thus using 'try' instead
			{
				try { return dom.getElementsByTagName(tag) || []; }

				catch(error) { return []; }
			}

			return dom.getElementsByTagName && dom.getElementsByTagName(tag) || [];
		}

		this.text = function(node, revert) //Returns the inner text out of a node
		{
			if(typeof node != 'object' || !node.firstChild || !$system.is.text(node.firstChild.nodeValue)) return '';

			var text = node.firstChild.nodeValue.replace(/^\s+/, '');
			if(!revert) return text; //Return as is

			//Unescape safely transitioned CDATA string if specified
			return text.replace(/\]\]&gt;/g, ']]>').replace(/\]\]&amp;/g, ']]&');
		}
	}
