<?php
session_start();

unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_fullname'],
    $_SESSION['orders_csrf_token']
);

header('Location: admin_login.php');
exit;
?>