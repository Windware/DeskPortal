$self.run = function(callback) //Function to run just before GUI window is loaded for this application
{
	$self.main.operator('sample') //Do any initial operation required to display the interface
	if(typeof callback == 'function') callback() //Make sure to call any callback specified when the system loads this application
}
