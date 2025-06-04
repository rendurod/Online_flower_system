<?php
require_once('config/db.php');

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
  $username = $_POST['username']; // Added to identify the admin
  $currentPassword = $_POST['currentPassword'];
  $newPassword = $_POST['newPassword'];

  try {
    // Verify admin by username and current password
    $stmt = $conn->prepare("SELECT * FROM admin WHERE UserName = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($currentPassword, $admin['Password'])) {
      echo json_encode([
        'status' => 'error',
        'message' => 'ชื่อผู้ใช้หรือรหัสผ่านปัจจุบันไม่ถูกต้อง'
      ]);
      exit;
    }

    // Update to new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE admin SET Password = :password WHERE id = :id");
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':id', $admin['id']);

    if ($updateStmt->execute()) {
      echo json_encode([
        'status' => 'success',
        'message' => 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่'
      ]);
      exit;
    } else {
      echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาลองอีกครั้ง'
      ]);
      exit;
    }
  } catch (PDOException $e) {
    echo json_encode([
      'status' => 'error',
      'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="" />
  <meta name="author" content="" />

  <title>SB Admin 2 - Change Password</title>

  <!-- LOGO -->
  <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
  <!-- Custom fonts for this template-->
  <link
    href="vendor/fontawesome-free/css/all.min.css"
    rel="stylesheet"
    type="text/css" />
  <link
    href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
    rel="stylesheet" />

  <!-- Custom styles for this template-->
  <link href="css/sb-admin-2.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />

  <!-- Inline CSS for password toggle icons -->
  <style>
    .password-toggle {
      position: relative;
    }

    .password-toggle .toggle-icon {
      color: black;
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
    }
  </style>
</head>

<body class="bg-gradient-pink">
  <div class="container">
    <!-- Outer Row -->
    <div class="row justify-content-center">
      <div class="col-xl-10 col-lg-12 col-md-9">
        <div class="card o-hidden border-0 shadow-lg my-5">
          <div class="card-body p-0">
            <!-- Nested Row within Card Body -->
            <div class="row">
              <div class="col-lg-6 d-none d-lg-block" style="background: url('img/change-password.jpg'); background-position: center; background-size: cover;"></div>
              <div class="col-lg-6">
                <div class="p-5">
                  <div class="text-center">
                    <h1 class="h4 text-gray-900 mb-2">
                      เปลี่ยนแปลงรหัสผ่าน
                    </h1>
                    <p class="mb-4 text-gray-600">
                      คุณสามารถเปลี่ยนรหัสผ่านของคุณได้โดยกรอกชื่อผู้ใช้ รหัสผ่านเดิมที่ลงทะเบียนไว้ในระบบ
                      และกรอกรหัสผ่านใหม่ที่ต้องการเปลี่ยนแปลง
                    </p>
                  </div>
                  <form class="user" id="changePasswordForm">
                    <div class="form-group">
                      <input
                        type="text"
                        class="form-control form-control-user"
                        id="username"
                        name="username"
                        placeholder="กรุณากรอกชื่อผู้ใช้"
                        required />
                    </div>
                    <div class="form-group password-toggle">
                      <input
                        type="password"
                        class="form-control form-control-user"
                        id="currentPassword"
                        name="currentPassword"
                        placeholder="กรุณากรอกรหัสผ่านปัจจุบัน"
                        required />
                      <i class="fas fa-eye toggle-icon" id="toggleCurrentPassword"></i>
                    </div>
                    <div class="form-group password-toggle">
                      <input
                        type="password"
                        class="form-control form-control-user"
                        id="newPassword"
                        name="newPassword"
                        placeholder="กรุณากรอกรหัสผ่านใหม่"
                        required />
                      <i class="fas fa-eye toggle-icon" id="toggleNewPassword"></i>
                    </div>
                    <div id="result" class="alert" style="display: none;"></div>
                    <button type="submit" class="btn btn-pink btn-user btn-block">
                      เปลี่ยนรหัสผ่าน
                    </button>
                  </form>
                  <hr />
                  <div class="text-center">
                    <a href="login.php">← กลับไปยังหน้าล็อคอิน</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Custom scripts for all pages-->
  <script src="js/sb-admin-2.min.js"></script>
  <script>
    $(document).ready(function() {
      // Password visibility toggle
      $('#toggleCurrentPassword').click(function() {
        var input = $('#currentPassword');
        var icon = $(this);
        if (input.attr('type') === 'password') {
          input.attr('type', 'text');
          icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
          input.attr('type', 'password');
          icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
      });

      $('#toggleNewPassword').click(function() {
        var input = $('#newPassword');
        var icon = $(this);
        if (input.attr('type') === 'password') {
          input.attr('type', 'text');
          icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
          input.attr('type', 'password');
          icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
      });

      // Form submission with confirmation
      $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();

        var username = $('#username').val();
        var currentPassword = $('#currentPassword').val();
        var newPassword = $('#newPassword').val();

        // Confirmation prompt
        if (!confirm('คุณต้องการยืนยันการเปลี่ยนรหัสผ่านใช่หรือไม่?')) {
          return;
        }

        $.ajax({
          url: 'change-password.php',
          type: 'POST',
          data: {
            username: username,
            currentPassword: currentPassword,
            newPassword: newPassword
          },
          dataType: 'json',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          success: function(response) {
            if (response.status === 'success') {
              $('#result')
                .removeClass('alert-danger')
                .addClass('alert-success')
                .html(response.message)
                .show();
              setTimeout(function() {
                window.location.href = 'login.php';
              }, 2000);
            } else {
              $('#result')
                .removeClass('alert-success')
                .addClass('alert-danger')
                .html(response.message)
                .show();
            }
          },
          error: function(xhr, status, error) {
            console.error(xhr.responseText);
            $('#result')
              .removeClass('alert-success')
              .addClass('alert-danger')
              .html('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง')
              .show();
          }
        });
      });
    });
  </script>
</body>

</html>