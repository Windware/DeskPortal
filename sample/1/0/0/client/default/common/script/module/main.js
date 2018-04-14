//Custom class code to be used

$self.main = new function() //Class name
{
	var _class = $id + '.main' //Declare the class name for log purpose

	this.operator = function(param) //Function inside this class
	{
		var log = $system.log.init(_class + '.operator') //Start the logger
		return param == __common //Do some operation
	}
}
