<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
// ถ้า admin ยังไม่ได้ login หรือ session ไม่ถูกต้อง ให้ส่ง error และหยุดการทำงาน
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Content-Type: application/json'); // กำหนด header เป็น JSON
    http_response_code(401); // 401 Unauthorized - ไม่มีสิทธิ์เข้าถึง
    echo json_encode(['success' => false, 'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อน', 'data' => []]); // เพิ่ม 'data' => [] เพื่อให้ client ไม่ error ตอน parse
    exit; // หยุดการทำงานของ script ทันที
}

// --- 2. ตั้งค่า Header สำหรับ Response ---
// (บรรทัดนี้อาจจะไม่จำเป็นถ้าโค้ดป้องกันด้านบนได้ตั้งไว้แล้ว แต่ใส่ไว้เพื่อความชัดเจนก็ได้)
header('Content-Type: application/json');

// --- 3. ส่วนเชื่อมต่อฐานข้อมูล SQLite ---
$db_file = __DIR__ . '/usernet_logger.sqlite'; // Path ไปยังไฟล์ฐานข้อมูล
$pdo = null; // กำหนดตัวแปร PDO เริ่มต้น
try {
    $pdo = new PDO('sqlite:' . $db_file); // สร้าง PDO object สำหรับเชื่อมต่อ
    // ตั้งค่า Attribute ของ PDO:
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // ให้โยน Exception เมื่อเกิด SQL error
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // ให้ดึงข้อมูลเป็น Associative Array
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // กำหนด Timeout 5 วินาที ถ้าฐานข้อมูลถูก lock
} catch (PDOException $e) {
    // ถ้าการเชื่อมต่อล้มเหลว ส่ง JSON error กลับไป
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage(), 'data' => []]);
    exit(); // หยุดการทำงาน
}

// --- 4. เตรียมตัวแปรสำหรับเก็บผลลัพธ์ ---
$activeUsers = []; // Array สำหรับเก็บข้อมูลผู้ใช้งานที่กำลัง active

// --- 5. ดึงข้อมูลผู้ใช้งานปัจจุบันจากฐานข้อมูล ---
try {
    // SQL Query:
    // - เลือกข้อมูลที่จำเป็นจากตาราง UsageLogs (ul) และ Users (u)
    // - JOIN สองตารางด้วย UserID
    // - กรอง (WHERE) เฉพาะรายการที่ LogoutTime ยังเป็นค่าว่าง (IS NULL) ซึ่งหมายถึงยังไม่เลิกใช้งาน
    // - เรียงลำดับ (ORDER BY) ตาม ServiceDate (วันล่าสุดก่อน) และ LoginTime (เวลาเข้าล่าสุดก่อน)
    $sql = "SELECT
                ul.LogID,
                ul.UserID,
                u.Prefix,
                u.FirstName,
                u.LastName,
                ul.ComputerNumber,
                ul.HeadphoneID,
                ul.LoginTime,
                ul.ServiceDate
            FROM
                UsageLogs ul
            JOIN
                Users u ON ul.UserID = u.UserID
            WHERE
                ul.LogoutTime IS NULL
            ORDER BY
                ul.ServiceDate DESC, ul.LoginTime DESC";

    // เนื่องจาก SQL query นี้ไม่มี input จากผู้ใช้โดยตรง (เช่น $_GET หรือ $_POST)
    // เราสามารถใช้ $pdo->query() ได้เลย ไม่จำเป็นต้องใช้ prepare และ bind
    $stmt = $pdo->query($sql);
    $activeUsers = $stmt->fetchAll(); // ดึงข้อมูลทั้งหมดที่ได้จาก query

    // ส่งผลลัพธ์กลับไปให้ Client เป็น JSON
    echo json_encode(['success' => true, 'data' => $activeUsers]);

} catch (PDOException $e) {
    // ถ้าเกิด Error ระหว่างการ Query หรือ Fetch ข้อมูล
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้งานปัจจุบัน: ' . $e->getMessage(), 'data' => []]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูล (เป็น good practice)
    $pdo = null;
}
?>