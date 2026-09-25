<?php

$host = 'localhost';
$user= 'root';
$dbpassword = '';
$database = 'maintenance_request_system';

$conn=mysqli_connect($host, $user, $dbpassword, $database);
if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}
?>