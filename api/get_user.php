<?php
// Removed: this endpoint took the password in the URL (?email=&passkey=), which leaks it
// into server, proxy and browser logs. Credentials are never read here.
require __DIR__ . '/_bootstrap.php';

jsonResponse(410, ["message" => "This login endpoint was removed. Use POST api/login.php with a JSON body {\"email\", \"password\"}."]);
