$self.task = new function() //Periodic task schedule related class : TODO - Not implemented
{
	var _class = $id + '.task'

	/*this.list = {}; //List of tasks

	this.add = function(id, name, run, strict, minute, hour, day, week, month) //Adds a new task
	{
		var log = $system.log.init(_class + '.add');

		for(var i = 3; i < 7; i++) //For invalid number variables, quit
			if(!$system.is.type(arguments[i], 'string') && !$system.is.type(arguments[i], 'number')) return log.param();

		if(!$system.is.id(id) || !$system.is.text(name) || typeof run != 'function') return log.param();

		if(!$system.task.list[id]) $system.task.list[id] = {}; //Register a new space for the application's tasks if it doesn't exist
		$system.task.list[id][name] = {strict : strict, minute : minute, hour : hour, day : day, week : week, month : month, name : name, run : run, last : 0}

		return true; //Report success
	}

	this.remove = function(id, name) //Deletes a task
	{
		var log = $system.log.init(_class + '.remove');
		if(!$system.is.id(id) || !$system.is.text(name)) return log.param();

		delete $system.task.list[id][name]; //Cancel a task from the list
	}

	this.run = function() //Run the list of registered tasks
	{
		var log = $system.log.init(_class + '.run');

		var now = $system.date.create();
		var list = $system.task.list;

		//TODO - Figure out the time component and compare them to each entries

		for(var id in list) //For each of the applications' entries
		{
			for(var name in list[id]) //For each of the tasks
			{
				if(typeof list[id][name].run != 'function') continue;

				try { list[id][name].run(); } //Run the task

				catch(error)
				{
					log.dev($global.log.error, 'dev/task', 'dev/check', [id, name, now.timestamp(), $system.browser.report(error)]);
				}

				finally //Keep the time the job has run, failed or not
				{
					list[id][name].last = now;
					//TODO - Save the time on the server side
				}
			}
		}
	}*/
}
