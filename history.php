<?php
session_start(); // เริ่มหรือเข้าถึง session ที่มีอยู่

// ตรวจสอบว่าผู้ใช้ login แล้วหรือยัง
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Location: login.php?error=4'); // redirect ไปหน้า login ถ้ายังไม่ได้ login
    exit;
}

 $adminUsername = isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'ผู้ดูแลระบบ';
// $adminFullName = isset($_SESSION['admin_fullname']) ? htmlspecialchars($_SESSION['admin_fullname']) : '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการใช้งาน - USO NET</title>
    <link rel="stylesheet" type="text/css" href="main.css">         
</head>
<body>
    <div class="container">
        <?php include 'navigation_admin.php';?>
        <h1>ประวัติการใช้งานศูนย์ USO NET</h1>

        <div class="filter-section">
    <div class="filter-row filter-labels">
        <label for="startDate">ตั้งแต่วันที่:</label>
        <label for="endDate">ถึงวันที่:</label>
    </div>
    <div class="filter-row filter-inputs">
        <input type="date" id="startDate">
        <input type="date" id="endDate">
    </div>
    <div class="filter-row filter-buttons">
    <button onclick="fetchHistory()">ดูประวัติ</button>
    <button onclick="clearFiltersAndFetchHistory()" class="secondary">ดูทั้งหมด</button>
    <button onclick="exportHistoryToCSV()" style="background-color: #28a745; color: white;">Export เป็น CSV</button> </div>
    
</div>

        <div id="historyTableContainer">
            <p>กำลังโหลดข้อมูลประวัติ...</p>
            </div>
    </div>
<script>
        // --- ฟังก์ชันช่วย (Utility Functions) ---

        /**
         * ฟังก์ชันสำหรับเติมเลข 0 ข้างหน้าตัวเลข ถ้าตัวเลขนั้นน้อยกว่า 10
         * เช่น ถ้าใส่ 5 จะได้ "05", ถ้าใส่ 12 จะได้ "12"
         * ใช้สำหรับจัดรูปแบบวันที่และเวลา
         * @param {number|string} n - ตัวเลขที่ต้องการจัดรูปแบบ
         * @returns {string} - สตริงของตัวเลขที่เติม 0 (ถ้าจำเป็น)
         */
        function pad(n) {
            // แปลง n เป็นตัวเลขก่อน แล้วค่อยเช็ค
            return parseInt(n, 10) < 10 ? '0' + parseInt(n, 10) : n.toString();
        }

        // --- โค้ดที่ทำงานเมื่อหน้าเว็บโหลดเสร็จ (DOMContentLoaded Event Listener) ---
        /**
         * เมื่อโครงสร้าง HTML ของหน้าเว็บโหลดเสร็จเรียบร้อยแล้ว จะทำสิ่งต่อไปนี้:
         * 1. ตั้งค่าเริ่มต้นให้กับช่องเลือกวันที่ (startDate และ endDate) ให้เป็นเดือนปัจจุบัน
         * 2. เรียกฟังก์ชัน fetchHistory() เพื่อดึงข้อมูลประวัติการใช้งานมาแสดงผลครั้งแรก
         */
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date(); // วันที่และเวลาปัจจุบัน
            const firstDayThisMonth = new Date(today.getFullYear(), today.getMonth(), 1); // วันแรกของเดือนปัจจุบัน

            // ตั้งค่าช่อง "ตั้งแต่วันที่" (startDate) ให้เป็นวันแรกของเดือนปัจจุบัน
            document.getElementById('startDate').value = `${firstDayThisMonth.getFullYear()}-${pad(firstDayThisMonth.getMonth() + 1)}-${pad(firstDayThisMonth.getDate())}`;
            
            // ตั้งค่าช่อง "ถึงวันที่" (endDate) ให้เป็นวันปัจจุบัน
            document.getElementById('endDate').value = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
            
            fetchHistory(); // เรียกฟังก์ชันดึงข้อมูลประวัติมาแสดงเมื่อหน้าเว็บโหลดเสร็จ
        });

        // --- ฟังก์ชันหลักๆ สำหรับหน้าประวัติการใช้งาน (Core Functions for History Page) ---

        /**
         * ฟังก์ชันสำหรับดึงข้อมูลประวัติการใช้งานจาก Server (get_history_api.php)
         * โดยจะดึงตามช่วงวันที่ที่ผู้ใช้เลือกในช่อง Filter
         * เมื่อได้ข้อมูลมาแล้ว จะเรียกฟังก์ชัน renderHistoryTable() เพื่อนำข้อมูลไปแสดงเป็นตาราง
         */
        async function fetchHistory() {
            const startDate = document.getElementById('startDate').value; // ค่าวันที่เริ่มต้นจากช่อง input
            const endDate = document.getElementById('endDate').value;     // ค่าวันที่สิ้นสุดจากช่อง input
            const historyTableContainer = document.getElementById('historyTableContainer'); // div ที่จะใช้แสดงตารางประวัติ

            historyTableContainer.innerHTML = '<p>กำลังโหลดข้อมูลประวัติ...</p>'; // แสดงข้อความ "กำลังโหลด..." ระหว่างรอ

            let apiUrl = 'get_history_api.php'; // URL ของ API ที่จะเรียก
            const params = new URLSearchParams(); // ตัวช่วยสร้าง query string (เช่น ?startDate=...&endDate=...)

            // ถ้ามีการเลือกวันที่เริ่มต้น ให้เพิ่มเข้าไปใน params
            if (startDate) {
                params.append('startDate', startDate);
            }
            // ถ้ามีการเลือกวันที่สิ้นสุด ให้เพิ่มเข้าไปใน params
            if (endDate) {
                params.append('endDate', endDate);
            }

            // ถ้ามี params (คือมีการกรองอย่างน้อย 1 อย่าง) ให้ต่อท้าย apiUrl ด้วย query string
            if (params.toString()) {
                apiUrl += '?' + params.toString();
            }
            // console.log("กำลังเรียก API URL:", apiUrl); // สำหรับ Debug ดู URL ที่จะเรียก

            try {
                const response = await fetch(apiUrl); // เรียก API
                if (!response.ok) { // ถ้า Server ตอบกลับมาว่ามีปัญหา (เช่น 404, 500)
                    throw new Error(`Server ตอบกลับมาว่ามีปัญหา: ${response.status} ${response.statusText}`);
                }
                const result = await response.json(); // แปลงข้อมูลที่ Server ส่งกลับมา (ซึ่งเป็น JSON) ให้เป็น JavaScript object

                if (result.success && result.data) { // ถ้า API บอกว่าสำเร็จ และมีข้อมูล (result.data)
                    renderHistoryTable(result.data); // เรียกฟังก์ชันแสดงผลข้อมูลเป็นตาราง
                } else { // ถ้า API บอกว่าไม่สำเร็จ หรือไม่มีข้อมูล
                    historyTableContainer.innerHTML = `<p>ไม่สามารถโหลดข้อมูลประวัติได้: ${result.message || 'ไม่มีข้อมูล'}</p>`;
                }
            } catch (error) { // ถ้าเกิด Error ตอนเรียก API (เช่น Network ขัดข้อง)
                console.error('เกิดข้อผิดพลาดตอนดึงข้อมูลประวัติ:', error);
                historyTableContainer.innerHTML = `<p>เกิดข้อผิดพลาดในการเชื่อมต่อ: ${error.message}</p>`;
            }
        }

        /**
         * ฟังก์ชันสำหรับล้างค่าในช่อง Filter วันที่ (startDate, endDate)
         * แล้วเรียก fetchHistory() อีกครั้งเพื่อดึงข้อมูลทั้งหมด (โดยไม่กรองวันที่)
         */
        function clearFiltersAndFetchHistory() {
            document.getElementById('startDate').value = ''; // ล้างค่าช่องวันที่เริ่มต้น
            document.getElementById('endDate').value = '';   // ล้างค่าช่องวันที่สิ้นสุด
            fetchHistory(); // ดึงข้อมูลประวัติใหม่ (ซึ่งจะกลายเป็นดึงทั้งหมดเพราะไม่มี filter วันที่)
        }
        /**
 * สร้าง URL และสั่งให้เบราว์เซอร์ดาวน์โหลดไฟล์ CSV ของข้อมูลประวัติ
 * โดยใช้ startDate และ endDate ที่ผู้ใช้เลือกใน Filter (ถ้ามี)
 */
        function exportHistoryToCSV() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            let exportUrl = 'export_history_csv.php'; // ชื่อไฟล์ PHP ที่จะสร้างสำหรับ Export
            const params = new URLSearchParams();

            if (startDate) {
                params.append('startDate', startDate);
            }
            if (endDate) {
                params.append('endDate', endDate);
            }

            if (params.toString()) { // ถ้ามี parameters (มีการกรองวันที่)
                exportUrl += '?' + params.toString();
            }

            // console.log('Export URL:', exportUrl); // สำหรับ Debug ดู URL ที่จะเรียก

            // สั่งให้เบราว์เซอร์ไปที่ URL นี้ ซึ่งจะเริ่มการดาวน์โหลดไฟล์
            // เราไม่ใช้ fetch() ที่นี่เพราะต้องการให้เบราว์เซอร์จัดการการดาวน์โหลดไฟล์โดยตรง
            window.location.href = exportUrl;
            // หรือถ้าต้องการเปิดในแท็บใหม่ (อาจจะดีกว่าถ้ามีปัญหาเรื่องการ redirect)
            // window.open(exportUrl, '_blank');
        }
        /**
         * ฟังก์ชันสำหรับนำข้อมูลประวัติการใช้งาน (historyData) มาสร้างเป็นตาราง HTML แล้วแสดงผล
         * @param {Array<object>} historyData - Array ของข้อมูล Log การใช้งานแต่ละรายการ
         */
        function renderHistoryTable(historyData) {
            const historyTableContainer = document.getElementById('historyTableContainer');
            historyTableContainer.innerHTML = ''; // เคลียร์เนื้อหาเก่าใน div นี้ออกก่อน (เช่น ข้อความ "กำลังโหลด...")

            if (historyData.length === 0) { // ถ้าไม่มีข้อมูลประวัติเลย
                historyTableContainer.innerHTML = '<p>ไม่พบข้อมูลประวัติในช่วงวันที่ที่เลือก หรือยังไม่มีข้อมูลการใช้งาน</p>';
                return; // จบการทำงานของฟังก์ชันนี้
            }

            const table = document.createElement('table'); // สร้าง element <table>
            table.id = 'historyTable'; // กำหนด id ให้ตาราง (เผื่อใช้ CSS จัดสไตล์เฉพาะ)

            // --- สร้างส่วนหัวของตาราง (Table Header) ---
            const thead = table.createTHead(); // สร้าง <thead>
            const headerRow = thead.insertRow(); // สร้างแถว (<tr>) สำหรับหัวตาราง
            const headers = [ // รายชื่อหัวคอลัมน์ที่จะแสดง
                'ลำดับ(วัน)', 'วันที่', 'ผู้ใช้งาน', 'ประเภท', 'เวลาเข้า', 'เวลาออก',
                'เลขคอมฯ', 'หูฟัง', 'คืนหูฟัง', 'รายละเอียด', 'หมายเหตุ'
            ];
            headers.forEach(headerText => { // วนลูปสร้าง <th> สำหรับแต่ละหัวคอลัมน์
                const th = document.createElement('th');
                th.textContent = headerText; // ใส่ข้อความหัวคอลัมน์
                headerRow.appendChild(th); // เพิ่ม <th> เข้าไปในแถว headerRow
            });

            // --- สร้างส่วนเนื้อหาของตาราง (Table Body) ---
            const tbody = table.createTBody(); // สร้าง <tbody>
            historyData.forEach(log => { // วนลูปข้อมูลประวัติแต่ละรายการ (log)
                const row = tbody.insertRow(); // สร้างแถวใหม่ (<tr>) สำหรับข้อมูล log นี้

                // เพิ่มข้อมูลเข้าแต่ละเซลล์ (<td>) ในแถว
                row.insertCell().textContent = log.dailySequence; // ลำดับที่ในแต่ละวัน (คำนวณมาจาก PHP)
                row.insertCell().textContent = log.ServiceDate;   // วันที่ใช้บริการ
                row.insertCell().textContent = `${log.Prefix || ''}${log.FirstName} ${log.LastName}`; // ชื่อผู้ใช้

                // คำนวณประเภทผู้ใช้จากอายุ
                let userType = '';
                const age = parseInt(log.Age, 10);
                if (age >= 0 && age <= 14) userType = 'นักเรียน';
                else if (age >= 15) userType = 'ประชาชน';
                else userType = 'ไม่ระบุ';
                row.insertCell().textContent = userType; // ประเภทผู้ใช้

                row.insertCell().textContent = log.LoginTime;    // เวลาเข้า
                row.insertCell().textContent = log.LogoutTime || '-'; // เวลาออก (ถ้าไม่มี ให้แสดง "-")

                row.insertCell().textContent = log.ComputerNumber; // เลขคอมพิวเตอร์
                row.insertCell().textContent = (log.HeadphoneID && log.HeadphoneID !== 'NO_HEADPHONE') ? log.HeadphoneID : '-'; // เลขหูฟัง (ถ้าไม่ยืม หรือเป็น "NO_HEADPHONE" ให้แสดง "-")
                
                // แสดงสถานะการคืนหูฟัง
                let headphoneStatus = '-';
                if (log.HeadphoneID && log.HeadphoneID !== 'NO_HEADPHONE' && log.HeadphoneID !== null && log.HeadphoneID !== '') { // เช็คว่ามีการยืมหูฟังจริงหรือไม่
                    headphoneStatus = log.HeadphoneReturned == 1 ? 'คืนแล้ว' : (log.LogoutTime ? 'ยังไม่คืน' : 'ยืมอยู่');
                }
                row.insertCell().textContent = headphoneStatus; // สถานะคืนหูฟัง

                row.insertCell().textContent = log.UsageDetails || '-'; // รายละเอียดการใช้งาน
                
                // แสดงหมายเหตุ (Notes) และให้มีการขึ้นบรรทัดใหม่ตามที่พิมพ์
                const notesCell = row.insertCell();
                const notesDiv = document.createElement('div');
                notesDiv.classList.add('notes-display'); // ใช้ class นี้สำหรับ CSS (เพื่อให้ white-space: pre-wrap ทำงาน)
                notesDiv.textContent = log.Notes || '-';   // หมายเหตุ
                notesCell.appendChild(notesDiv);
            });

            historyTableContainer.appendChild(table); // นำตารางที่สร้างเสร็จแล้วไปแสดงใน div ที่กำหนด
        }
    </script>
</body>
</html>