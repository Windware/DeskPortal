
	$self.array = new function() //Array manipulation class
	{
		this.dump = function(object, match, detail) //Dump an object's elements as a concatenated string
		{
			var list = ''; //Text to return

			if(!$system.is.object(object)) return alert('(Not an object)'); //If not an object, quit
			if(match !== undefined && !$system.is.text(match) && !(match instanceof RegExp)) return alert('(Invalid second parameter)');

			if(typeof match === 'string') match = RegExp($system.text.regexp(match)); //Make sure escape strings don't act funny

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
					var functions = (detail || typeof object[i] !== 'function') ? object[i] : '(function)';
					list += i + ' : ' + functions + '\n';
				}
			}

			return alert(list);
		}

		this.find = function(list, value, strict) //Finds a specified element out of an array
		{
			if(typeof list !== 'object' || !$system.is.text(value) && !(value instanceof RegExp)) return false;

			//Give case insensitive representation of the value
			if(typeof value === 'string') value = RegExp('^' + $system.text.regexp(value) + '$', i);

			if(list instanceof Array) //If for an array
			{
				for(var i = 0; i < list.length; i++) //Return the index number if found
					if(strict && list[i] === value || !strict && String(list[i]).match(value)) return true;
			}
			//If for an object, return the key if found
			else for(var i in list) if(strict && list[i] === value || !strict && String(list[i]).match(value)) return true;

			return false; //Return failure if not found. Make sure not to mix up with 0 and empty strings on the receiving side
		}

		this.json = function(list) //Create a JSON text from a hash
		{
			if(!$system.is.object(list)) return '{}'; //If not an object, return an empty hash

			var build = function(list)
			{
				for(var key in list)
				{
					if($system.is.object(list[key])) var element = build(list[key]); //Go recursive for objects
					else if(typeof list[key] === 'string') var element = '"' + list[key] + '"';
					else if($system.is.digit(list[key], true, true)) var element = list[key];
					else continue;

					json.push($system.text.format('"%%" : %%', [key, element])); //Create the hash representation
				}
			}

			var json = [];
			build(list); //Create the JSON text

			return '{' + json.join(', ').replace(/\\/g, '\\\\') + '}';
		}

		this.keys = function(list) //Retrieves keys in a hash
		{
			if(typeof list != 'object') return []; //If not an object, return an empty array

			var keys = []; //List of keys
			for(var i in list) keys.push(i); //Find the keys and push it in

			return keys;
		}

		this.list = function(text, splitter) //Creates an array from a string separated by 'splitter'
		{
			if(splitter === undefined) splitter = ' '; //Sepcify a space as a default splitter if unspecified
			if(!$system.is.text(text) || typeof splitter != 'string' && !(splitter instanceof RegExp)) return [];

			return text.split(splitter); //Return the array splitted by the splitter
		}

		this.unique = function(list, strict) //Crop any entries that are same
		{
			if(!(list instanceof Array)) return []; //Return empty if given an invalid parameter
			var result = []; //Result array

			for(var i = list.length - 1; i >= 0; i--) //Check all elements (except the last one) for duplicates
			{
				var duplicate = false; //State of duplicate existence

				for(var j = i - 1; j >= 0; j--) if(list[i] === list[j] || !strict && list[i] == list[j]) duplicate = true; //Go through the rest of the variables in the array
				if(!duplicate) result.push(list[i]); //If no duplicates, return it
			}

			return result.reverse();
		}
	}
