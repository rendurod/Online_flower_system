<?php
ob_start(); // ⭐ ป้องกัน output ที่ทำให้ JSON พัง
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array("status" => "error", "msg" => "กรุณากรอกอีเมลที่ถูกต้อง"));
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_members WHERE EmailId = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $hashedPassword = $row['Password'];

            if (password_verify($password, $hashedPassword)) {
                $_SESSION['user_login'] = $row['ID'];
                echo json_encode(array("status" => "success", "msg" => "เข้าสู่ระบบสำเร็จ<br>ยินดีต้อนรับ!"));
            } else {
                echo json_encode(array("status" => "error", "msg" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"));
            }
        } else {
            echo json_encode(array("status" => "error", "msg" => "ไม่พบอีเมลนี้ในระบบ"));
        }
    } catch (PDOException $e) {
        echo json_encode(array("status" => "error", "msg" => "เกิดข้อผิดพลาด โปรดลองใหม่อีกครั้ง"));
    }
}
?>
