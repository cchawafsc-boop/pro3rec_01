<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0" >
  <title>QR Scan</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
</head>
<body>
  <?php
    require('../topbar.php');
  ?>

  <div class="main-menu">
    <h2>QR Code Scan</h2>

    <div id="qr-cam" style="max-width:400px; margin:0 auto;">
      <video id="qr-video" style="width:100%;" playsinline></video>
    </div>
    <canvas id="qr-canvas" style="display:none;"></canvas>

    <p>
      <input type="text" id="qr-result" placeholder="QR code result" readonly>
    </p>
  </div>

  <script>
    var video   = document.getElementById('qr-video');
    var canvas  = document.getElementById('qr-canvas');
    var ctx     = canvas.getContext('2d');
    var resultInput = document.getElementById('qr-result');
    var scanning = false;

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function(stream) {
        video.srcObject = stream;
        video.setAttribute('playsinline', true);
        video.play();
        scanning = true;
        requestAnimationFrame(tick);
      })
      .catch(function(err) {
        resultInput.value = 'Camera error: ' + err.message;
      });

    function tick() {
      if (!scanning) return;

      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var code = jsQR(imageData.data, imageData.width, imageData.height);

        if (code) {
          resultInput.value = code.data;
        }
      }

      requestAnimationFrame(tick);
    }
  </script>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>
</body>
</html>
