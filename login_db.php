<?php
session_start();
// Include the database connection code
require 'config/db.php';
// Retrieve and validate user input
// if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
// }

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array("status" => "error", "msg" => "กรุณากรอกอีเมลที่ถูกต้อง"));
    // $_SESSION['error'] = "Please enter a valid email address";
    // header('location: register.php');
} else {

    // Prepare and execute the SQL query
    try {
        $stmt = $conn->prepare("SELECT * FROM tblusers WHERE EmailId = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $hashedPassword = $row['Password'];

            // Verify the password
            if (password_verify($password, $hashedPassword)) {
                echo json_encode(array("status" => "success", "msg" => "เข้าสู่ระบบสำเร็จ<br>ยินดีต้อนรับ!"));
                // $_SESSION['success'] = "Login successful!";
                $_SESSION['user_login'] = $row['id'];
                // header('location: dashboard.php');
                // Perform additional actions, such as setting session variables or redirecting the user
            } else {
                echo json_encode(array("status" => "error", "msg" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"));
                // $_SESSION['error'] = "Invalid credentials";
                // header('location: login.php');
            }
        } else {
            // Invalid username
            echo json_encode(array("status" => "error", "msg" => "ไม่พบอีเมลนี้ในระบบ"));
            // $_SESSION['error'] = "Invalid credentials";
            // header('location: login.php');
        }
    } catch (PDOException $e) {
        // Login failed
        echo json_encode(array("status" => "error", "msg" => "มีข้อผิดพลาดเกิดขึ้น โปรดลองอีกครั้ง!"));
        // echo "Login failed: " . $e->getMessage();
        // $_SESSION['error'] = "Invalid credentials";
        // header('location: login.php');
    }
}


?>
