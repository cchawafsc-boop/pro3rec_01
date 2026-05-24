<?php
// start the session
session_start();
require('connect.php');

// get the inputs from the form
$Us   = $_POST['regis_us'];
$Pw   = hash('sha256', $_POST['regis_pw']);
$Id   = $_POST['regis_id'];
$Dep  = strtoupper($_POST['regis_Dep']);
    
/*echo "Username: ".$Us."<br>";
echo "Password: ".$Pw."<br>";
echo "User_id: ".$Id."<br>";
echo "Dep: ".$Dep."<br>";*/

// Check the redundant user
//$sql = "SELECT * FROM `tb_user` WHERE `ID` LIKE \"".strval($Id)."\"";
//$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$stmt = mysqli_prepare($conn, "SELECT * FROM `tb_user` WHERE `ID` = ?");
mysqli_stmt_bind_param($stmt, "s", $Id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$n_row = mysqli_num_rows($result);
$n_fld = mysqli_num_fields($result);

if($n_row>0){
    echo "<script>alert(\"ข้อมูลนี้เคยลงทะเบียนแล้ว กรุณากรอกข้อมูลใหม่\")</script>";
	echo "<script> location='index.php'</script>";
	exit;
}else{
    // Upload data into dBase
    // AUT = 0 : Top authorized; able to read, write, delete & edit 
    // AUT = 1 : 2nd authorized; able to read, write & delete
    // AUT = 2 : 3rd authorized; able to read & write
    // AUT = 3 : lowest authorized; able to read only
    //$sql = "INSERT INTO `tb_user`(`US`, `PW`, `ID`, `DEP`, `AUT`) ".
    //          "VALUES (\"".$Us."\",\"".$Pw."\",\"".$Id."\",\"".$Dep."\",\"3\")";
    //$req = mysqli_query($conn, $sql) or die(mysqli_error($conn));
    $aut = "3";
    $stmt2 = mysqli_prepare($conn, "INSERT INTO `tb_user`(`US`, `PW`, `ID`, `DEP`, `AUT`) VALUES (?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt2, "sssss", $Us, $Pw, $Id, $Dep, $aut);
    $req = mysqli_stmt_execute($stmt2);
	if($req == 1){
        echo "<script> alert(\"ลงทะเบียนสำเร็จ\") </script>";
        echo "<script> location='index.php'</script>";
    }else{
        echo "<script> alert(\"ลงทะเบียน ไม่ สำเร็จ, กรุณากรอกข้อมูลใหม่\") </script>";
        echo "<script> location='index.php'</script>";
    }
    //exit;*/
}

mysqli_close($conn);   // ปิดฐานข้อมูล
?>
