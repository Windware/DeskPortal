
	var __account = []; //List of accounts

	var __active; //Timer for updating folder list for the currently displayed account

	var __belong = {}; //Accounts folders belong to

	var __cache = {}; //Message listing cache

	var __current; //Current mail listing cache

	var __default; //The default account ID

	var __filter = {}; //List filter options

	var __mail = {}; //List of mails

	var __order = {item : 'sent', reverse : true}; //Current sort order method

	var __refresh = {}; //Periodic folder update timer

	var __selected = {}; //Currently displayed account, folder and page

	var __special = {'inbox' : {}, 'drafts' : {}, 'sent' : {}, 'trash' : {}} //Special folders

	var __timer = {}; //Auto folder update timer

	var __update = {}; //Flag to indicate that a folder should be updated from the server

	var __window = 0; //Mail window counter
