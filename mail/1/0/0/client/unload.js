
	for(var account in __active) clearInterval(__active[account]); //Stop updating folder lists

	for(var folder in __refresh) clearTimeout(__refresh[folder]); //Stop updating folder contents
