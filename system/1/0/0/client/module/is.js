
	$self.is = new function() //Variable inspection class
	{
		//Finds if it is a web address
		this.address = function(address) { return $system.is.text(address, false, /^https?:\/\/[0-9a-z\-]+/); }

		this.app = function(name, version) //Checks if the value is a valid name/version for an application or not
		{
			return $system.is.text(name, false, /^[a-z\d]+$/) && (version === undefined || $system.is.version(version));
		}

		this.array = function(subject) { return subject instanceof Array; } //Check if the subject is an array

		this.color = function(subject) //Finds if the subject qualifies as a color code or an alphabetic string
		{
			//Match for a hex color code or alphabets
			return typeof subject == 'string' && !!(subject.match(/^[0-9a-f]{6}$/i) || subject.match(/^[a-z]+$/));
		}

		this.date = function(subject, time) //Check if the subject is a DATE string
		{
			var time = time ? ' \\d{1,2}(:\\d{1,2}){2}' : ''; //Check for time as well if specified
			return String(subject).match(RegExp('^\\d{4}(-\\d{1,2}){2}' + time + '$'));
		}

		this.digit = function(subject, signed, decimal) //Checks if a value is a valid number or not
		{
			signed = signed ? '[+-]?' : ''; //If signs are allowed or not
			decimal = decimal ? '(\\.\\d+)?' : ''; //If signs are allowed or not : FIXME - need to allow '.8' style too

			var digit = RegExp($system.text.format('^%%\\d+%%$', [signed, decimal]));
			return !!String(subject).match(digit); //Return the match result
		}

		this.element = function(node, tag) //Checks if the node is a HTML element (HTMLElement does not exist in IE)
		{
			if(!$system.is.object(node) || !$system.is.type(tag, 'string')) return false;
			return node.nodeType == 1 && (tag === undefined || node.nodeName.toLowerCase() == tag.toLowerCase());
		}

		//Checks if a given string looks like a language representation (Not limiting character length due to uncertainty)
		this.language = function(subject) { return $system.is.text(subject, false, /^[a-z]+(\-[a-z]+)?$/i); }

		this.md5 = function(subject) { return $system.is.text(subject, false, /^[a-f0-9]{32}$/i); } //Check if the given string is a md5 value

		this.id = function(subject) //Checks if the value is a valid application ID or not
		{
			if(typeof subject != 'string') return false;
			if(subject == 'system_static') return true;

			var values = subject.split('_'); //Split the id into name and version (JS cannot split only up to certain delimeters and keep the rest joined)
			return $system.is.app(values[0], [values[1], values[2], values[3]].join('_')); //Check for validity
		}

		//Checks if the value is an object but not null (Checking 'instanceof Object' can differ from browser to browser)
		this.object = function(subject) { return typeof subject == 'object' && subject != null; }

		//Checks if the value is a valid relative path from the system root directory (Only checks the structure and not the accessibility)
		this.path = function(subject) { return typeof subject == 'string' && !!(subject.match(/^[a-z\d]+(\/\d+){3}\//) || subject.match(/^system\/static\//)); }

		this.sha256 = function(subject) { return $system.is.text(subject, false, /^[0-9a-f]{64}$/i); } //Checks if the string is a sha256 hash value

		//Checks if the subject is a string, and optionally allow zero length string and match certain characters
		this.text = function(subject, zero, match)
		{
			return typeof subject == 'string' && (!!zero || (subject.length > 0 && (match === undefined || !!subject.match(match))));
		}

		this.type = function(subject, type) //Checks if the value is undefined or the specified type
		{
			if(subject === undefined) return true;
			if(typeof type != 'string') return false;

			return type == 'array' ? $system.is.array(subject) : typeof subject == type;
		}

		this.user = function(user) { return $system.is.text(user, false, /^\w+$/); } //Checks if the subjet is a valid user name or not

		//Checks if the value is a valid version for an application or not
		this.version = function(subject) { return $system.is.text(subject, false, /^\d+_\d+_\d+$/); }
	}
