<?php
    // ข้อมูลการเชื่อมต่อกับฐานข้อมูล
    $db_host = "localhost";
    $db_username = "root";
    $db_pwd = "root";
    $db_name = "db_flowershop";

    // สร้างการเชื่อมต่อกับฐานข้อมูล
    $mysqli = new mysqli($db_host, $db_username, $db_pwd, $db_name);

    // ตรวจสอบการเชื่อมต่อ
    if ($mysqli->connect_errno) {
        die("Failed to connect to MySQL: " . $mysqli->connect_error);
    }

    // เปลี่ยนการเข้ารหัสอักขระเป็น UTF-8
    if (!$mysqli->set_charset("utf8")) {
        die("Error loading character set utf8: " . $mysqli->error);
    }

    // ถ้าเชื่อมต่อสำเร็จ ให้แสดงข้อความ
    // echo "Connection Success!";

    // ปิดการเชื่อมต่อ (ถ้าต้องการ)S
    // $mysqli->close();
?>