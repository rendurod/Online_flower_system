<?php
session_start();
require 'config/db.php';
$minLength = 8;

// Retrieve and validate user input
$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password']; // รับ confirm password จากฟอร์ม

// Validation checks
if (!$firstname) {
    echo json_encode(array("status" => "error", "msg" => "กรุณากรอกชื่อจริง"));
} else if (!$lastname) {
    echo json_encode(array("status" => "error", "msg" => "กรุณากรอกนามสกุล"));
} else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array("status" => "error", "msg" => "กรุณากรอกอีเมล"));
} else if (strlen($password) < $minLength) {
    echo json_encode(array("status" => "error", "msg" => "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร"));
} else if ($password !== $confirm_password) {  // ตรวจสอบ confirm password
    echo json_encode(array("status" => "error", "msg" => "รหัสผ่านไม่ตรงกัน"));
} else if (!preg_match('/[A-Z]/', $password)) {
    echo json_encode(array("status" => "error", "msg" => "รหัสผ่านต้องมีอักษรตัวพิมพ์ใหญ่อย่างน้อยหนึ่งตัว"));
} else if (!preg_match('/[a-z]/', $password)) {
    echo json_encode(array("status" => "error", "msg" => "รหัสผ่านจะต้องมีตัวอักษรพิมพ์เล็กอย่างน้อยหนึ่งตัว"));
} else if (!preg_match('/\d/', $password)) {
    echo json_encode(array("status" => "error", "msg" => "รหัสผ่านต้องมีตัวเลขอย่างน้อยหนึ่งตัว"));
} else {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM tblusers WHERE EmailId = ?');
    $stmt->execute([$email]);
    $userExists = $stmt->fetchColumn();

    if ($userExists) {
        echo json_encode(array("status" => "error", "msg" => "มีอีเมลนี้ในระบบแล้ว"));
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO tblusers (FirstName, LastName, EmailId, Password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$firstname, $lastname, $email, $hashedPassword]);
            echo json_encode(array("status" => "success", "msg" => "สมัครสมาชิกสำเร็จ!"));
        } catch (PDOException $e) {
            echo json_encode(array("status" => "error", "msg" => "มีข้อผิดพลาดเกิดขึ้น โปรดลองอีกครั้ง!"));
        }
    }
}

?>

