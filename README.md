# Contact-Adressbook-Sharing-for-Baikal
Extennson to share Contacts/Adressbooks in Baikal
Baikal Extension for Contact and Adressbook sharing with Baikal (sabre/DAV) Servers

The Extension was tested with Baikal 11.1 (https://github.com/sabre-io/Baikal) for mysql and SQLite configuration

The extion consists of 3 files:

1. contacts_share.php 
2. SharedReadonlyCardDAVBackend.php
3. Server.php

Spoiler: You can read what to do for installatione at the end of the file … but there is helpfull Information before.

Conzept(s) of Sharing with this Extension:
- The data structure of Baikal will not be touched or changed. A deletion of the extion is possible without any harm to your installation.
- The Concept indroduces a new table "addressbook_shares" to the DB (mysql or SQLite). No other tables are changed or restructured.
- In this new table adressbookIDs and principleURIs are stored (in pairs) indicating which adressbook will be shared !read-only! for a user 

- To use this data table we need to add some functions for data and request handling. This is done in SharedReadonlyCardDAVBackend.php
- The extension is not handling authentication or access-Control and should never write to the database. This is done by original Baikal code.
- CardDAV requests will be checked and will deliver shared addressbooks (using the new table) to the requesting client (user).
- This checks are done by SharedReadonlyCardDAVBackend.php adding the shared addressbooks to the regular adressbooks of a requesting user.
- The extension is not handling authentication or access-control and should never write to the database just adding adressbooks request answers.
- Requests with create, update and delete commands are refused or piped to the original data processing resulting in 403 and explicit error Messages.
- Baikal is finally controlling if write access and write commands are allowed.

- To use this extension and functionality SharedReadonlyCardDAVBackend.php has to be "called" and used in the Server.php file when creating backends.
- Therefore some codelines for CardDAV handlig are modified not dircty calling PDO.php but SharedReadonlyCardDAVBackend.php which uses also PDO.php.
- This means original code is still working and integral part of instances running with the extension and related modifications.

- To control which adressbooks are shared with other users the contacts_share.php script is offering a UI (Right now just for the admin!).
- The script authenticates against the Baikal password hash of the admin. If authentication is OK the subsequent processes can start.
- By using the data in the yaml config file it is checked whether SQLite or mysql is used and refering data for DB access is collected.
- If the needed additional table "addressbook_shares" is not existing, it will be added automatically. This is the only DB change made!
- The UI offers functionality for sharing and revoking shares (remember: as implemented right now this can be done just by the admin!)
- Error-Messges due to i.e. server problems might publicate data from the yaml config file in some cases (not in my Tests) ...
- Gemini AI advices therefore to rename the contacts_share.php in contacts_share.php_off after configuration. 

- However here some thoughts concerning security and putative problems:
- Acessibility of all files is the same as for all other baikal files (Mode 644). Authentification is done with baikal data and baikal processes. 
- No additional passwords are stored (no hashes and no plain text)
- No changes with respect to the original data structure means compatiblitity with future baikal versions is given. Extension might stop working.
- You can uninstall by switching back to the original Server.php (replace with original file!), deleting the other 2 file and the table in the DB.
- All pathes are compatible with the standard pathes used in baikal 11.1, differing installations might have to adapt pathes in the code.
- One important point in the end:
- If users try to write contacts into shared adressbooks nothing will be written into the databes but the client stores changes locally
- This was also the case with other calendars (e.g. tine20). So check "read-only" in a client, when the option is given to avoid problems.
- For me this behaviour was a pain with other systems .. when switching to Baikal there was no contact sharing at all .. so this is OK for me!

- How to get it working: 
- replace the Server.php with the modified version in \baikal\Core\Frameworks\Baikal\Core\ (store the original file to be able to uninstall!)
- copy SharedReadonlyCardDAVBackend.php into the same folder \baikal\Core\Frameworks\Baikal\Core\
- copy contacts_share.php into \baikal\html\ and open the file in the browser with yourdomain.com/baikal/html/contacts_share.php 
- login with your Baikal admin credentials (username and password) and share ore revoke shares (sorry the interface is in German)

