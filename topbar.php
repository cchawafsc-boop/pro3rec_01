<div class="topbar1">  
  <a href="/index.php"><img src="/img/logo1.png"></a> 

  <?php 
  if(empty($_SESSION['us_name'])){
    echo "<button onclick=\"showForm()\" class=\"makeButton\">Login</button>";
    echo "<button onclick=\"goRegister()\" class=\"makeButton\">Register</button>";
  }else{
    echo "สวัสดี ".$_SESSION['us_name'];
    echo "<button onclick=\"goLogout()\" class=\"makeButton\">Logout</button>";
  }
  ?>  
</div>

<!-- Log-in pop-up window -->
<div class="form-lgin" id="loginForm">
  <form action="/login.php?<?php echo "pv_page=".$_SERVER['PHP_SELF']; ?>" method="post">
    <h2>โปรด Login</h2>
    <input  type="number"   id="lg_id" name="lg_id" placeholder="รหัสพนักงาน"  required max="9999" maxlength="4">
    <input  type="password" id="lg_pw" name="lg_pw" placeholder="พาสเวิร์ด" required>
    <button type="button"   id="cancelBtn" onclick="hideForm()">Cancel</button>
    <button type="submit"   id="okBtn">OK</button>
  </form>
</div>


<!-- Register pop-up window -->
<div class="form-lgin" id="registerForm">
  <form action="/register.php" method="post">
    <h2>ฟอร์มลงทะเบียน</h2>
    <input type="text"     id="reg_us"  name="regis_us"  placeholder="ชื่อพนักงาน"  required minlength="3" maxlength="12">
    <input type="password" id="reg_pw"  name="regis_pw"  placeholder="พาสเวิร์ด"    required minlength="4" maxlength="12">
    <input type="number"   id="reg_id"  name="regis_id"  placeholder="รหัสพนักงาน"  required min="0" max="9999">
    <select id="reg_dep" name="regis_Dep" required>
      <option value="">-- แผนก --</option>
      <option value="Pro1">Pro.1</option>
      <option value="Pro2">Pro.2</option>
      <option value="Pro3">Pro.3</option>
      <option value="CECA">CE/CA</option>
      <option value="PE">PE</option>
      <option value="FE">FE</option>
      <option value="SALE">SALE</option>
      <option value="QAQC">QA/QC</option>
      <option value="Admin">Admin</option>
    </select>
    <button type="button" id="regCancelBtn" onclick="hideRegisterForm()">Cancel</button>
    <button type="submit" id="regOkBtn">ลงทะเบียน</button>
  </form>
</div>


<script>
  function showForm() {
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('lg_id').value = "";
    document.getElementById('lg_pw').value = "";    
  }

  function hideForm() {
    document.getElementById('loginForm').style.display = 'none';
  }

  function goRegister() {
    document.getElementById('registerForm').style.display = 'block';
    document.getElementById('reg_us').value = "";
    document.getElementById('reg_pw').value = "";
    document.getElementById('reg_id').value = "";
    document.getElementById('reg_dep').value = "";
  }

  function hideRegisterForm() {
    document.getElementById('registerForm').style.display = 'none';
  }

  function goLogout(){
    window.location.href = "/logout.php?pv_page=" + <?php echo json_encode($_SERVER['PHP_SELF']); ?>;
  }
</script>
