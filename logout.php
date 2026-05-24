<?php
// start the session
session_start();

// get previous page
if(isset($_REQUEST['pv_page'])){
    $pv_page = $_REQUEST['pv_page'];
}

// remove the session variable
session_unset();

// end the session
session_destroy();

// redirect to the previous page
header("Location:".$pv_page);
?>
