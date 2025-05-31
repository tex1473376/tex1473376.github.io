<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
// ถ้า admin ยังไม่ได้ login หรือ session ไม่ถูกต้อง ให้ส่ง error และหยุดการทำงาน
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Content-Type: application/json'); // กำหนด header เป็น JSON
    http_response_code(401); // 401 Unauthorized - ไม่มีสิทธิ์เข้าถึง
    echo json_encode(['success' => false, 'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อน']);
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
    echo json_encode(['success' => false, 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()]);
    exit(); // หยุดการทำงาน
}

// --- 4. รับและประมวลผลข้อมูล Input ---
$json_data = file_get_contents('php://input'); // อ่านข้อมูล JSON ดิบที่ส่งมาจาก Client (JavaScript)
$data = json_decode($json_data, true);         // แปลง JSON string เป็น PHP Associative Array

$response = ['success' => false, 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง หรือไม่ครบถ้วน']; // Response เริ่มต้น

// --- 5. ตรวจสอบความสมบูรณ์ของข้อมูล (Data Validation) ---
if (
    !empty($data['nationalID']) &&      // เลขบัตรต้องไม่ว่าง
    !empty($data['firstName']) &&       // ชื่อจริงต้องไม่ว่าง
    !empty($data['lastName']) &&        // นามสกุลต้องไม่ว่าง
    isset($data['prefix']) &&           // คำนำหน้าชื่อต้องมี (แม้จะเป็นค่าว่างถ้าเลือก "อื่นๆ")
    isset($data['age']) && is_numeric($data['age']) // อายุต้องมีและเป็นตัวเลข
) {
    // ทำความสะอาดข้อมูล Input (ลบ space หน้า-หลัง)
    $nationalID = trim($data['nationalID']);
    $prefix = trim($data['prefix']);
    $firstName = trim($data['firstName']);
    $lastName = trim($data['lastName']);
    $age = intval($data['age']); // แปลงอายุเป็น Integer

    // 5.1. ตรวจสอบรูปแบบของ National ID
    if (strlen($nationalID) !== 13 || !ctype_digit($nationalID)) {
        $response['message'] = 'เลขบัตรประจำตัวประชาชนต้องเป็นตัวเลข 13 หลัก';
        echo json_encode($response);
        exit();
    }

    // 5.2. ตรวจสอบช่วงของอายุ
    if ($age < 0 || $age > 120) {
        $response['message'] = 'อายุไม่ถูกต้อง (ต้องอยู่ระหว่าง 0 - 120 ปี)';
        echo json_encode($response);
        exit();
    }

    // --- 5.3. ตรวจสอบว่า NationalID นี้มีอยู่ในระบบแล้วหรือยัง (ป้องกันข้อมูลซ้ำ) ---
    try {
        $check_sql = "SELECT UserID FROM Users WHERE NationalID = :nationalID_param";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':nationalID_param', $nationalID, PDO::PARAM_STR);
        $check_stmt->execute();
        $existing_user = $check_stmt->fetch(); // ดึงข้อมูล (ถ้ามี)

        if ($existing_user) { // ถ้าพบว่ามีผู้ใช้นี้อยู่แล้ว
            $response['message'] = "เลขบัตรประจำตัวประชาชน \"{$nationalID}\" นี้มีอยู่ในระบบแล้ว";
            echo json_encode($response);
            exit();
        }
    } catch (PDOException $e) {
        $response['message'] = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลผู้ใช้: ' . $e->getMessage();
        echo json_encode($response);
        exit();
    }
    // --- จบส่วนการตรวจสอบ NationalID ซ้ำ ---

    // --- 6. เตรียมและ Execute คำสั่ง SQL INSERT (ถ้าข้อมูลผ่านการตรวจสอบทั้งหมด) ---
    $sql = "INSERT INTO Users (NationalID, Prefix, FirstName, LastName, Age)
            VALUES (:nationalID_val, :prefix_val, :firstName_val, :lastName_val, :age_val)";

    try {
        $stmt = $pdo->prepare($sql);
        // Bind ค่าเข้ากับ Parameters ใน SQL Query เพื่อป้องกัน SQL Injection
        $stmt->bindParam(':nationalID_val', $nationalID, PDO::PARAM_STR);
        $stmt->bindParam(':prefix_val', $prefix, PDO::PARAM_STR);
        $stmt->bindParam(':firstName_val', $firstName, PDO::PARAM_STR);
        $stmt->bindParam(':lastName_val', $lastName, PDO::PARAM_STR);
        $stmt->bindParam(':age_val', $age, PDO::PARAM_INT);

        $stmt->execute(); // สั่งให้ SQL ทำงาน
        $newUserId = $pdo->lastInsertId(); // ดึง UserID ของแถวที่เพิ่งเพิ่มเข้าไปล่าสุด

        // สร้าง Response สำหรับการบันทึกสำเร็จ
        $response = [
            'success' => true,
            'message' => "เพิ่มผู้ใช้ใหม่ \"{$prefix}{$firstName} {$lastName}\" สำเร็จ! (UserID: {$newUserId})",
            'newUser' => [ // ส่งข้อมูลผู้ใช้ใหม่กลับไปด้วย (เผื่อ Client ต้องการใช้)
                'UserID' => $newUserId,
                'NationalID' => $nationalID,
                'Prefix' => $prefix,
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Age' => $age
            ]
        ];

    } catch (PDOException $e) {
        $response['message'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูลผู้ใช้ใหม่: ' . $e->getMessage();
        // ตรวจสอบเพิ่มเติมสำหรับกรณี UNIQUE constraint violation (ถ้า NationalID ซ้ำ แต่การตรวจสอบข้างบนอาจจะพลาดไป)
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 19) ) { // SQLite error code for UNIQUE constraint is 19
             $response['message'] = "เลขบัตรประจำตัวประชาชน \"{$nationalID}\" นี้อาจมีอยู่ในระบบแล้ว (DB constraint)";
        }
    }
} else {
    // ถ้าข้อมูลที่จำเป็น (เช่น nationalID, firstName, lastName, age) ไม่ได้ถูกส่งมาครบ
    $response['message'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
}

// --- 7. ส่งผลลัพธ์กลับไปให้ Client ---
echo json_encode($response);
$pdo = null; // ปิดการเชื่อมต่อฐานข้อมูล
?>