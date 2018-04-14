var __cats //Categories

var __current //Currently displayed month

var __opened = {} //List of opened dates

var __schedules = {} //List of schedule information

var __index = function(year, month, day) //Create a DATETIME string
{
	if(month < 10) month = '0' + month
	if(day < 10) day = '0' + day

	return [year, month, day].join('-')
}

var __node = function(year, month, day, section) //Get the node with specified ID
{
	return $system.node.id([$id, 'day', __index(year, month, day), section].join('_'))
}

var __summary = function(year, month, day, node) //Set a toolip summary of the day
{
	var index = __index(year, month, day)
	var info = __schedules[index] //The day's information

	if(!info) return false

	var language = $system.language.strings($id)
	var summary = language.title + ' : ' + info.title //Create the tooltip summary

	if(info.category && __cats.indexed[info.category])
		summary += '\n' + language.category + ' : ' + __cats.indexed[info.category].name

	if(info.start || info.end) summary += '\n' + language.time + ' : ' + (info.start || '') + ' - ' + (info.end || '')
	return $system.tip.set(node, $id, 'summary', [$system.text.escape(summary).replace(/\n/g, '<br />\n')]) //Make a summary tooltip
}

new function() //Apply selections to the template
{
	var target = {} //Template variables
	target.hour = target.minute = '<option value="">--</option>\n' //Select options for the time

	//NOTE : IE6 requires the 'value' attribute to be present for the values to be retrieved
	for(var i = 0; i < 24; i++) target.hour += $system.text.format('<option value="%%">%%</option>\n', [i, i])

	for(var i = 0; i < 60; i += 5)
	{
		var padded = i < 10 ? '0' + i : i //Add a 0 to the front for 00 and 05
		target.minute += $system.text.format('<option value="%%">%%</option>\n', [padded, padded])
	}

	var replace = function(phrase, match) { return target[match] }
	$self.info.template.day = $system.language.apply($id, $self.info.template.day).replace(/%init:(.+?)%/g, replace)
}
