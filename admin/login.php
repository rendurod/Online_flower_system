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

  <title>Login</title>

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

</head>

<body class="bg-gradient-pink">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-xl-10 col-lg-12 col-md-9">
        <div class="card o-hidden border-0 shadow-lg my-5">
          <div class="card-body p-0">
            <div class="row">
              <div class="col-lg-6 d-none d-lg-block" style="background: url('img/flower4.jpg'); background-position: center; background-size: cover;"></div>
              <div class="col-lg-6">
                <div class="p-5">
                  <div class="text-center">
                    <h1 class="h4 text-gray-900 mb-4">Welcome Admin!</h1>
                  </div>
                  <form class="user" id="loginForm">
                    <div class="form-group">
                      <input type="text"
                        class="form-control form-control-user"
                        id="username"
                        name="username"
                        placeholder="Username"
                        required />
                    </div>
                    <div class="form-group">
                      <input type="password"
                        class="form-control form-control-user mb-5"
                        id="password"
                        name="password"
                        placeholder="Password"
                        required />
                    </div>
                    <div id="result" class="alert" style="display: none;"></div>
                    <button type="submit" class="btn btn-pink btn-user btn-block">
                      Login
                    </button>
                  </form>
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

  <script
    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"></script>

  <script>
    $(document).ready(function() {
      $('#loginForm').on('submit', function(e) {
        e.preventDefault();

        var username = $('#username').val();
        var password = $('#password').val();

        $.ajax({
          url: 'login_db.php',
          type: 'POST',
          data: {
            username: username,
            password: password
          },
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              $('#result')
                .removeClass('alert-danger')
                .addClass('alert-success')
                .html(response.message)
                .show();

              // รอ 1.5 วินาทีแล้วค่อย redirect
              setTimeout(function() {
                window.location.href = 'index.php';
              }, 1500);
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
              .html('เกิดข้อผิดพลาด กรุณาลองอีกครั้ง')
              .show();
          }
        });
      });
    });
  </script>
</body>

</html>