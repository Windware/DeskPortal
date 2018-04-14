var __cache = {} //Entry caches

var __entry = {} //Each entry's information

var __filter = {} //Chosen filter option by the user

var __feed = {} //Feed information

var __selected, __updater //Current feed ID selection, line template, update interval timer

var __section = $system.array.list('period year month week day') //List of choosable display span options

var __apply = function(id, reverse) //Apply the selected display span value for a feed to the internal hash and vice versa
{
	var form = $system.node.id($id + '_selection_span') //Display span form
	if(!$system.is.element(form, 'form')) return false

	if(!__feed[id]) __feed[id] = {page: 1}

	for(var i = 0; i < __section.length; i++)
	{
		if(!reverse) __feed[id][__section[i]] = form[__section[i]].value //Keep the selected form value
		else form[__section[i]].value = Number(__feed[id][__section[i]]) //Restore the kept value to the form
	}
}

var __swap = function(period) //Display the proper select object
{
	$system.node.hide($id + '_selection_0', true)
	$system.node.hide($id + '_selection_1', true)

	if(period != 2) $system.node.hide($id + '_selection_' + period, false)
}
