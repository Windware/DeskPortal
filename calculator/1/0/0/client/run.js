
	$self.run = function(callback)
	{
		var pad = $system.node.id($id + '_calculator'); //The pad table

		for(var i = 0; i < pad.rows.length; i++) //For rows with buttons to push
		{
			var row = pad.rows.item(i); //The row itself

			for(var j = 0; j < row.cells.length; j++) //For every cell of the row
			{
				var cell = row.cells.item(j); //The cell itself

				//Make the button look pressed
				$system.event.add(cell, 'onmousedown', $system.app.method($self.gui.press, [cell]));

				//Do the calculation
				$system.event.add(cell, 'onclick', $system.app.method($self.gui.work, [cell]));
			}
		}

		if(typeof callback == 'function') callback();
	}
