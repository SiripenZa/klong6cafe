<?php
$conn = new mysqli('localhost','root','','klong_6_cafe');
$conn->query("SET NAMES utf8");
if($conn->connect_error){
    die("Connection Fail God damn it ". $conn->connect_error);
}
?>