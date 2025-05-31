<?php
// --- 0. เริ่มต้น Session ---
// session_start() เป็นสิ่งแรกที่ต้องเรียกใช้เสมอเมื่อจะทำงานกับ Session
// เพื่อให้สามารถเข้าถึงตัวแปร $_SESSION และจัดการ Session ปัจจุบันได้
session_start();

// --- 1. ล้างค่าตัวแปรทั้งหมดใน Session ปัจจุบัน ---
// การกำหนด $_SESSION ให้เป็น array ว่าง เป็นการลบข้อมูลทั้งหมดที่เก็บไว้ใน Session ของผู้ใช้นี้
// เช่น $_SESSION['admin_loggedin'], $_SESSION['admin_username'] จะถูกลบออกไป
$_SESSION = array();

// --- 2. ทำลาย Session Cookie (ถ้ามีการใช้งาน) ---
// โดยปกติ PHP session จะใช้ cookie ในการติดตาม session ID ของผู้ใช้
// ส่วนนี้จะพยายามลบ session cookie นั้นออกจากเบราว์เซอร์ของผู้ใช้
// เพื่อให้แน่ใจว่า session ไม่ได้ถูกจดจำไว้ที่ฝั่ง client อีกต่อไป
if (ini_get("session.use_cookies")) { // ตรวจสอบว่าระบบ session กำลังใช้ cookie หรือไม่
    $params = session_get_cookie_params(); // ดึงค่า parameters ของ session cookie ปัจจุบัน
    // สั่งให้เบราว์เซอร์ลบ cookie โดยการตั้งเวลาหมดอายุในอดีต (time() - 42000 วินาที)
    // และใช้ parameters เดิมของ cookie (path, domain, secure, httponly) เพื่อให้แน่ใจว่าลบถูกตัว
    setcookie(
        session_name(), // ชื่อของ session cookie (ปกติคือ PHPSESSID)
        '',             // ค่าของ cookie (ตั้งเป็นค่าว่าง)
        time() - 42000, // เวลาหมดอายุ (ในอดีต)
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// --- 3. ทำลาย Session บน Server อย่างสมบูรณ์ ---
// คำสั่งนี้จะลบข้อมูล session ทั้งหมดที่เก็บไว้บน Server ที่เกี่ยวข้องกับ session ID ปัจจุบัน
// และทำให้ session ID นั้นไม่สามารถใช้งานได้อีกต่อไป
session_destroy();

// --- 4. Redirect ผู้ใช้กลับไปยังหน้า Login ---
// หลังจากทำลาย session ทั้งหมดแล้ว ส่งผู้ใช้กลับไปที่หน้า login.php
// พร้อมกับ parameter 'logout=1' เพื่อให้หน้า login.php สามารถแสดงข้อความว่า "ออกจากระบบสำเร็จแล้ว"
header("location: login.php?logout=1");
exit; // หยุดการทำงานของ script ทันทีหลังจากสั่ง redirect เพื่อความปลอดภัย
?>