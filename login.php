<?php
// --- 0. เริ่มต้น Session ---
// session_start() ต้องถูกเรียกก่อนที่จะมีการใช้งานตัวแปร $_SESSION ใดๆ
// และควรจะอยู่บนสุดของไฟล์ PHP ก่อนที่จะมีการส่ง Output ใดๆ ออกไป (เช่น HTML)
session_start();

// --- 1. ตรวจสอบสถานะการ Login ---
// ถ้าผู้ใช้ (Admin) ได้ Login เข้าระบบอยู่แล้ว (มี session 'admin_loggedin' เป็น true)
// ให้ redirect (ส่งต่อไปยัง) หน้า index.php ทันที โดยไม่ต้องแสดงฟอร์ม Login อีก
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    header('Location: index.php'); // สั่งให้เบราว์เซอร์ไปที่ index.php
    exit; // หยุดการทำงานของ script นี้ทันทีหลังจากสั่ง redirect
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล - USO NET Logger</title>
    <link rel="stylesheet" type="text/css" href="main.css"> <style>
        /* CSS เฉพาะสำหรับหน้า Login นี้ (คุณสามารถย้ายไปรวมใน main.css ได้) */
        body {
            display: flex;
            justify-content: center; /* จัดเนื้อหาให้อยู่กึ่งกลางแนวนอน */
            align-items: center;    /* จัดเนื้อหาให้อยู่กึ่งกลางแนวตั้ง */
            min-height: 100vh;      /* ให้ body สูงเต็มหน้าจอเป็นอย่างน้อย */
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 35px 45px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px; /* ความกว้างสูงสุดของกล่อง Login */
            text-align: center;
        }
        .login-container h1 {
            color: #0056b3;
            margin-bottom: 30px;
            font-size: 2em;
        }
        .login-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            text-align: left; /* ให้ label ชิดซ้าย */
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 14px;
            margin-bottom: 22px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .login-container input[type="text"]:focus,
        .login-container input[type="password"]:focus {
             border-color: #80bdff;
             outline: 0;
             box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .login-container button[type="submit"] {
            width: 100%;
            padding: 14px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }
        .login-container button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message { /* Class สำหรับแสดงข้อความ (ทั้ง error และ success) */
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.95em;
        }
        .error-message { /* สไตล์สำหรับข้อความ Error */
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .success-message { /* สไตล์สำหรับข้อความ Success (เช่น "ออกจากระบบสำเร็จ") */
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>เข้าสู่ระบบผู้ดูแล</h1>
        <?php
        // --- 2. แสดงข้อความแจ้งเตือน (Error หรือ Success) ---
        // ตรวจสอบว่ามี GET parameter 'error' ส่งมาหรือไม่ (จาก login_process.php หรือจากหน้าอื่นที่ redirect มา)
        if (isset($_GET['error'])) {
            $errorMessage = ''; // กำหนดค่าเริ่มต้น
            // กำหนดข้อความ Error ตาม error code ที่ได้รับ
            if ($_GET['error'] == '1') {
                $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!';
            } elseif ($_GET['error'] == '2') {
                $errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
            } elseif ($_GET['error'] == '3') {
                $errorMessage = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } elseif ($_GET['error'] == '4') {
                $errorMessage = 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน'; // Error จากการพยายามเข้าหน้าที่ป้องกันไว้
            } else {
                $errorMessage = 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ';
            }
            // แสดง div ที่มีข้อความ error
            echo '<div class="message error-message">' . htmlspecialchars($errorMessage) . '</div>';
        }

        // ตรวจสอบว่ามี GET parameter 'logout' ส่งมาหรือไม่ (จาก logout.php)
        if (isset($_GET['logout']) && $_GET['logout'] == '1') {
            // แสดง div ที่มีข้อความ "ออกจากระบบสำเร็จแล้ว"
            echo '<div class="message success-message">ออกจากระบบสำเร็จแล้ว</div>';
        }
        ?>
        <form action="login_process.php" method="POST">
            <div>
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div>
                <label for="password">รหัสผ่าน:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>