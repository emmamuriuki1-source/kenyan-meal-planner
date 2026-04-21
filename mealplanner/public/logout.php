<?php
ob_start();
session_start();
$_SESSION = [];
session_destroy();
ob_end_clean();
header('Location: index.php');
exit;
