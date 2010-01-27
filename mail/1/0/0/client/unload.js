
	for(var id in __timer) clearInterval(__timer[id]); //Stop updating folder list
	for(var folder in __refresh) clearTimeout(__refresh[folder]); //Stop updating folder contents
