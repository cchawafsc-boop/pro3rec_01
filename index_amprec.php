<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0" >
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="./style01.css">
  
</head>
<body>

<?php
  session_start();
  require('connect.php');
  require('init_session.php');
  require('topbar.php');
?>

<!--div class="topbar1">
  <form>
  <?php/* 
  if(empty($_SESSION['us_name'])){
    echo "<input type=\"submit\" formaction=\"login.html\"    value=\"Login\"    class=\"makeButton\">";
    echo "<input type=\"submit\" formaction=\"register.html\" value=\"Register\" class=\"makeButton\">";
  }else{
    echo "สวัสดี ".$_SESSION['us_name'];
    echo "<input type=\"submit\" formaction=\"logout.php\"   value=\"logout\" class=\"makeButton\">";
  }
  */?>
  </form>
</div-->

<!--div class="topbar2">
  <form id="F1" name="F1" method="post" action="index.php">
    ไลน์:
    <select name="F_line" id="id_line">
      <option value="S3">S3</option>
      <option value="S4">S4</option>
    </select>
    วัน:
    <input type="date" name="F_Date" id="F_Date" value="<?php/* echo $_SESSION['S_Date'] ;*/?>"/>
    <input type="submit" name="Read"  value="อ่านค่าใหม่"  formaction="index.php"/>
    <?php/*
    if($_SESSION['us_aut']<=0){
      echo "<input type=\"submit\" formaction=\"newrecord.php\"   value=\"กรอกค่าใหม่\" class=\"makeButton\">";
    }
    */?>
  
</div-->

<table>
  <tr>
    <th> Process </th>
    <th class="ColPackage"> <div>Package</div> </th>
    <th class="ColPlateNo"> <div>Plate no.</div> </th>
    <th class="ColTime">    <div>Time</div> </th>
    <th class="ColSlot">    <div>ช่องชุบ</div> </th>
    <th class="ColRackNo">  <div>Rack no.</div> </th>    
    <th class="ColRackCode"><div>Rack code</div> </th>
    <th class="ColHangerNo"><div>Hanger no.</div> </th>
    <th class="ColFrontAmp"><div>Amp หน้าแร็ก</div> </th>    
    <th class="ColRearAmp"> <div>Amp หลังแร็ก</div> </th>
    <th class="ColRectAmp"> <div>Amp หน้าตู้</div> </th>    
    <th class="ColNegativeAmp"><div>Amp ขั้วลบ</div> </th>
    <th class="ColOpr">     <div>พนง.</div> </th>    
    <th class="ColNote">    <div>Note</div> </th>
    <th <?php 
    if(isset($_SESSION['Authorize'])){
      if($_SESSION['Authorize']>1){ echo "style=\"display:none\""; }
    }else{ echo "style=\"display:none\"";} ?>>Edit</th>    
  </tr>

  <?php
  /*$sql = "SELECT * FROM `tb_user` WHERE `ID` LIKE \"".strval($Us_id)."\"";
  $result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
  $n_row = mysqli_num_rows($result);
  $n_fld = mysqli_num_fields($result);
  */
  if(isset($n_row)){
    for($i=0;$i<$n_row;$i++){?>
      <tr style=<?php if($i%2==0){ echo "background:lightskyblue";}else{ echo "background:lightyellow"; }?>>
        <td class="ColProcess">   <label id=<?php echo "F_Tb_Process" ?>> <?php echo $record[$i]['Process'] ?> </label> </td>
        <td> <label id=<?php echo "F_CName".$i ?>>      <?php echo $record[$i]['ChemName'] ?> </label> </td>
        <td class="ColConcenStd"> <label id=<?php echo "F_CStd".$i ?>>   <?php echo $record[$i]['ConcenStd']." ".$record[$i]['ConcenUnit'] ?> </label> </td>
        <td class="ColOptimum">   <label id=<?php echo "F_Opt".$i ?>>    <?php echo $record[$i]['Optimum']." ".$record[$i]['ConcenUnit'] ?> </label> </td>
        <td class="ColCtrlLimit"> <label id=<?php echo "F_CLimit".$i ?>> <?php echo $record[$i]['CtrLimit']." ".$record[$i]['ConcenUnit'] ?> </label> </td>  
        <td> <label id=<?php echo "F_Time".$i ?>>    <?php echo substr($record[$i]['Time'],0,5) ?> </label> </td>
        <td> <label id=<?php echo "F_AnaVal".$i ?>>  <?php echo $record[$i]['AnaVal']." ".$record[$i]['ConcenUnit'] ?> </label> </td>
        <td> <label id=<?php echo "F_AddVal".$i ?>>  <?php echo $record[$i]['AddVal']." ".$record[$i]['AddUnit'] ?> </label> </td>
        <td> <label id=<?php echo "F_VerVal".$i ?>>  <?php echo $record[$i]['VerVal']." ".$record[$i]['ConcenUnit']  ?> </label> </td>
        <td> <label id=<?php echo "F_AnaZer".$i ?>>  <?php 
          $analyzer_req = "SELECT `US` FROM `tbuser` WHERE `ComID` = \"".$record[$i]['ComID']."\"";
          $analyzer_res = mysqli_query($conn,$analyzer_req) or die(mysqli_error($conn));
          $analyzer_fetch = mysqli_fetch_array($analyzer_res);
          echo $analyzer_fetch['US']; ?> </label> </td>
        <td> <label id=<?php echo "F_EC_Note".$i ?>>    <?php echo $record[$i]['Note'] ?> </label> </td>
        <td <?php
              if(isset($_SESSION['Authorize'])){
                if($_SESSION['Authorize']>1){echo "style=\"display:none\"";}
              }else{ echo "style=\"display:none\"";} ?>>
            <button type="submit" style="width:40px" name="Rec_Del" value=<?php echo $i?> formaction="index_del.php"> ลบ </button> 
        </td>
      </tr>
      <?php
    }

    if(isset($_SESSION['Authorize'])){
      if($_SESSION['Authorize']<=1){
        // ===== This's for create all possible rows for add a new record. =====
        // ---------- Request all possible ChemName for the precess. -----------
        $sql = "SELECT `ChemName`,`ConcenUnit`,`ConcenStd`,`Optimum`,`ControlLimit`,`AddingUnit` FROM `tb_line` ";
        $sql = $sql."WHERE `LineName` =\"".$_SESSION['S_Line']."\" AND `Process`=\"".$_SESSION['S_Process']."\"";
        $nr_res =  mysqli_query($conn,$sql) or die(mysqli_error($conn));
        $nr_n_row = mysqli_num_rows($nr_res);
        //echo "sql = $sql <br>";
        //echo "CCA_n_row = $nr_n_row <br>";
        if($nr_n_row>0){
          for($i=0; $i<$nr_n_row; $i++){
            $nr_record[$i] = mysqli_fetch_array($nr_res);
          }
        }
        for($i=0; $i<$nr_n_row; $i++){
          ?>
          <tr>
          <td class="ColProcess"> <label id=<?php echo "F_Process_Add" ?>> <?php echo $_SESSION['S_Process'] ?> </label> </td>
          <td class="ColChemName">     <input type="text" size="6" style="color:#aaa" readonly
            name=<?php echo "ChemName_Add".$i ?> value=<?php echo $nr_record[$i]['ChemName'] ?>></td>
          
          <td class="ColConcenStd">     <input type="text" size="3" style="color:#aaa" readonly
            name=<?php echo "ConStd_Add".$i ?>   value=<?php echo $nr_record[$i]['ConcenStd'] ?>>
            <label style="padding:0px"> <?php echo $nr_record[$i]['ConcenUnit'] ?> </label></td>
          <td class="ColOptimum">       <input type="number" style="width:4em; color:#aaa" step="0.01" readonly 
            name=<?php echo "Opt_Add".$i ?>      value=<?php echo $nr_record[$i]['Optimum'] ?>>
            <label style="padding:0px"> <?php echo $nr_record[$i]['ConcenUnit'] ?> </label></td></td>
          
          <td class="ColCtrlLimit">     <input type="text" size="3" style="color:#aaa" readonly
            name=<?php echo "CLimit_Add".$i ?>   value=<?php echo $nr_record[$i]['ControlLimit'] ?>>
            <label style="padding:0px"> <?php echo $nr_record[$i]['ConcenUnit'] ?> </label></td></td>
          <td class="ColTime">          <input type="time" style="width: 5em"
            name=<?php echo "Time_Add".$i ?>        value=""></td>
          
          <td class="ColAnaVal">        <input type="number" style="width:4em" step="0.01" 
            name=<?php echo "AnaVal_Add".$i ?>      value="">
            <label style="padding:0px"> <?php echo $nr_record[$i]['ConcenUnit'] ?> </label></td></td>
          <td class="ColAddVal">        <input type="number" style="width:4em" step="0.01" 
            name=<?php echo "AddVal_Add".$i ?>      value="">
            <label style="padding:0px"> <?php echo $nr_record[$i]['AddingUnit'] ?> </label></td></td>
          
          <td class="ColVerVal">        <input type="number" style="width:4em" step="0.01"
            name=<?php echo "VerVal_Add".$i ?>      value="">
            <label style="padding:0px"> <?php echo $nr_record[$i]['ConcenUnit'] ?> </label></td></td>
          <td class="ColAnalyzer">      <label> <?php echo $_SESSION['Username'] ?> </label> </td>
          
          <td class="ColNote">          <input type="text" 
            name=<?php echo "Note_Add".$i ?>        value=""></td>
          <td><button type="submit" style="width:40px" name="Rec_Add" value=<?php echo $i ?> formaction="index_add.php"> เพิ่ม </button></td>
          </tr><?php
        }
      }
    }  
  }
  ?>
</table> 

</form>    

<?php mysqli_close($conn);   // ปิดฐานข้อมูล
?>

</body>
</html>