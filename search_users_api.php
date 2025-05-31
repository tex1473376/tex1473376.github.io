<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401); // 401 Unauthorized
    // ส่ง JSON กลับไปพร้อม data เป็น array ว่าง เพื่อให้ client ไม่ error ตอน parse
    echo json_encode(['success' => false, 'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อน', 'data' => []]);
    exit;
}

// --- 2. ตั้งค่า Header สำหรับ Response ---
// (บรรทัดนี้อาจจะไม่จำเป็นถ้าโค้ดป้องกันด้านบนได้ตั้งไว้แล้ว แต่ใส่ไว้เพื่อความชัดเจนก็ได้)
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
    // ถ้าการเชื่อมต่อล้มเหลว ส่ง JSON error กลับไป (พร้อม data เป็น array ว่าง)
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage(), 'data' => []]);
    exit();
}

// --- 4. รับคำค้นหา (Search Term) จาก GET Request ---
// ใช้ trim() เพื่อลบ space หน้า-หลัง ที่อาจจะติดมาโดยไม่ตั้งใจ
// ถ้าไม่มี 'term' ส่งมา หรือเป็นค่าว่าง ให้ $searchTerm เป็น string ว่าง
$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

// --- 5. เตรียมตัวแปรสำหรับเก็บผลลัพธ์ ---
$users = []; // Array สำหรับเก็บข้อมูลผู้ใช้ที่ค้นหาเจอ

// --- 6. ดำเนินการค้นหาถ้า searchTerm ไม่ใช่ค่าว่าง ---
if (!empty($searchTerm)) {
    try {
        // SQL Query สำหรับค้นหาผู้ใช้:
        // - เลือกข้อมูลที่ต้องการจากตาราง Users
        // - ค้นหา (WHERE) โดยใช้ LIKE กับ placeholder :searchTerm_like
        //   ในคอลัมน์ NationalID, FirstName, หรือ LastName
        // - LIKE '%value%' หมายถึงค้นหาคำที่ "มี value อยู่ส่วนใดส่วนหนึ่ง"
        // - ORDER BY FirstName, LastName เพื่อให้ผลการค้นหาเรียงตามชื่อ (ถ้าต้องการ)
        $sql = "SELECT UserID, NationalID, Prefix, FirstName, LastName, Age
                FROM Users
                WHERE NationalID LIKE :searchTerm_like
                   OR FirstName LIKE :searchTerm_like
                   OR LastName LIKE :searchTerm_like
                ORDER BY FirstName ASC, LastName ASC"; // เรียงตามชื่อจริงและนามสกุลจากน้อยไปมาก

        $stmt = $pdo->prepare($sql); // เตรียม SQL query

        // สร้าง search pattern สำหรับ LIKE (เช่น '%คำค้น%')
        $searchPattern = '%' . $searchTerm . '%';
        
        // Bind ค่า searchPattern เข้ากับ placeholder :searchTerm_like
        // (การส่ง array เข้า execute() เป็นวิธีหนึ่งในการ bind โดยอัตโนมัติ)
        $stmt->execute([':searchTerm_like' => $searchPattern]);
        
        $users = $stmt->fetchAll(); // ดึงข้อมูลทั้งหมดที่ค้นหาเจอ

        // ส่งผลลัพธ์กลับไป (แม้ว่าจะไม่พบข้อมูล, $users จะเป็น array ว่าง)
        echo json_encode(['success' => true, 'data' => $users]);

    } catch (PDOException $e) {
        // ถ้าเกิด Error ระหว่างการ Query หรือ Fetch ข้อมูล
        // ส่ง JSON error กลับไปพร้อม data เป็น array ว่าง
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการค้นหาข้อมูล: ' . $e->getMessage(), 'data' => []]);
        exit(); // หยุดการทำงานถ้าเกิด error ตอน query
    }
} else {
    // ถ้า searchTerm ที่ส่งมาเป็นค่าว่าง (เช่น ผู้ใช้ยังไม่ได้พิมพ์อะไร หรือลบคำค้นหาหมด)
    // ให้ส่ง array ว่างกลับไป (JavaScript ฝั่ง Client จะจัดการแสดงผลเอง)
    echo json_encode(['success' => true, 'data' => []]);
}

// --- 7. ปิดการเชื่อมต่อฐานข้อมูล ---
// (เป็น good practice แม้ว่า PHP มักจะปิดให้เมื่อ script จบ)
$pdo = null;
?>