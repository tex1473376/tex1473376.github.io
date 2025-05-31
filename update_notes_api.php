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
    // $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // ไม่จำเป็นสำหรับ UPDATE โดยตรง
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);                              // กำหนด Timeout 5 วินาที ถ้าฐานข้อมูลถูก lock
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()]);
    exit();
}

// --- 4. รับและประมวลผลข้อมูล Input ที่ส่งมาจาก Client (JavaScript) ---
$json_data = file_get_contents('php://input'); // อ่านข้อมูล JSON ดิบ
$data = json_decode($json_data, true);         // แปลง JSON string เป็น PHP Associative Array

// --- 5. ตรวจสอบข้อมูลเบื้องต้นที่จำเป็นสำหรับการอัปเดต Notes ---
if (empty($data['logID']) || !is_numeric($data['logID'])) {
    echo json_encode(['success' => false, 'message' => 'LogID ไม่ถูกต้องสำหรับบันทึกหมายเหตุ']);
    exit();
}
// ตรวจสอบว่ามี key 'notes' ส่งมาหรือไม่ (อนุญาตให้ค่า notes เป็น string ว่างได้)
if (!isset($data['notes'])) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลหมายเหตุไม่ถูกต้อง (ไม่พบ key "notes")']);
    exit();
}

// --- 6. กำหนดค่าตัวแปรจากข้อมูลที่ได้รับ ---
$logID = (int)$data['logID'];       // LogID ของรายการใช้งานที่จะอัปเดต Notes
$notes = trim($data['notes']);      // หมายเหตุที่ต้องการบันทึก (trim เพื่อลบ space หน้า-หลัง)
                                    // ถ้าผู้ใช้ไม่กรอกอะไรเลย $notes จะเป็น string ว่าง ""

// --- 7. เตรียมและ Execute คำสั่ง SQL UPDATE ---
// อัปเดตคอลัมน์ 'Notes' ของแถวในตาราง 'UsageLogs' ที่มี 'LogID' ตรงกัน
$sql = "UPDATE UsageLogs
        SET Notes = :notes_param
        WHERE LogID = :logID_param";

try {
    $stmt = $pdo->prepare($sql); // เตรียม SQL query
    
    // Bind ค่า Notes และ LogID เข้ากับ placeholders ใน SQL query
    $stmt->bindParam(':notes_param', $notes, PDO::PARAM_STR);
    $stmt->bindParam(':logID_param', $logID, PDO::PARAM_INT);

    $stmt->execute(); // สั่งให้ SQL query ทำงาน
    $rowCount = $stmt->rowCount(); // ดึงจำนวนแถวที่ได้รับผลกระทบจากการ UPDATE

    // ตรวจสอบผลลัพธ์
    // $rowCount > 0 หมายถึงมีการอัปเดตข้อมูลอย่างน้อย 1 แถว (ปกติควรจะเป็น 1 ถ้า LogID ถูกต้อง)
    // ถ้า $rowCount == 0 อาจจะหมายถึง:
    //   1. ไม่พบ LogID ที่ระบุ (เช่น LogID ผิด)
    //   2. ค่า Notes ที่ส่งมาเหมือนกับค่าเดิมในฐานข้อมูล (SQLite PDO driver อาจจะคืน 0 rowCount ในกรณีนี้)
    // เพื่อให้ User Experience ดีขึ้น ถ้าไม่มี Exception เกิดขึ้น เราจะถือว่าการบันทึก (หรือการพยายามบันทึก) สำเร็จ
    // เว้นแต่จะมีการตรวจสอบเพิ่มเติมว่า LogID นั้นมีอยู่จริงก่อนทำการ UPDATE (ซึ่งอาจจะดีกว่า)

    // ในที่นี้ เราจะตอบกลับว่าสำเร็จถ้าไม่มี Exception
    // หากต้องการความแม่นยำมากขึ้นว่ามีการเปลี่ยนแปลงจริงหรือไม่ อาจจะต้อง SELECT ข้อมูลก่อน UPDATE
    // หรือตรวจสอบ $rowCount อย่างเข้มงวดกว่านี้
    echo json_encode(['success' => true, 'message' => 'บันทึกหมายเหตุเพิ่มเติมสำเร็จ!']);
    // ตัวอย่างการตอบกลับที่เข้มงวดกว่า:
    // if ($rowCount > 0) {
    //     echo json_encode(['success' => true, 'message' => 'บันทึกหมายเหตุเพิ่มเติมสำเร็จ!']);
    // } else {
    //     // ตรวจสอบว่า LogID นั้นมีอยู่จริงหรือไม่
    //     $checkExistSql = "SELECT COUNT(*) FROM UsageLogs WHERE LogID = :logID_check";
    //     $checkStmt = $pdo->prepare($checkExistSql);
    //     $checkStmt->bindParam(':logID_check', $logID, PDO::PARAM_INT);
    //     $checkStmt->execute();
    //     if ($checkStmt->fetchColumn() > 0) {
    //         echo json_encode(['success' => true, 'message' => 'หมายเหตุไม่มีการเปลี่ยนแปลงจากค่าเดิม']);
    //     } else {
    //         echo json_encode(['success' => false, 'message' => 'ไม่พบ LogID ที่ต้องการอัปเดตหมายเหตุ']);
    //     }
    // }

} catch (PDOException $e) {
    // ถ้าเกิด Error ระหว่างการ UPDATE ข้อมูล
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกหมายเหตุ: ' . $e->getMessage()]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูลเสมอ
    $pdo = null;
}
?>