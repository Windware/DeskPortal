
	$self.gui = new function()
	{
		var _class = $id + '.gui';

		var _switch = true; //Flag to change operation

		var _memory; //Current calculated value

		var _timer = {}; //Timer to make the button look flat in case the mouse didn't go up on the button

		var _calculate = function(operation, soil, seed)
		{
			//Multiplier to keep the elements as integers to avoid floating point number accuracy errors in JavaScript
			//http://en.wikipedia.org/wiki/Floating_point#Accuracy_problems
			var precision = 0;

			var check = [soil, seed]; //Values to check

			for(var i = 0; i <= 1; i++)
			{
				if(!String(check[i]).match(/\./)) continue; //If it does not contain a dot, leave it

				var decimal = String(check[i]).split('.')[1].length; //Find the amount of digits below the dot
				if(decimal > precision) precision = decimal; //Override the precision value if it's longer
			}

			precision = Math.pow(10, precision); //Have it be the decimal multiplier

			//Make sure these are numbers and apply the integer precision multiplier and make sure they are integers
			soil = Math.round(Number(soil) * precision);
			seed = Math.round(Number(seed) * precision);

			switch(operation)
			{
				case '+' : return (soil + seed) / precision; break; //Addition
				case '-' : return (soil - seed) / precision; break; //Subtraction

				case '*' : return (soil * seed) / precision / precision; break; //Multiplication
				case '/' : return seed != 0 ? soil / seed : 0; break; //Division

				default : return 0; break;
			}
		}

		//TODO - If the window width changes, keep it, so that it won't suddenly shrink on the next op
		var _display = function(value) //Display the value on the screen with commas if necessary
		{
			var display = $system.node.id($id + '_result'); //The display area
			if(value === undefined) return display.innerHTML.replace(/,/g, ''); //If no parameter is given, return the currently displayed value

			$system.node.fade(display.id, false);

			value = String(value); //Treat the value as a string
			$system.node.text(display, value); //Show on the display
		}

		var _mode = function(value) //Set operation mode display
		{
			var node = $system.node.id($id + '_mode');
			if(value === undefined) return node.innerHTML; //Return the current mode

			if(value === null) return node.innerHTML = ''; //Reset the mode
			if($system.array.find($system.array.list('+ - * /'), value)) $system.node.text(node, value); //Set a new mode
		}

		var _recover = function(button) //Make the button look flat again
		{
			$system.node.classes(button, $id + '_pressed', false);
			button.style.padding = '2px'; //Let the padding go for the size of the border : TODO - Style on code is not good
		}

		this.press = function(button) //Simulate pressed look on mousedown
		{
			$system.node.classes(button, $id + '_pressed', true); //Create a pressed look
			button.style.padding = '0px'; //Let the padding go for the size of the border : TODO - Style on code is not good

			_timer[button.innerHTML] = setTimeout($system.app.method(_recover, [button]), 1000); //Let go of the look
		}

		this.work = function(button) //Do calculator work upon mouseup
		{
			var value = button.innerHTML; //Button value

			//Make the button flat
			clearTimeout(_timer[value]);
			_recover(button);

			if($system.is.digit(value)) //When clicking on number pads
			{
				if(_switch) _switch = false; //Do not clear anymore
				else value = _display() + value;

				_display(value); //Set the new number
			}
			else
			{
				switch(value)
				{
					case 'C' : //Clear
						//Reset the variables
						_switch = true;
						_memory = undefined;

						_display(0); //Reset the display
						_mode(null); //Reset the operation mode
					break;

					case 'D' : //Delete a digit
						if(_switch) return; //When an operation is triggered, do not wipe the digit on the answers
						var shown = _display(); //Current display

						//Crop the last digit (Try to be cross browser for IE6 without the use of '-1')
						_display(shown.length == 1 ? 0 : shown.substr(0, shown.length - 1));
					break;

					case '.' : //Decimal point
						if(_switch) //On a new number
						{
							_display('0.'); //Start out as '0.';
							_switch = false; //Indicate the display has switched to a new number
						}
						else if(!_display().match(/\./)) _display(_display() + '.'); //Dot has not been injected, add the dot
					break;

					default : //For arithmetic operations
						//If not consecutively pressed, do the previous calculation, if any
						if(!_switch && _mode()) _display(_calculate(_mode(), _memory, _display()));
						_switch = true; //Clear the display on next number press

						_memory = _display(); //Keep the current value memorized
						_mode(value == '=' ? null : value); //Show the operation mode
					break;
				}
			}
		}
	}
