Installation
============

1. As root, copy these two files into the remote directory under the ISPConfig document root (this is usually /usr/local/ispconfig/interface/web/remote, with a symlink to /var/www/ispconfig/remote).
2. chown ispconfig:ispconfig json.php ISPConfigJsonWrapper.php
3. chmod +x json.php ISPConfigJsonWrapper.php
4. That's it

Usage
=====

Base URI
--------
http://{ServerIP}:8080/remote/json.php

JSONP
-----
Simply add a callback query parameter to trigger the JSONP

Requests
--------
JSON requests are normal form-encoded POST requests with the method to execute as a URL query parameter named method (i.e. /remote/json.php?method=login).  
JSONP requests have all of the requests in the URL (i.e. /remote/json.php?method=login&username=fubar&password=boobaz&callback=JSON_CALLBACK).

Knowing The Methods & Arguments
-------------------------------
Simply call this endpoint via GET request:  /remote/json.php?method=methods, simply add a callback parameter for JSONP, 
and you will get an object with all of the callable methods, their arguments and names as well as whether the argument is required or not.
