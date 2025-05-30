<?php
session_start();
session_destroy(); // ทำลาย session ทั้งหมด
unset($_SESSION['adminid']); // ลบ session adminid โดยเฉพาะ
header("Location: login.php");
exit();
?>