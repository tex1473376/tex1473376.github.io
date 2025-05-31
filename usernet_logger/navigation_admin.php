<?php
// File: navigation_admin.php
// Purpose: Generates the admin navigation menu.
// This file is intended to be included in pages that require admin authentication.
// Assumes session_start() has already been called in the including file.

// --- 1. กำหนดข้อความที่จะแสดงสำหรับชื่อผู้ใช้ที่ Login อยู่ ---
$loggedInUserDisplay = 'ผู้ดูแลระบบ'; // ค่าเริ่มต้น ถ้าไม่พบชื่อใน session

// ตรวจสอบว่ามี 'admin_fullname' ใน session และไม่เป็นค่าว่าง (หลังจาก trim) หรือไม่
if (isset($_SESSION['admin_fullname']) && !empty(trim($_SESSION['admin_fullname']))) {
    $loggedInUserDisplay = htmlspecialchars(trim($_SESSION['admin_fullname'])); // ใช้ชื่อเต็ม ถ้ามี
} 
// ถ้าไม่มีชื่อเต็ม ให้ตรวจสอบ 'admin_username'
elseif (isset($_SESSION['admin_username'])) {
    $loggedInUserDisplay = htmlspecialchars($_SESSION['admin_username']); // ใช้ username ถ้ามี
}
// (ถ้าไม่มีทั้งคู่ ก็จะใช้ค่า default 'ผู้ดูแลระบบ' ที่ตั้งไว้ตอนแรก)

// --- 2. ดึงชื่อไฟล์ของหน้าปัจจุบัน เพื่อใช้ในการไฮไลท์ลิงก์ที่ Active ---
// basename($_SERVER['PHP_SELF']) จะคืนค่าชื่อไฟล์ของ script ที่กำลังรันอยู่ (เช่น 'index.php', 'history.php')
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="main-navigation">
    <div class="nav-placeholder-left">
        </div>

    <div class="nav-links-center">
        <a href="index.php" 
           id="nav-index" 
           class="<?php echo ($current_page == 'index.php') ? 'active-nav' : ''; // ถ้าหน้าปัจจุบันคือ index.php ให้เพิ่ม class 'active-nav' ?>">
           หน้าหลัก (บันทึก Log)
        </a>
        <a href="history.php" 
           id="nav-history" 
           class="<?php echo ($current_page == 'history.php') ? 'active-nav' : ''; // ถ้าหน้าปัจจุบันคือ history.php ให้เพิ่ม class 'active-nav' ?>">
           ดูประวัติการใช้งาน
        </a>
        </div>

    <div class="nav-user-info">
        <span>สวัสดี, <?php echo $loggedInUserDisplay; // แสดงชื่อผู้ใช้ที่ Login ?></span>
        <a href="logout.php" class="logout-link">ออกจากระบบ</a>
    </div>
</nav>