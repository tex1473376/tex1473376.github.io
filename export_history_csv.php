<?php
// เริ่ม session เพื่อตรวจสอบการ login ของ admin
session_start();

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Authentication Check) ---
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    http_response_code(401); // Unauthorized
    // สำหรับการดาวน์โหลดไฟล์, การแสดงข้อความ error แบบนี้อาจจะดีกว่า redirect
    die("Error 401: Unauthorized access. Please log in to download this file.");
}

// --- 2. ส่วนเชื่อมต่อฐานข้อมูล SQLite ---
$db_file = __DIR__ . '/usernet_logger.sqlite'; // ตรวจสอบ Path ให้ถูกต้อง
$pdo = null;
try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // รอ 5 วินาทีถ้า database locked
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("CSV Export - DB Connection failed: " . $e->getMessage()); // Log error จริง
    die("Database connection failed. Please contact administrator."); // แสดงข้อความทั่วไป
}

// --- 3. รับค่า Filter วันที่จาก GET Request ---
$startDate = isset($_GET['startDate']) && !empty($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) && !empty($_GET['endDate']) ? $_GET['endDate'] : null;

$params = []; // สำหรับเก็บ parameters ของ prepared statement
$filename_parts = ["USO_NET_Automate_Data"]; // ชื่อไฟล์เริ่มต้น

// --- 4. ดึงข้อมูลประวัติการใช้งานจากฐานข้อมูล ---
try {
    // SQL query หลัก - เลือก field ที่จำเป็นสำหรับการสร้าง CSV ตามที่คุณต้องการ
    $sql = "SELECT
                u.NationalID,
                u.Age,
                u.Prefix,
                u.FirstName,
                u.LastName,
                ul.ServiceDate,     /* รูปแบบ YYYY-MM-DD */
                ul.LoginTime,       /* รูปแบบ HH:MM:SS หรือ YYYY-MM-DD HH:MM:SS */
                ul.LogoutTime,      /* รูปแบบ HH:MM:SS หรือ YYYY-MM-DD HH:MM:SS หรือ NULL */
                ul.ComputerNumber,
                ul.HeadphoneID,
                ul.UsageDetails
            FROM
                UsageLogs ul
            JOIN
                Users u ON ul.UserID = u.UserID";

    $conditions = [];
    if ($startDate) {
        $conditions[] = "ul.ServiceDate >= :startDate_param";
        $params[':startDate_param'] = $startDate;
        $filename_parts[] = "from_" . str_replace('-', '', $startDate);
    }
    if ($endDate) {
        $conditions[] = "ul.ServiceDate <= :endDate_param";
        $params[':endDate_param'] = $endDate;
        if ($startDate) {
             $filename_parts[] = "to_" . str_replace('-', '', $endDate);
        } else {
             $filename_parts[] = "until_" . str_replace('-', '', $endDate);
        }
    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    // เรียงตามข้อมูลเก่าสุดขึ้นก่อนในไฟล์ CSV (ASC)
    $sql .= " ORDER BY ul.ServiceDate ASC, ul.LoginTime ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // --- 5. ตั้งค่า HTTP Headers สำหรับการดาวน์โหลดไฟล์ CSV ---
    if (count($filename_parts) == 1) { // ถ้าไม่มี filter วันที่เลย (มีแค่ชื่อเริ่มต้น)
        $filename_parts[] = "all_" . date("Ymd");
    }
    $filename = implode("_", $filename_parts) . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // --- 6. สร้างและ Output เนื้อหา CSV ---
    $output = fopen('php://output', 'w'); // เปิด PHP output stream สำหรับเขียน CSV

    // 6.1 เขียน Byte Order Mark (BOM) สำหรับ UTF-8 (เพื่อให้ Excel เปิดไฟล์ไทยได้ถูกต้องเสมอ)
    fwrite($output, "\xEF\xBB\xBF"); 

    // 6.2 เขียนแถว Header (ชื่อคอลัมน์) - ตาม Format ที่คุณต้องการ
    $csv_headers = [
        'เลขบัตรประชาชน', 'อายุ', 'นักเรียน/ประชาชน', 'คำนำหน้า', 'ชื่อ นามสกุล',
        'วันที่เข้า', 'เวลาเข้า', // (dd/MM/yyyy), (HH:MM)
        'วันที่ออก', 'เวลาออก',   // (dd/MM/yyyy), (HH:MM)
        'เลขคอม', 'เลขหูฟัง', 'รายละเอียด'
    ];
    fputcsv($output, $csv_headers);

    // 6.3 เขียนข้อมูลแต่ละแถว - จัดรูปแบบตามที่คุณต้องการ
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            // คำนวณประเภทผู้ใช้
            $userType = 'ไม่ระบุ'; // ค่าเริ่มต้น
            if (isset($log['Age']) && is_numeric($log['Age'])) {
                $age = (int)$log['Age'];
                if ($age >= 0 && $age <= 14) {
                    $userType = 'นักเรียน';
                } elseif ($age >= 15) {
                    $userType = 'ประชาชน';
                }
            }

            // จัดรูปแบบวันที่ ServiceDate (YYYY-MM-DD) ให้เป็น dd/MM/yyyy
            $serviceDateFormatted = '';
            if (!empty($log['ServiceDate'])) {
                try {
                    $dateObj = new DateTime($log['ServiceDate']);
                    $serviceDateFormatted = $dateObj->format('d/m/Y');
                } catch (Exception $ex) {
                    $serviceDateFormatted = $log['ServiceDate']; // ถ้า format ผิดพลาด, ใช้ค่าเดิม
                }
            }

            // จัดรูปแบบเวลาเข้า LoginTime ให้เป็น HH:MM
            $loginTimeFormatted = '';
            if (!empty($log['LoginTime'])) {
                try {
                    // ลองพิจารณาว่า LoginTime เก็บแค่ HH:MM:SS หรือมีวันที่ด้วย
                    // ถ้ามีวันที่ด้วย การใช้ DateTime จะปลอดภัยกว่า
                    if (strpos($log['LoginTime'], ':') !== false && strlen($log['LoginTime']) >= 5) {
                        $timeObj = new DateTime($log['LoginTime']); // อาจจะ error ถ้า LoginTime ไม่มีวันที่แต่มีแค่เวลา
                                                                    // ถ้า LoginTime เป็น YYYY-MM-DD HH:MM:SS จะถูกต้อง
                                                                    // ถ้าเป็นแค่ HH:MM:SS อาจจะต้องสร้าง DateTime จาก ServiceDate + LoginTime
                        $loginTimeFormatted = $timeObj->format('H:i');
                    } else {
                        $loginTimeFormatted = $log['LoginTime']; // ถ้า format ไม่รู้จัก
                    }
                } catch (Exception $ex) {
                    // ถ้า DateTime error, ลอง substr หรือปล่อยเป็นค่าเดิม
                    $loginTimeFormatted = (strlen($log['LoginTime']) >= 5) ? substr($log['LoginTime'], 0, 5) : $log['LoginTime'];
                }
            }

            // วันที่ออก และ เวลาออก
            $logoutDateFormatted = '';
            $logoutTimeFormatted = '';
            if (!empty($log['LogoutTime'])) {
                // สมมติว่า Logout ในวันเดียวกับ ServiceDate สำหรับวันที่ออก
                // หรือถ้า LogoutTime ของคุณเก็บวันที่ด้วย ก็ต้องดึงวันที่จาก LogoutTime
                // ในที่นี้จะใช้วันที่ของ ServiceDate เป็นหลักก่อน
                $logoutDateFormatted = $serviceDateFormatted; 
                
                try {
                    if (strpos($log['LogoutTime'], ':') !== false && strlen($log['LogoutTime']) >= 5) {
                        $timeObj = new DateTime($log['LogoutTime']);
                        $logoutTimeFormatted = $timeObj->format('H:i');
                    } else {
                         $logoutTimeFormatted = $log['LogoutTime'];
                    }
                } catch (Exception $ex) {
                    $logoutTimeFormatted = (strlen($log['LogoutTime']) >= 5) ? substr($log['LogoutTime'], 0, 5) : $log['LogoutTime'];
                }
            }

            $csv_row = [
                $log['NationalID'] ?? '',
                $log['Age'] ?? '',
                $userType,
                $log['Prefix'] ?? '',
                trim(($log['FirstName'] ?? '') . ' ' . ($log['LastName'] ?? '')),
                $serviceDateFormatted,
                $loginTimeFormatted,
                $logoutDateFormatted, // วันที่ออก (อาจจะยังเป็นวันที่เข้า ถ้าต้องการวันที่ออกจริงๆ ต้องปรับ Logic)
                $logoutTimeFormatted, // เวลาออก
                $log['ComputerNumber'] ?? '',
                ($log['HeadphoneID'] && $log['HeadphoneID'] !== 'NO_HEADPHONE') ? ($log['HeadphoneID'] ?? '') : '',
                $log['UsageDetails'] ?? ''
            ];
            fputcsv($output, $csv_row);
        }
    }
    
    fclose($output);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    error_log("CSV Export Database Error: " . $e->getMessage());
    // ไม่ควร echo HTML ในไฟล์ CSV แต่ถ้าจำเป็นจริงๆ ให้แสดง error message สั้นๆ
    die("เกิดข้อผิดพลาดในการสร้างไฟล์ CSV: " . htmlspecialchars($e->getMessage()));
} finally {
    $pdo = null; // ปิดการเชื่อมต่อ
}
?>