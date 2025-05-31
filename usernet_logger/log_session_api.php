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

// --- 5. ตรวจสอบข้อมูลเบื้องต้นที่จำเป็นสำหรับการบันทึก Log ---
// (การตรวจสอบอื่นๆ เช่น รูปแบบวันที่, computerNumber ควรทำใน JavaScript หรือเพิ่มที่นี่ก็ได้)
if (empty($data['userID']) || !is_numeric($data['userID'])) {
    echo json_encode(['success' => false, 'message' => 'UserID ไม่ถูกต้อง']);
    exit();
}
if (empty($data['serviceDate'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุวันที่เข้าใช้บริการ']);
    exit();
}
if (empty($data['loginTime'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุเวลาเข้า']);
    exit();
}
if (empty($data['computerNumber'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเลือกเครื่องคอมพิวเตอร์']);
    exit();
}
// หมายเหตุ: โค้ดนี้เป็นเวอร์ชันที่ยังไม่ได้รับ logoutTime, headphoneReturned, notes โดยตรงจากฟอร์ม Log Entry นี้
// หากต้องการให้รองรับการลงข้อมูลย้อนหลังที่สมบูรณ์ (รวมเวลาออก, สถานะคืนหูฟัง, โน้ต) จะต้องปรับปรุงส่วนนี้
// และปรับปรุง SQL INSERT ด้านล่างให้รวม fields เหล่านั้นด้วย

// --- 6. กำหนดค่าตัวแปรจากข้อมูลที่ได้รับ ---
$userID         = $data['userID'];
$serviceDate    = $data['serviceDate'];
$loginTime      = $data['loginTime'];
$usageDetails   = isset($data['usageDetails']) ? trim($data['usageDetails']) : ''; // ถ้ามีก็ใช้, ไม่มีก็เป็นค่าว่าง
$computerNumber = $data['computerNumber'];
// จัดการ headphoneID: ถ้าส่งมาเป็น "NO_HEADPHONE" หรือค่าว่าง ให้เก็บเป็น NULL ในฐานข้อมูล
$headphoneID    = (isset($data['headphoneID']) && $data['headphoneID'] !== 'NO_HEADPHONE' && !empty($data['headphoneID'])) ? $data['headphoneID'] : null;

// สำหรับเวอร์ชันนี้ `logoutTime`, `headphoneReturned`, `notes` จะยังไม่ถูกตั้งค่าจาก input นี้โดยตรง
// จะถูกจัดการผ่าน `logout_session_api.php` และ `update_notes_api.php`
$logoutTime        = isset($data['logoutTime']) && !empty($data['logoutTime']) ? $data['logoutTime'] : null;
$headphoneReturned = isset($data['headphoneReturned']) && $data['headphoneReturned'] !== '' ? (int)$data['headphoneReturned'] : null;
$notes             = isset($data['notes']) && $data['notes'] !== null ? trim($data['notes']) : null;


// --- 7. ตรวจสอบการ Log In ซ้ำซ้อน (เฉพาะกรณีที่เป็นการ Log in ใหม่จริงๆ คือ logoutTime ไม่ได้ถูกส่งมาด้วย) ---
if ($logoutTime === null) { // ตรวจสอบเฉพาะเมื่อไม่ได้ระบุเวลาออก (คือเป็นการเริ่ม session ใหม่)
    try {
        $check_sql = "SELECT LogID FROM UsageLogs WHERE UserID = :userID_param AND LogoutTime IS NULL";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':userID_param', $userID, PDO::PARAM_INT);
        $check_stmt->execute();
        $active_session = $check_stmt->fetch();

        if ($active_session) {
            // ถ้าพบ session ที่ยัง active อยู่สำหรับ UserID นี้
            // ดึงข้อมูลชื่อผู้ใช้เพื่อแสดงในข้อความแจ้งเตือน
            $user_info_sql = "SELECT Prefix, FirstName, LastName FROM Users WHERE UserID = :userID_param_info";
            $user_stmt = $pdo->prepare($user_info_sql);
            $user_stmt->bindParam(':userID_param_info', $userID, PDO::PARAM_INT);
            $user_stmt->execute();
            $user = $user_stmt->fetch();
            $userName = $user ? (($user['Prefix'] ?? '') . $user['FirstName'] . ' ' . $user['LastName']) : "ผู้ใช้ ID: {$userID}";

            echo json_encode([
                'success' => false,
                'message' => "ผู้ใช้งาน \"{$userName}\" กำลังใช้งานระบบอยู่แล้ว (LogID: {$active_session['LogID']}) กรุณาทำการเลิกใช้งาน (Logout) จาก session เก่าก่อน."
            ]);
            $pdo = null; // ปิดการเชื่อมต่อ
            exit();
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการตรวจสอบ session ผู้ใช้: ' . $e->getMessage()]);
        $pdo = null; // ปิดการเชื่อมต่อ
        exit();
    }
}
// --- จบส่วนการตรวจสอบ Log In ซ้ำซ้อน ---


// --- 8. เตรียมและ Execute คำสั่ง SQL INSERT ---
// SQL นี้จะบันทึกข้อมูลการเข้าใช้งานใหม่ (หรือข้อมูลย้อนหลังที่กรอกครบ)
$sql = "INSERT INTO UsageLogs 
            (UserID, ServiceDate, LoginTime, LogoutTime, UsageDetails, ComputerNumber, HeadphoneID, HeadphoneReturned, Notes)
        VALUES 
            (:userID_val, :serviceDate_val, :loginTime_val, :logoutTime_val, :usageDetails_val, :computerNumber_val, :headphoneID_val, :headphoneReturned_val, :notes_val)";

try {
    $stmt = $pdo->prepare($sql);
    // Bind ค่าเข้ากับ Parameters
    $stmt->bindParam(':userID_val', $userID, PDO::PARAM_INT);
    $stmt->bindParam(':serviceDate_val', $serviceDate, PDO::PARAM_STR);
    $stmt->bindParam(':loginTime_val', $loginTime, PDO::PARAM_STR);
    $stmt->bindParam(':logoutTime_val', $logoutTime, $logoutTime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':usageDetails_val', $usageDetails, PDO::PARAM_STR);
    $stmt->bindParam(':computerNumber_val', $computerNumber, PDO::PARAM_STR);
    $stmt->bindParam(':headphoneID_val', $headphoneID, $headphoneID === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':headphoneReturned_val', $headphoneReturned, $headphoneReturned === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':notes_val', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute(); // สั่งให้ SQL ทำงาน

    // ส่ง Response กลับแจ้งว่าสำเร็จ
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลการใช้งานสำเร็จ!']);

} catch (PDOException $e) {
    // ถ้าเกิด Error ระหว่างการ INSERT
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูลการใช้งาน: ' . $e->getMessage()]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูลเสมอ
    $pdo = null;
}
?>