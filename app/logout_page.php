<?php
require_once("../public/initialize.php");
require_once(HANDLERS_PATH . DS . "logoutProcess.php");

// why tf this exist
// if this does not exist i have to make a 2 or more html forms instead of one
// para iwas headache

/*EXPLAINATION
yung htaccess only include render frontend files sa /app 
which means if accessing the other files via url, htaccess found it as 403 or 404 (safe from pentest not perfect but it works) 
then yung logic which Handlers and initialization(index.php) is only include in the frontend

simple explanation
pinagsabay ang backend sa frontend as ONE FILE
why pinagsabay??

to minimize the server usage from infinityfree on a free plan

*/
?>