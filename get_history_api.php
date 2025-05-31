<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
// ถ้า admin ยังไม่ได้ login หรือ session ไม่ถูกต้อง ให้ส่ง error และหยุดการทำงาน
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อน', 'data' => []]);
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
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage(), 'data' => []]);
    exit();
}

// --- 4. รับค่า Filter วันที่จาก GET Request (ถ้ามี) ---
// ถ้ามีการส่ง startDate มาใน URL และไม่เป็นค่าว่าง ก็ใช้ค่านั้น, ถ้าไม่มีก็ให้เป็น null
$startDate = isset($_GET['startDate']) && !empty($_GET['startDate']) ? $_GET['startDate'] : null;
// ถ้ามีการส่ง endDate มาใน URL และไม่เป็นค่าว่าง ก็ใช้ค่านั้น, ถ้าไม่มีก็ให้เป็น null
$endDate = isset($_GET['endDate']) && !empty($_GET['endDate']) ? $_GET['endDate'] : null;

$params = []; // Array สำหรับเก็บ parameters ที่จะใช้ใน Prepared Statement (สำหรับ bind ค่า)

// --- 5. ดึงข้อมูลประวัติการใช้งานจากฐานข้อมูล ---
try {
    // SQL query หลักสำหรับดึงข้อมูลที่จำเป็นทั้งหมด
    // - ul คือ alias ของตาราง UsageLogs
    // - u คือ alias ของตาราง Users
    $sql = "SELECT
                ul.LogID,
                ul.UserID,
                u.Prefix,
                u.FirstName,
                u.LastName,
                u.Age,
                ul.ServiceDate,
                ul.LoginTime,
                ul.LogoutTime,
                ul.UsageDetails,
                ul.ComputerNumber,
                ul.HeadphoneID,
                ul.HeadphoneReturned,
                ul.Notes
            FROM
                UsageLogs ul
            JOIN
                Users u ON ul.UserID = u.UserID"; // JOIN ตาราง Users เพื่อดึงข้อมูลผู้ใช้

    $conditions = []; // Array สำหรับเก็บเงื่อนไข WHERE (ถ้ามีการกรองตามวันที่)

    // ถ้ามีการระบุ startDate ให้เพิ่มเงื่อนไข ServiceDate >= startDate
    if ($startDate) {
        $conditions[] = "ul.ServiceDate >= :startDate_param"; // ใช้ placeholder
        $params[':startDate_param'] = $startDate; // เพิ่มค่าเข้า $params array
    }
    // ถ้ามีการระบุ endDate ให้เพิ่มเงื่อนไข ServiceDate <= endDate
    if ($endDate) {
        $conditions[] = "ul.ServiceDate <= :endDate_param"; // ใช้ placeholder
        $params[':endDate_param'] = $endDate;   // เพิ่มค่าเข้า $params array
    }

    // ถ้ามีเงื่อนไข (มีการกรองตามวันที่) ให้เพิ่มส่วน WHERE เข้าไปใน SQL query
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions); // เชื่อมเงื่อนไขด้วย AND
    }

    // เรียงลำดับผลลัพธ์:
    // - เรียงตาม ServiceDate จากเก่าไปใหม่ (ASC) ก่อน
    // - จากนั้นภายในวันเดียวกัน ให้เรียงตาม LoginTime จากเก่าไปใหม่ (ASC)
    // - การเรียงแบบนี้สำคัญเพื่อให้การคำนวณ "ลำดับที่ในแต่ละวัน" (dailySequence) ถูกต้อง
    $sql .= " ORDER BY ul.ServiceDate ASC, ul.LoginTime ASC";

    $stmt = $pdo->prepare($sql); // เตรียม SQL query
    $stmt->execute($params);     // สั่งให้ query ทำงานพร้อมกับ parameters (ถ้ามี)
    $rawLogs = $stmt->fetchAll(); // ดึงข้อมูลทั้งหมดที่ได้จาก query

    // --- 6. คำนวณ "ลำดับที่ของการใช้งานในแต่ละวัน" (Daily Sequence) ---
    $finalLogsWithSequence = []; // Array สำหรับเก็บผลลัพธ์สุดท้ายพร้อม dailySequence
    $currentDate = null;       // ตัวแปรเก็บวันที่ปัจจุบันที่กำลังประมวลผล
    $sequence = 0;             // ตัวแปรนับลำดับที่ในแต่ละวัน

    foreach ($rawLogs as $log) {
        if ($log['ServiceDate'] !== $currentDate) { // ถ้าเป็นวันใหม่
            $currentDate = $log['ServiceDate'];      // อัปเดตวันที่ปัจจุบัน
            $sequence = 1;                          // เริ่มนับลำดับที่ 1 ใหม่
        } else { // ถ้ายังเป็นวันเดิม
            $sequence++;                            // เพิ่มลำดับต่อไป
        }
        $log['dailySequence'] = $sequence;        // เพิ่ม key 'dailySequence' เข้าไปในข้อมูล log
        $finalLogsWithSequence[] = $log;          // เพิ่ม log ที่มี dailySequence แล้วเข้าไปใน array ผลลัพธ์
    }

    // --- 7. (เป็นทางเลือก) เรียงข้อมูลครั้งสุดท้ายสำหรับการแสดงผล ---
    // ถ้าต้องการให้หน้าเว็บแสดงรายการล่าสุด (วันใหม่สุด) ขึ้นก่อน
    // แต่ dailySequence ยังคงนับจากรายการเก่าสุดของวันนั้นๆ เป็น 1
    // เราจะเรียง $finalLogsWithSequence อีกครั้ง
    usort($finalLogsWithSequence, function($a, $b) {
        // เรียงตาม ServiceDate จากใหม่ไปเก่า (DESC) ก่อน
        if ($a['ServiceDate'] != $b['ServiceDate']) {
            return strcmp($b['ServiceDate'], $a['ServiceDate']);
        }
        // ถ้า ServiceDate เหมือนกัน ให้เรียงตาม dailySequence จากน้อยไปมาก (ASC)
        // เพื่อให้ลำดับที่ 1 ของวันนั้นแสดงก่อนลำดับที่ 2, 3...
        return $a['dailySequence'] - $b['dailySequence'];
    });
    // ถ้าคุณต้องการให้แสดงจาก "เก่าสุดไปใหม่สุด" ทั้งหมด ก็ไม่จำเป็นต้องมี usort() ส่วนนี้
    // เพราะข้อมูลจาก $rawLogs ถูกเรียงแบบ ASC มาแล้ว และการวนลูปคำนวณ sequence ไม่ได้เปลี่ยนลำดับหลักนั้น

    // ส่งผลลัพธ์กลับไปให้ Client เป็น JSON
    echo json_encode(['success' => true, 'data' => $finalLogsWithSequence]);

} catch (PDOException $e) {
    // ถ้าเกิด Error ระหว่างการ Query หรือประมวลผลข้อมูล
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลประวัติ: ' . $e->getMessage(), 'data' => []]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูล
    $pdo = null;
}
?>