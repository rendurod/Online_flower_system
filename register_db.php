<?php
session_start();
require 'config/db.php';

header('Content-Type: application/json');

// เก็บ output ใด ๆ ที่อาจเกิดจาก warning/error
ob_start();

$response = [];

try {
    $minLength = 8;

    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$firstname) {
        $response = ["status" => "error", "msg" => "กรุณากรอกชื่อจริง"];
    } elseif (!$lastname) {
        $response = ["status" => "error", "msg" => "กรุณากรอกนามสกุล"];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ["status" => "error", "msg" => "กรุณากรอกอีเมลให้ถูกต้อง"];
    } elseif (strlen($password) < $minLength) {
        $response = ["status" => "error", "msg" => "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร"];
    } elseif ($password !== $confirm_password) {
        $response = ["status" => "error", "msg" => "รหัสผ่านไม่ตรงกัน"];
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $response = ["status" => "error", "msg" => "รหัสผ่านต้องมีอักษรพิมพ์ใหญ่อย่างน้อยหนึ่งตัว"];
    } elseif (!preg_match('/[a-z]/', $password)) {
        $response = ["status" => "error", "msg" => "รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อยหนึ่งตัว"];
    } elseif (!preg_match('/\d/', $password)) {
        $response = ["status" => "error", "msg" => "รหัสผ่านต้องมีตัวเลขอย่างน้อยหนึ่งตัว"];
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_members WHERE EmailId = ?');
        $stmt->execute([$email]);
        $userExists = $stmt->fetchColumn();

        if ($userExists) {
            $response = ["status" => "error", "msg" => "มีอีเมลนี้ในระบบแล้ว"];
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO tbl_members (FirstName, LastName, EmailId, Password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$firstname, $lastname, $email, $hashedPassword]);
            $response = ["status" => "success", "msg" => "สมัครสมาชิกสำเร็จ!"];
        }
    }
} catch (Exception $e) {
    $response = ["status" => "error", "msg" => "เกิดข้อผิดพลาดจากระบบ: " . $e->getMessage()];
}

// ลบ output อื่น ๆ ที่อาจเกิดจาก warning หรือ error
ob_clean();

// ส่งเฉพาะ JSON อย่างเดียว
echo json_encode($response);
exit;
?>
