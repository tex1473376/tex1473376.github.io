<?php
// --- 0. เริ่มต้น Session ---
// session_start() ต้องถูกเรียกก่อนที่จะมีการใช้งานตัวแปร $_SESSION ใดๆ
session_start();

// --- 1. ตรวจสอบว่าเป็น POST Request หรือไม่ ---
// สคริปต์นี้ควรจะทำงานเฉพาะเมื่อมีการส่งข้อมูลมาจากฟอร์ม Login ด้วยเมธอด POST เท่านั้น
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 2. ตรวจสอบว่า Username และ Password ถูกส่งมาและไม่ว่างเปล่า ---
    // ใช้ trim() เพื่อลบ space หน้า-หลัง ที่อาจจะติดมาโดยไม่ตั้งใจ
    if (empty(trim($_POST["username"])) || empty(trim($_POST["password"]))) {
        // ถ้าข้อมูลไม่ครบถ้วน ให้ redirect กลับไปหน้า login พร้อม error code 3
        header("location: login.php?error=3"); // Error 3: กรอกข้อมูลไม่ครบ
        exit; // หยุดการทำงานของ script ทันที
    }

    // --- 3. รับค่า Username และ Password จากฟอร์ม ---
    $username_from_post = trim($_POST["username"]);
    $password_from_post = trim($_POST["password"]);

    // --- 4. ส่วนเชื่อมต่อฐานข้อมูล SQLite ---
    $db_file = __DIR__ . '/usernet_logger.sqlite'; // Path ไปยังไฟล์ฐานข้อมูล (อยู่ในโฟลเดอร์เดียวกับ script นี้)
    $pdo = null; // กำหนดตัวแปร PDO เริ่มต้น

    try {
        $pdo = new PDO('sqlite:' . $db_file); // สร้าง PDO object สำหรับเชื่อมต่อ
        // ตั้งค่า Attribute ของ PDO:
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);         // ให้โยน Exception เมื่อเกิด SQL error
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);    // ให้ดึงข้อมูลเป็น Associative Array
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);                              // กำหนด Timeout 5 วินาที ถ้าฐานข้อมูลถูก lock
    } catch (PDOException $e) {
        // ถ้าการเชื่อมต่อล้มเหลว (ควร Log error จริงจังใน Production)
        // error_log("Database Connection Error in login_process: " . $e->getMessage());
        header("location: login.php?error=2"); // Error 2: ข้อผิดพลาดฐานข้อมูล
        exit; // หยุดการทำงาน
    }

    // --- 5. ตรวจสอบ Username และ Password กับฐานข้อมูล ---
    if ($pdo) { // ตรวจสอบว่าการเชื่อมต่อ PDO สำเร็จหรือไม่
        try {
            // เตรียม SQL query เพื่อค้นหาผู้ใช้จาก Username
            // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
            $sql = "SELECT AdminID, Username, PasswordHash, FullName FROM Admins WHERE Username = :username_param";
            $stmt = $pdo->prepare($sql);
            
            // Bind ค่า Username ที่รับมาจากฟอร์มเข้ากับ placeholder ใน SQL query
            $stmt->bindParam(':username_param', $username_from_post, PDO::PARAM_STR);
            
            $stmt->execute(); // สั่งให้ SQL query ทำงาน

            $admin = $stmt->fetch(PDO::FETCH_ASSOC); // ดึงข้อมูลผู้ใช้ (ถ้ามี) ออกมา 1 แถว

            if ($admin) { // ถ้าพบผู้ใช้ที่มี Username นี้ในฐานข้อมูล
                // ตรวจสอบรหัสผ่านที่ผู้ใช้กรอก กับ PasswordHash ที่เก็บไว้ในฐานข้อมูล
                if (password_verify($password_from_post, $admin['PasswordHash'])) {
                    // รหัสผ่านถูกต้อง!
                    
                    session_regenerate_id(true); // สร้าง Session ID ใหม่ (เพื่อเพิ่มความปลอดภัย ป้องกัน Session Fixation)

                    // ตั้งค่า Session Variables เพื่อระบุว่าผู้ใช้ Login สำเร็จแล้ว และเก็บข้อมูลที่จำเป็น
                    $_SESSION["admin_loggedin"] = true;
                    $_SESSION["admin_id"] = $admin['AdminID'];
                    $_SESSION["admin_username"] = $admin['Username']; // ใช้ Username จากฐานข้อมูล เพื่อความถูกต้อง
                    $_SESSION["admin_fullname"] = $admin['FullName'];

                    // (เป็นทางเลือก) อาจจะมีการอัปเดต 'LastLoginDate' ในตาราง Admins ที่นี่

                    // Redirect ผู้ใช้ไปยังหน้าหลักของ Admin (index.php)
                    header("location: index.php");
                    exit;
                } else {
                    // รหัสผ่านไม่ถูกต้อง
                    header("location: login.php?error=1"); // Error 1: ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง
                    exit;
                }
            } else {
                // ไม่พบ Username นี้ในฐานข้อมูล
                header("location: login.php?error=1"); // Error 1: ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง
                exit;
            }
        } catch (PDOException $e) {
            // ถ้าเกิด Error ระหว่างการ Query หรือประมวลผลข้อมูล (ควร Log error จริงจังใน Production)
            // error_log("Login Query/Processing Error: " . $e->getMessage());
            header("location: login.php?error=2"); // Error 2: ข้อผิดพลาดในการประมวลผล (อาจจะเกี่ยวกับฐานข้อมูล)
            exit;
        } finally {
            // ปิดการเชื่อมต่อฐานข้อมูลเสมอ ไม่ว่าจะสำเร็จหรือเกิด Error
            $pdo = null;
        }
    } else {
        // กรณีนี้ไม่ควรเกิดขึ้นถ้า try-catch ของ PDO connection ด้านบนทำงานถูกต้อง
        // และมีการ exit; ไปแล้วถ้าเชื่อมต่อไม่ได้
        // error_log("PDO object was null without throwing an exception in login_process (should not happen).");
        header("location: login.php?error=2"); // ข้อผิดพลาดฐานข้อมูล (ไม่สามารถสร้าง PDO object ได้)
        exit;
    }
} else {
    // ถ้าไม่ได้เข้ามาด้วยวิธี POST (เช่น พิมพ์ URL ของ login_process.php โดยตรง)
    // ให้ redirect กลับไปหน้า login
    header("location: login.php");
    exit;
}
?>