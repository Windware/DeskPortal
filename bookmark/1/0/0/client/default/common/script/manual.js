$self.manual = new function()
{
	this._7_import = function() //Bookmark import manual
	{
		switch($system.browser.name)
		{
			case 'Internet Explorer' :
			case 'Firefox' :
			case 'Safari' :
			case 'Chrome' :
			case 'Opera' :
				var page = $system.browser.name.replace(/ /g, '_').toLowerCase() //Flatten the browser name
				break

			default :
				var page = 'other'
		}

		$system.node.hide($id + '_manual_' + page, false) //Display the manual for the current browser
	}
}
