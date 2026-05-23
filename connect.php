<?php

$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$dbname = getenv('MYSQLDATABASE');
$port = (int)getenv('MYSQLPORT');

// Temporary debug - remove after fixing
echo "Host: " . $host . "<br>";
echo "User: " . $user . "<br>";
echo "DB: " . $dbname . "<br>";
echo "Port: " . $port . "<br>";

$conn=mysqli_connect($host,$user,$pass,$dbname, $port) or die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้"); // เชื่อมต่อ ฐานข้อมูล

if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error();
} else {
    echo "Connected successfully!";
}

?>
