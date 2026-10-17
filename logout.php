<?php
session_start();

unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_firstname'],
    $_SESSION['customer_lastname'],
    $_SESSION['customer_email'],
    $_SESSION['cart']
);

header("Location: Home.php");
exit;
?>