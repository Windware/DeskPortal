var __external = {} //Indicates the results will be shown at an external source

var __first = {} //The first method for a feature

var __initial = 'search' //The initially selected feature

var __mutual = {} //Indicates that a method uses two select forms

var __number = 0 //The window number index

var __operation = {} //List of operations to execute for each modes

new function()
{
	var request = $system.network.item($self.info.root + 'resource/*.js', true)

	for(var i = 0; i < request.length; i++)
	{
		if(!request[i].valid()) throw 'Cannot load one of the resource JavaScript'

		try
		{ eval(request[i].text) }

		catch(error)
		{ throw 'Error loading the feature script : ' + request[i].file + ' : ' + error }
	}
}
