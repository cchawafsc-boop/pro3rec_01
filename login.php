<?php
// start the session
session_start();
require('connect.php');

// get the User's info from the form
$lg_id = $_POST['lg_id'];
$lg_pw = hash('sha256', $_POST['lg_pw']);
if(isset($_REQUEST['pv_page'])){$pv_page = $_REQUEST['pv_page'];}

// Check the redundant user
$sql = "SELECT * FROM `tb_user` WHERE `ID` LIKE \"".strval($lg_id)."\"";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$n_row = mysqli_num_rows($result);


if($n_row == 1){
  $record = mysqli_fetch_array($result);
  //echo "Password from login page: ".$Pw."<br>";
  //echo "Passwork from Database:   ".$record['PW']."<br>";
  if(($lg_id == $record['ID']) and ($lg_pw == $record['PW']) ){
    $_SESSION['us_name'] = $record['US'];
    $_SESSION['us_id']   = $record['ID'];
    $_SESSION['us_dep']  = $record['DEP'];
    $_SESSION['us_aut']  = $record['AUT'];
    // AUT = 0 : Top authorized; able to read, write, delete & edit 
    // AUT = 1 : 2nd authorized; able to read, write & delete
    // AUT = 2 : 3rd authorized; able to read & write
    // AUT = 3 : lowest authorized; able to read only
    echo "สวัสดี ".$_SESSION['us_name']." login successfully.</br>";
    if(isset($pv_page)){
      //echo "previous page is: ".$pv_page;
      //header("Location: /prodrec/pro1_lotcard/pro1_lotcard.php");
      header("Location:".$pv_page);
    }else{
      header("Location: index.php");
    }
  }else{
    echo "<script> alert(\"รหัสพาสเวิร์ดไม่ถูกต้อง \u000aโปรดกรอกข้อมูล login ใหม่\") </script>";
    if(isset($pv_page)){
      echo "<script> location=\"".$pv_page."\"</script>";
    }else{
      echo "<script> location='index.php'</script>";
    }
}

}else{
  echo "<script> alert(\"รหัสพนักงานไม่มีในฐานข้อมูล \u000aโปรด login ใหม่ หรือลงทะเบียนใหม่\") </script>";
  if(isset($pv_page)){
    echo "<script> location=\"".$pv_page."\"</script>";
  }else{
    echo "<script> location='index.php'</script>";
  }
}

mysqli_close($conn);   // ปิดฐานข้อมูล
?>
