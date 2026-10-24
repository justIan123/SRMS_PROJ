<?php
session_start();
session_destroy();
header("Location: app/login_page.php");
exit;
?>
