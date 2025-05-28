<?php
/**
 * ตรวจสอบการมีอยู่ของไฟล์และสร้าง URL ที่เหมาะสม
 * 
 * @param string $file ชื่อไฟล์ที่ต้องการตรวจสอบ
 * @param array $params พารามิเตอร์ที่จะส่งไปกับ URL (optional)
 * @return string URL ที่จะ redirect ไป
 */
function checkPageExists($file, $params = []) {
    $filePath = __DIR__ . '/../' . $file;
    
    // ถ้าไฟล์ไม่มีอยู่ ให้ redirect ไปที่ 404
    if (!file_exists($filePath)) {
        return '404.php';
    }

    // ถ้ามีพารามิเตอร์ ให้เพิ่มเข้าไปใน URL
    if (!empty($params)) {
        $queryString = http_build_query($params);
        return $file . '?' . $queryString;
    }

    return $file;
}