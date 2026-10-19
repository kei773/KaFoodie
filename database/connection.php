<?php
$localhost = "localhost";
$user = "root";
$pass = "";
$db = "kafoodie";

$con = mysqli_connect($localhost, $user, $pass, $db)
    or die("Can't Connect to the database");
?>