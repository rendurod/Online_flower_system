<?php
session_start();
require_once('config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE UserName = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['Password'])) {
            $_SESSION['adminid'] = $admin['id']; // เซ็ต session
            echo json_encode([
                'status' => 'success',
                'message' => 'เข้าสู่ระบบสำเร็จ กำลังนำคุณไปยังหน้าควบคุม...'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
            ]);
        }
    } catch(Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
}
?>