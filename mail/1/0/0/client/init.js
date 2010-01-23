
	var __account = []; //List of accounts

	var __belong = {}; //Accounts folders belong to

	var __current; //Currenst mail listing cache

	var __filter = {}; //List filter options

	var __mail = {}; //List of mails

	var __order = {item : 'sent', reverse : true}; //Current sort order method

	var __refresh = {}; //Periodic folder update timer

	var __selected = {}; //Currently displayed account, folder and page

	var __special = {'inbox' : {}, 'drafts' : {}, 'sent' : {}, 'trash' : {}} //Special folders

	var __update = {}; //Flag to indicate that a folder should be updated from the server
