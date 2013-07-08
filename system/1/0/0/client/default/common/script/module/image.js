
	$self.image = new function() //Image related class
	{
		//Note : IE7 supports translucent png images natively, but window movement becomes unusably slow and it is made to use filters like IE6
		//IE8 has fixed this issue and uses native image function
		//For any non translucent images, using this class is unnecessary

		var _class = $id + '.image';

		//IE's image loader filter declaration
		var _filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(sizingMethod=scale, src='%%')";

		this.background = function(node, image) //Give a background image to an element
		{
			var log = $system.log.init(_class + '.background');
			if(!$system.is.object(node) || !image && image !== null) return log.param();

			if(!image) //If no image is specified, strip away the background image
			{
				if($system.browser.engine === 'trident' && $system.browser.version < 8) node.style.removeAttribute('filter');
				else node.style.backgroundImage = ''; //IE8+ and others
			}
			else //Otherwise, set the image as its background element
			{
				if($system.browser.engine === 'trident' && $system.browser.version < 8) node.style.filter = $system.text.format(_filter, [$system.network.form(image)]);
				else node.style.backgroundImage = 'url(' + $system.network.form(image) + ')'; //IE8+ annd others
			}
		}

		this.fit = function() //Make the wallpaper fit inside the screen
		{
			var log = $system.log.init(_class + '.fit');
			if(!$global.user.pref.resize) return;

			var size = $system.browser.size(); //Current screen size
			var paper = $system.node.id($id + '_background'); //Wallpaper object

			//Find the ratio of the image size against screen size
			var ratio = {x : paper.clientWidth / size.x, y : paper.clientHeight / size.y};

			if(ratio.x > ratio.y) //Strech the image accordingly
			{
				paper.style.width = size.x + 'px';
				paper.style.height = $global.user.pref.stretch ? size.y + 'px' : '';
			}
			else
			{
				paper.style.width = $global.user.pref.stretch ? size.x + 'px' : '';
				paper.style.height = size.y + 'px';
			}
		}

		this.set = function(node, image) //Sets an image element's source
		{
			var log = $system.log.init(_class + '.set');
			node = $system.node.target(node); //Figure out the target node

			if(!$system.is.element(node, 'img') || !$system.is.text(image)) return log.param();

			if($system.browser.engine === 'trident' && $system.browser.version < 8) //If old IE
			{
				node.src = $system.network.form($system.info.devroot + 'image/blank.png'); //Give a blank image
				node.style.filter = $system.text.format(_filter, [$system.network.form(image)]); //But put filter on it to simulate translucency
			}
			else node.src = $system.network.form(image); //Otherwise, simply use the image as is
		}

		this.source = function(id, image) //Returns a cross engine image source address that will go inside 'src' attribute of an 'img' element
		{
			var log = $system.log.init(_class + '.source');
			if(!$system.is.id(id) || !$system.is.text(image)) return log.param();

			var root = $global.user.conf[id] && $global.user.conf[id].theme ? $global.user.conf[id].theme : $system.app.path(id) + 'client/default/common/';
			var graphic = $system.network.form(root + 'image/' + image); //Full address to get the image

			if($system.browser.engine !== 'trident' || $system.browser.version >= 8) return graphic; //For png supporting browsers, use the image as is

			//Otherwise, use filter to achieve the translucent effects under older IE
			return $system.network.form($system.info.devroot + 'image/blank.png') + '" style="filter : ' + $system.text.format(_filter, [graphic]);
		}

		this.wallpaper = function(name, save) //Set a wallpaper
		{
			var log = $system.log.init(_class + '.wallpaper');
			if(!$system.is.path(name) && !$system.is.address(name)) return log.param();

			var resize = function() //Resize the wallpaper
			{
				$system.image.fit(); //Set the body and wallpaper size
				paper.style.visibility = ''; //Make it visible again

				if($system.browser.engine === 'trident') $system.image.fit(); //IE6 refuses to set it properly on first try
			}

			var paper = $system.node.id($id + '_background');
			if(!paper) return false;

			paper.style.visibility = 'hidden'; //Keep it hidden until resized

			paper.onload = paper.onabort = resize; //Resize to configured method
			paper.src = $system.is.path(name) ? $system.network.form(name) : name; //Set image path

			//Make it centered if configured so
			paper.parentNode.style.textAlign = $global.user.pref.center ? 'center' : '';
			paper.parentNode.style.verticalAlign = $global.user.pref.center ? 'middle' : '';

			$global.user.pref.wallpaper = name; //Update the global variable
			log.user($global.log.notice, 'user/wallpaper');

			if(!save) return true;
			return $system.network.send($system.info.root + 'server/php/run.php', {task : 'image.wallpaper'}, {name : name});
		}
	}
