var __account = [] //List of accounts

var __belong = {} //Accounts that folders belong to

var __cache = {} //Message listing cache

var __current //Current mail listing cache

var __default //The default account ID

var __mail = {} //List of mails

var __order = {item: 'sent', reverse: true} //Current sort order method

var __page = {} //Selected page for folders

var __refresh = {} //Expire folder freshness for IMAP

var __selected = {marked: false, unread: false, search: ''} //Currently displayed account, folder and page

var __special = {'inbox': {}, 'drafts': {}, 'sent': {}, 'trash': {}} //Special folders

var __timer = {} //Auto folder update timer

var __update = {} //Flag to indicate that mail listing should be updated from the server on a folder

var __window = 0 //Mail window counter
