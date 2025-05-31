<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
// ถ้า admin ยังไม่ได้ login หรือ session ไม่ถูกต้อง ให้ส่ง error และหยุดการทำงาน
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401); // 401 Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

// --- 2. ตั้งค่า Header สำหรับ Response ---
header('Content-Type: application/json');

// --- 3. ส่วนเชื่อมต่อฐานข้อมูล SQLite ---
$db_file = __DIR__ . '/usernet_logger.sqlite'; // Path ไปยังไฟล์ฐานข้อมูล
$pdo = null;
try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);         // ให้โยน Exception เมื่อเกิด SQL error
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);    // ให้ดึงข้อมูลเป็น Associative Array
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);                              // กำหนด Timeout 5 วินาที ถ้าฐานข้อมูลถูก lock
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()]);
    exit();
}

// --- 4. รับและประมวลผลข้อมูล Input ที่ส่งมาจาก Client (JavaScript) ---
$json_data = file_get_contents('php://input'); // อ่านข้อมูล JSON ดิบ
$data = json_decode($json_data, true);         // แปลง JSON string เป็น PHP Associative Array

// --- 5. ตรวจสอบข้อมูลเบื้องต้นที่จำเป็นสำหรับการ Logout ---
if (empty($data['logID']) || !is_numeric($data['logID'])) {
    echo json_encode(['success' => false, 'message' => 'LogID ไม่ถูกต้องสำหรับการเลิกใช้งาน']);
    exit();
}

// --- 6. กำหนดค่าตัวแปรจากข้อมูลที่ได้รับ ---
$logID = (int)$data['logID']; // LogID ของรายการใช้งานที่จะทำการ Logout

// ค่า headphoneReturned ที่ส่งมาจาก JavaScript จะเป็น boolean (true/false) หรือ null
// เราจะแปลงเป็น 1 ถ้า true, 0 ถ้า false
// ถ้า $data['headphoneReturned'] ไม่ได้ถูกตั้งค่า (เช่น ไม่มีการยืมหูฟังตั้งแต่แรก) หรือเป็น null, $phpHeadphoneReturnedState จะเป็น 0
$phpHeadphoneReturnedState = (isset($data['headphoneReturned']) && $data['headphoneReturned'] === true) ? 1 : 0;

// ตรวจสอบว่ามีการยืมหูฟังใน session นี้จริงหรือไม่ จาก headphoneID ที่ส่งมา
// ค่า $data['headphoneID'] จะมาจาก currentLogoutHeadphoneID ใน JavaScript
$phpHasHeadphone = isset($data['headphoneID']) && $data['headphoneID'] !== null && $data['headphoneID'] !== 'NO_HEADPHONE' && $data['headphoneID'] !== '';

// --- 7. เตรียมคำสั่ง SQL UPDATE ---
// ตั้ง LogoutTime เป็นเวลาปัจจุบันของ Server (SQLite จะใช้ strftime เพื่อให้ได้ Local Time)
$sql = "UPDATE UsageLogs
        SET LogoutTime = strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')";

// ถ้า session นี้มีการยืมหูฟัง ($phpHasHeadphone เป็น true)
// ให้เพิ่มส่วนการอัปเดตคอลัมน์ HeadphoneReturned เข้าไปใน SQL query
if ($phpHasHeadphone) {
    $sql .= ", HeadphoneReturned = :headphoneReturnedValue_param";
}

// อัปเดตเฉพาะแถวที่มี LogID ตรงกัน และยังไม่ได้มีการ Logout (LogoutTime IS NULL)
// เพื่อป้องกันการอัปเดตซ้ำซ้อน หรืออัปเดตรายการที่ Logout ไปแล้ว
$sql .= " WHERE LogID = :logID_param AND LogoutTime IS NULL";

try {
    $stmt = $pdo->prepare($sql); // เตรียม SQL query
    
    // Bind ค่า LogID เข้ากับ placeholder
    $stmt->bindParam(':logID_param', $logID, PDO::PARAM_INT);

    // ถ้ามีการยืมหูฟัง ให้ bind ค่าสถานะการคืนหูฟังด้วย
    if ($phpHasHeadphone) {
        $stmt->bindParam(':headphoneReturnedValue_param', $phpHeadphoneReturnedState, PDO::PARAM_INT);
    }

    $rowCount = $stmt->execute(); // สั่งให้ SQL query ทำงาน และเก็บจำนวนแถวที่ได้รับผลกระทบ

    if ($rowCount > 0) { // ถ้ามีแถวข้อมูลถูกอัปเดต (ปกติควรจะเป็น 1 แถว)
        echo json_encode(['success' => true, 'message' => 'บันทึกการเลิกใช้งานสำเร็จ!']);
    } else {
        // ถ้าไม่มีแถวถูกอัปเดต อาจจะเกิดจาก:
        // 1. LogID ที่ส่งมาไม่ถูกต้อง (ไม่มี LogID นี้ในฐานข้อมูล)
        // 2. รายการ LogID นี้ได้ถูก Logout ไปแล้ว (LogoutTime ไม่ใช่ NULL)
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกการเลิกใช้งานได้ อาจมีการ Logout ไปแล้ว หรือ LogID ไม่ถูกต้อง']);
    }

} catch (PDOException $e) {
    // ถ้าเกิด Error ระหว่างการ UPDATE ข้อมูล
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $e->getMessage()]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูลเสมอ
    $pdo = null;
}
?>