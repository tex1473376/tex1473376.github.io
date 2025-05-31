<?php
session_start(); // เริ่มหรือเข้าถึง session ที่มีอยู่

// ตรวจสอบว่าผู้ใช้ login แล้วหรือยัง
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Location: login.php?error=4');
    exit;
}

// ดึงชื่อผู้ใช้จาก Session สำหรับแสดงผล (เป็นทางเลือก)
$adminUsername = isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'ผู้ดูแลระบบ';
// $adminFullName = isset($_SESSION['admin_fullname']) ? htmlspecialchars($_SESSION['admin_fullname']) : ''; // ถ้าจะใช้ FullName
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบันทึกการใช้งาน USO NET</title>
    <link rel="stylesheet" type="text/css" href="main.css">
    </head>
<body>
    <div class="container">
        <?php include 'navigation_admin.php'; // Include Navigation Menu ?>
        <h1>ระบบบันทึกการใช้งานศูนย์ USO NET โรงเรียนบ้านห้วยลึก</h1>

        <div class="section" id="searchUserSection">
            <h2>ค้นหาผู้ใช้บริการ</h2>
            <label for="searchTerm">ค้นหาด้วยเลขบัตรประชาชน หรือ ชื่อ-สกุล:</label>
            <input type="text" id="searchTerm" name="searchTerm" placeholder="พิมพ์คำค้นหา...">
            <div class="button-group">
                <button onclick="searchUsers()">ค้นหา</button>
                <button onclick="openAddUserModal()" class="secondary">เพิ่มผู้ใช้ใหม่</button>
            </div>
            <div id="searchResults"></div>
        </div>

        <div class="section" id="activeUsersDashboardSection">
            <h2>ผู้ใช้งานปัจจุบัน</h2>
            <div id="activeUsersDashboard">
                <p>กำลังโหลดข้อมูล...</p>
            </div>
        </div>
    </div> <div id="addUserModalOverlay" class="modal-overlay" onclick="closeAddUserModalOnClickOutside(event)">
        <div class="modal" id="addUserModalContent">
            <div class="modal-header">
                <h2>เพิ่มผู้ใช้บริการใหม่</h2>
                <button class="modal-close-button" onclick="closeAddUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div><label for="add_nationalID">เลขบัตรประจำตัวประชาชน (13 หลัก):</label><input type="text" id="add_nationalID" name="nationalID" maxlength="13" required></div>
                    <div><label for="add_prefix">คำนำหน้าชื่อ:</label><select id="add_prefix" name="prefix"><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="เด็กชาย">เด็กชาย</option><option value="เด็กหญิง">เด็กหญิง</option><option value="อื่นๆ">อื่นๆ</option></select></div>
                    <div><label for="add_firstName">ชื่อจริง:</label><input type="text" id="add_firstName" name="firstName" required></div>
                    <div><label for="add_lastName">นามสกุล:</label><input type="text" id="add_lastName" name="lastName" required></div>
                    <div><label for="add_age">อายุ (ปี):</label><input type="number" id="add_age" name="age" min="0" max="120" required></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary" onclick="closeAddUserModal()">ยกเลิก</button>
                <button type="submit" form="addUserForm">บันทึกผู้ใช้ใหม่</button>
            </div>
            <div id="addUserMessage"></div>
        </div>
    </div>

    <div id="logEntryModalOverlay" class="modal-overlay" onclick="closeLogEntryModalOnClickOutside(event)">
        <div class="modal" id="logEntryModalContent">
            <div class="modal-header">
                <h2>บันทึกการเข้าใช้งาน</h2>
                <button class="modal-close-button" onclick="closeLogEntryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="selectedUserInfo_modal"></div>
                <div id="userTypeDisplay_modal"></div>
                <input type="hidden" id="selectedUserID_modal" name="selectedUserID_modal">
                <div class="form-grid">
                    <div><label for="serviceDate_modal">วันที่เข้าใช้บริการ:</label><input type="date" id="serviceDate_modal" name="serviceDate_modal"></div>
                    <div><label for="loginTime_modal">เวลาเข้า (HH:MM):</label><input type="time" id="loginTime_modal" name="loginTime_modal"></div>
                    <div><label for="logoutTime_modal">เวลาออก (HH:MM):</label><input type="time" id="logoutTime_modal" name="logoutTime_modal"></div>
                </div>
                <label for="usageDetails_modal">รายละเอียดการใช้งาน:</label><textarea id="usageDetails_modal" name="usageDetails_modal" placeholder="เช่น ค้นคว้าข้อมูล, พิมพ์งาน, เรียนออนไลน์"></textarea>
                <div class="form-grid form-grid-triple">
                    <div><label for="computerNumber_modal">เลขเครื่องคอมพิวเตอร์:</label><select id="computerNumber_modal" name="computerNumber_modal"><option value="">-- เลือกเครื่องคอมพิวเตอร์ --</option><option value="COM01">COM01</option><option value="COM02">COM02</option><option value="COM03">COM03</option><option value="COM04">COM04</option><option value="COM05">COM05</option><option value="COM06">COM06</option><option value="COM07">COM07</option><option value="COM08">COM08</option><option value="COM09">COM09</option><option value="COM10">COM10</option><option value="COMADMIN">COMADMIN (ผู้ดูแล)</option><option value="COMTEACHER">COMTEACHER (ครู)</option><option value="NO_COMPUTER">ไม่ใช้งานเครื่องคอม</option></select></div>
                    <div><label for="headphoneID_modal">หูฟัง (ถ้ามี):</label><select id="headphoneID_modal" name="headphoneID_modal"><option value="NO_HEADPHONE">ไม่ยืมหูฟัง</option><option value="HP01">HP01</option><option value="HP02">HP02</option><option value="HP03">HP03</option><option value="HP04">HP04</option><option value="HP05">HP05</option><option value="HP06">HP06</option><option value="HP07">HP07</option><option value="HP08">HP08</option><option value="HP09">HP09</option><option value="HP10">HP10</option><option value="HPTEACHER">HPTEACHER (หูฟังคุณครู)</option><option value="HPADMIN">HPADMIN (หูฟังผู้ดูแล)</option></select></div>
                    <div><label for="headphoneReturned_modal_retro">สถานะคืนหูฟัง:</label><select id="headphoneReturned_modal_retro" name="headphoneReturned_modal_retro"><option value="">ไม่ระบุ/ยังไม่เลิกใช้</option><option value="1">คืนแล้ว</option><option value="0">ยังไม่คืน</option></select></div>
                </div>
                <div><label for="notes_modal_retro">หมายเหตุ (ถ้ามี):</label><textarea id="notes_modal_retro" name="notes_modal_retro" placeholder="กรอกหมายเหตุ..."></textarea></div>
            </div>
            <div class="modal-footer">
                 <button type="button" class="secondary" onclick="closeLogEntryModal()">ยกเลิก</button>
                 <button onclick="submitLogEntry()">บันทึกข้อมูล</button>
            </div>
            <div id="logEntryMessage"></div>
        </div>
    </div>

    <div id="logoutModalOverlay" class="modal-overlay" onclick="closeLogoutModalOnClickOutside(event)">
        <div class="modal" id="logoutModalContent">
            <div class="modal-header">
                <h2 id="logoutModalTitle">ยืนยันการเลิกใช้งาน</h2> <button class="modal-close-button" onclick="closeLogoutModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="logoutConfirmMessage" style="margin-bottom: 15px;"></p>
                <div id="logoutHeadphoneSection" style="display:none; margin-bottom:15px;">
                    <label>สถานะการคืนหูฟัง (<span id="logoutHeadphoneIDDisplay"></span>):</label>
                    <div>
                        <input type="radio" id="headphoneReturnedYes" name="headphoneStatus" value="1" checked>
                        <label for="headphoneReturnedYes">คืนแล้ว</label>
                        <input type="radio" id="headphoneReturnedNo" name="headphoneStatus" value="0">
                        <label for="headphoneReturnedNo">ยังไม่คืน</label>
                    </div>
                </div>
                <div><label for="logoutNotes">หมายเหตุ (ถ้ามี):</label><textarea id="logoutNotes" placeholder="กรอกหมายเหตุ..."></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary" onclick="closeLogoutModal()">ยกเลิก</button>
                <button onclick="submitLogoutFromModal()">ยืนยันการเลิกใช้งาน</button>
            </div>
            <div id="logoutResponseMessage"></div>
        </div>
    </div>

    <script>
        // --- Global Variables ---
        let currentSelectedUser = null;     // Stores the user object selected from search results for logging
        let debounceTimer;                  // Timer for debouncing search input
        let currentLogoutLogID = null;      // Stores LogID for the current logout operation
        let currentLogoutHeadphoneID = null;// Stores HeadphoneID for the current logout operation

        // --- Utility Functions ---
        /**
         * Pads a number with a leading zero if it's less than 10.
         * @param {number|string} n The number to pad.
         * @returns {string} The padded number as a string.
         */
        function pad(n) {
            return parseInt(n, 10) < 10 ? '0' + parseInt(n, 10) : n.toString();
        }

        // --- Add User Modal: Element References & Management Functions ---
        const addUserModalOverlay = document.getElementById('addUserModalOverlay');
        const addUserFormElement = document.getElementById('addUserForm');
        const addUserMessageDiv = document.getElementById('addUserMessage');

        /** Opens the "Add New User" modal, resets the form, and clears previous messages. */
        function openAddUserModal() {
            addUserFormElement.reset();
            addUserMessageDiv.textContent = ''; addUserMessageDiv.style.color = '';
            addUserModalOverlay.style.display = 'flex';
            document.getElementById('add_nationalID').focus();
        }
        /** Closes the "Add New User" modal. */
        function closeAddUserModal() { addUserModalOverlay.style.display = 'none'; }
        /** Closes the "Add New User" modal if a click occurs on the overlay itself. */
        function closeAddUserModalOnClickOutside(event) { if (event.target === addUserModalOverlay) closeAddUserModal(); }

        // --- Log Entry Modal: Element References & Management Functions ---
        const logEntryModalOverlay = document.getElementById('logEntryModalOverlay');
        const logEntryMessageDiv = document.getElementById('logEntryMessage');

        /**
         * Opens the "Log Entry" modal and populates it with the selected user's data
         * and defaults for new log entry.
         * @param {object} user - The user object from search results.
         */
        function openLogEntryModal(user) {
            currentSelectedUser = user;
            document.getElementById('selectedUserInfo_modal').innerHTML = `<strong>ผู้ใช้ที่เลือก:</strong> ${user.Prefix || ''}${user.FirstName} ${user.LastName} (เลขบัตร: ${user.NationalID})`;
            document.getElementById('selectedUserID_modal').value = user.UserID;
            
            let userType = ''; const age = parseInt(user.Age, 10);
            if (age >= 0 && age <= 14) userType = 'นักเรียน';
            else if (age >= 15) userType = 'ประชาชน';
            else userType = 'ไม่ระบุอายุ';
            document.getElementById('userTypeDisplay_modal').innerHTML = `<strong>ประเภทผู้ใช้:</strong> ${userType}`;

            const now = new Date();
            document.getElementById('serviceDate_modal').value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            document.getElementById('loginTime_modal').value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            
            document.getElementById('logoutTime_modal').value = '';
            document.getElementById('headphoneReturned_modal_retro').value = '';
            document.getElementById('notes_modal_retro').value = '';
            document.getElementById('usageDetails_modal').value = '';
            document.getElementById('computerNumber_modal').value = '';
            document.getElementById('headphoneID_modal').value = 'NO_HEADPHONE';
            
            logEntryMessageDiv.textContent = ''; logEntryMessageDiv.style.color = '';
            logEntryModalOverlay.style.display = 'flex';
            document.getElementById('usageDetails_modal').focus();
        }
        /** Closes the "Log Entry" modal. */
        function closeLogEntryModal() { logEntryModalOverlay.style.display = 'none'; }
        /** Closes the "Log Entry" modal if a click occurs on the overlay. */
        function closeLogEntryModalOnClickOutside(event) { if (event.target === logEntryModalOverlay) closeLogEntryModal(); }
        
        // --- Logout Modal: Element References & Management Functions ---
        const logoutModalOverlay = document.getElementById('logoutModalOverlay');
        // const logoutModalTitle = document.getElementById('logoutModalTitle'); // Title is static
        const logoutConfirmMessage = document.getElementById('logoutConfirmMessage');
        const logoutHeadphoneSection = document.getElementById('logoutHeadphoneSection');
        const logoutHeadphoneIDDisplay = document.getElementById('logoutHeadphoneIDDisplay');
        const logoutNotesTextarea = document.getElementById('logoutNotes');
        const logoutResponseMessage = document.getElementById('logoutResponseMessage');

        /**
         * Opens the "Logout Confirmation" modal.
         * @param {number} logID - The ID of the usage log to be closed.
         * @param {string|null} headphoneID - The ID of the headphone borrowed, if any.
         * @param {string} userName - The name of the user logging out for display.
         */
        function openLogoutModal(logID, headphoneID, userName) {
            currentLogoutLogID = logID; currentLogoutHeadphoneID = headphoneID;
            logoutConfirmMessage.textContent = `คุณต้องการบันทึกการเลิกใช้งานสำหรับ "${userName}" ใช่หรือไม่?`;
            logoutNotesTextarea.value = '';
            logoutResponseMessage.textContent = ''; logoutResponseMessage.style.color = '';
            
            const hasHeadphoneToHandle = (headphoneID && headphoneID !== 'NO_HEADPHONE' && headphoneID !== null && headphoneID !== '');
            if (hasHeadphoneToHandle) {
                logoutHeadphoneIDDisplay.textContent = `ID: ${headphoneID}`;
                document.getElementById('headphoneReturnedYes').checked = true;
                logoutHeadphoneSection.style.display = 'block';
            } else {
                logoutHeadphoneSection.style.display = 'none';
            }
            logoutModalOverlay.style.display = 'flex';
        }
        /** Closes the "Logout Confirmation" modal. */
        function closeLogoutModal() { logoutModalOverlay.style.display = 'none'; }
        /** Closes the "Logout Confirmation" modal if a click occurs on the overlay. */
        function closeLogoutModalOnClickOutside(event) { if (event.target === logoutModalOverlay) closeLogoutModal(); }

        // --- Main Action Handler for "เลิกใช้งาน" Button (called from Dashboard) ---
        /**
         * Initiates the logout process by opening the logout confirmation modal.
         * @param {number} logID The LogID of the session to end.
         * @param {string|null} headphoneID The ID of the headphone used in the session.
         * @param {string} userName The name of the user for display in the modal.
         */
        function handleLogout(logID, headphoneID, userName) {
            openLogoutModal(logID, headphoneID, userName);
        }

        // --- DOMContentLoaded: Setup initial page state and global event listeners ---
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const localTodayDate = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
            document.getElementById('serviceDate_modal').setAttribute('max', localTodayDate);
            
            fetchActiveUsers();

            const searchTermInput = document.getElementById('searchTerm');
            searchTermInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(searchUsers, 400);
            });

            if (addUserFormElement) {
                addUserFormElement.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    addUserMessageDiv.textContent = 'กำลังบันทึก...'; addUserMessageDiv.style.color = 'blue';
                    const formData = {
                        nationalID: document.getElementById('add_nationalID').value,
                        prefix: document.getElementById('add_prefix').value,
                        firstName: document.getElementById('add_firstName').value,
                        lastName: document.getElementById('add_lastName').value,
                        age: document.getElementById('add_age').value
                    };
                    if (formData.nationalID.length !== 13 || !/^\d+$/.test(formData.nationalID)) {
                        addUserMessageDiv.textContent = 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'; addUserMessageDiv.style.color = 'red'; return;
                    }
                    if (!formData.firstName.trim() || !formData.lastName.trim() || !formData.age.trim() || parseInt(formData.age) < 0 || parseInt(formData.age) > 120) {
                        addUserMessageDiv.textContent = 'กรุณากรอกชื่อ, นามสกุล และอายุให้ถูกต้อง (0-120 ปี)'; addUserMessageDiv.style.color = 'red'; return;
                    }
                    try {
                        const response = await fetch('add_user_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) });
                        const result = await response.json();
                        if (result.success) {
                            addUserMessageDiv.textContent = result.message; addUserMessageDiv.style.color = 'green';
                            addUserFormElement.reset();
                            setTimeout(closeAddUserModal, 500); // Faster close
                        } else {
                            addUserMessageDiv.textContent = 'เกิดข้อผิดพลาด: ' + (result.message || 'ไม่สามารถเพิ่มผู้ใช้ได้'); addUserMessageDiv.style.color = 'red';
                        }
                    } catch (error) {
                        console.error('Add user error:', error);
                        addUserMessageDiv.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message; addUserMessageDiv.style.color = 'red';
                    }
                });
            }
        });

        // --- Core Application Logic Functions ---

        /** Fetches users from the backend based on the search term and renders them. */
        async function searchUsers() {
            const searchTerm = document.getElementById('searchTerm').value;
            const searchResultsDiv = document.getElementById('searchResults');
            currentSelectedUser = null;

            if (searchTerm.trim() === '') {
                searchResultsDiv.innerHTML = ''; return;
            }
            searchResultsDiv.innerHTML = '<p style="padding:10px; text-align:center;">กำลังค้นหา...</p>';
            try {
                const response = await fetch('search_users_api.php?term=' + encodeURIComponent(searchTerm));
                if (!response.ok) throw new Error(`Network error: ${response.statusText} (Status: ${response.status})`);
                const result = await response.json();
                searchResultsDiv.innerHTML = '';
                if (result.success && result.data && result.data.length > 0) {
                    result.data.forEach(user => {
                        const userDiv = document.createElement('div');
                        userDiv.classList.add('user-item');
                        userDiv.textContent = `${user.Prefix || ''}${user.FirstName} ${user.LastName} (ID: ${user.NationalID}, อายุ: ${user.Age})`;
                        userDiv.onclick = () => openLogEntryModal(user);
                        searchResultsDiv.appendChild(userDiv);
                    });
                } else if (result.success && result.data && result.data.length === 0) {
                     searchResultsDiv.innerHTML = '<p style="padding:10px; text-align:center;">ไม่พบผู้ใช้งานที่ตรงกับคำค้นหา</p>';
                } else {
                    searchResultsDiv.innerHTML = `<p style="padding:10px; text-align:center; color:red;">${result.message || 'ค้นหาไม่สำเร็จ'}</p>`;
                }
            } catch (error) {
                console.error('Search error:', error);
                searchResultsDiv.innerHTML = `<p style="padding:10px; text-align:center; color:red;">ผิดพลาด: ${error.message}</p>`;
            }
        }

        /** Submits the log entry form data to the backend. */
        async function submitLogEntry() {
            if (!currentSelectedUser) {
                logEntryMessageDiv.textContent = 'ผิดพลาด: ไม่พบผู้ใช้ที่เลือก'; logEntryMessageDiv.style.color = 'red'; return;
            }
            const logData = {
                userID: document.getElementById('selectedUserID_modal').value,
                serviceDate: document.getElementById('serviceDate_modal').value,
                loginTime: document.getElementById('loginTime_modal').value,
                logoutTime: document.getElementById('logoutTime_modal').value || null,
                usageDetails: document.getElementById('usageDetails_modal').value,
                computerNumber: document.getElementById('computerNumber_modal').value,
                headphoneID: document.getElementById('headphoneID_modal').value,
                headphoneReturned: document.getElementById('headphoneReturned_modal_retro').value === "" ? null : parseInt(document.getElementById('headphoneReturned_modal_retro').value, 10),
                notes: document.getElementById('notes_modal_retro').value.trim() === "" ? null : document.getElementById('notes_modal_retro').value.trim()
            };
            logEntryMessageDiv.textContent = 'กำลังบันทึก...'; logEntryMessageDiv.style.color = 'blue';

            // Client-side validations
            if (!logData.serviceDate) { logEntryMessageDiv.textContent = 'กรุณาระบุวันที่เข้าใช้'; logEntryMessageDiv.style.color = 'red'; document.getElementById('serviceDate_modal').focus(); return; }
            if (!logData.loginTime) { logEntryMessageDiv.textContent = 'กรุณาระบุเวลาเข้า'; logEntryMessageDiv.style.color = 'red'; document.getElementById('loginTime_modal').focus(); return; }
            if (logData.computerNumber === "") { logEntryMessageDiv.textContent = 'กรุณาเลือกเครื่องคอมพิวเตอร์'; logEntryMessageDiv.style.color = 'red'; document.getElementById('computerNumber_modal').focus(); return; }
            const today = new Date();
            const localTodayDate = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
            if (logData.serviceDate > localTodayDate) { logEntryMessageDiv.textContent = 'ไม่สามารถเลือกวันที่ในอนาคตได้'; logEntryMessageDiv.style.color = 'red'; document.getElementById('serviceDate_modal').focus(); return; }
            if (logData.usageDetails.trim() === "") { logEntryMessageDiv.textContent = 'กรุณากรอกรายละเอียดการใช้งาน'; logEntryMessageDiv.style.color = 'red'; document.getElementById('usageDetails_modal').focus(); return; }
            if (logData.logoutTime && logData.loginTime && logData.serviceDate === localTodayDate && logData.logoutTime < logData.loginTime) {
                logEntryMessageDiv.textContent = 'เวลาออกต้องไม่ก่อนเวลาเข้าในวันเดียวกัน'; logEntryMessageDiv.style.color = 'red'; document.getElementById('logoutTime_modal').focus(); return;
            }
             if (logData.headphoneReturned !== null && !logData.logoutTime && logData.headphoneID !== 'NO_HEADPHONE') {
                logEntryMessageDiv.textContent = 'กรุณาระบุเวลาออก หากต้องการบันทึกสถานะการคืนหูฟัง';
                logEntryMessageDiv.style.color = 'red'; document.getElementById('logoutTime_modal').focus(); return;
            }

            try {
                const response = await fetch('log_session_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(logData) });
                const result = await response.json();
                if (result.success) {
                    logEntryMessageDiv.textContent = result.message; logEntryMessageDiv.style.color = 'green';
                    setTimeout(() => {
                        closeLogEntryModal();
                        document.getElementById('searchResults').innerHTML = ''; document.getElementById('searchTerm').value = '';
                        currentSelectedUser = null;
                    }, 500);
                    fetchActiveUsers();
                } else {
                    logEntryMessageDiv.textContent = 'ผิดพลาดในการบันทึก: ' + (result.message || 'ไม่ทราบสาเหตุ'); logEntryMessageDiv.style.color = 'red';
                }
            } catch (error) {
                console.error('Submit log error:', error);
                logEntryMessageDiv.textContent = 'ผิดพลาดเชื่อมต่อเพื่อบันทึก: ' + error.message; logEntryMessageDiv.style.color = 'red';
            }
        }

        /** Submits the logout information from the Logout Modal. */
        async function submitLogoutFromModal() {
            if (!currentLogoutLogID) { logoutResponseMessage.textContent = 'ผิดพลาด: ไม่พบ LogID'; logoutResponseMessage.style.color = 'red'; return; }
            let headphoneWasReturned = null;
            const hasHeadphoneToCheck = (currentLogoutHeadphoneID && currentLogoutHeadphoneID !== 'NO_HEADPHONE' && currentLogoutHeadphoneID !== null && currentLogoutHeadphoneID !== '');
            if (hasHeadphoneToCheck) headphoneWasReturned = document.getElementById('headphoneReturnedYes').checked;
            const notes = logoutNotesTextarea.value.trim();
            logoutResponseMessage.textContent = 'กำลังบันทึก...'; logoutResponseMessage.style.color = 'blue';
            const logoutData = { logID: currentLogoutLogID, headphoneReturned: headphoneWasReturned, headphoneID: currentLogoutHeadphoneID };

            try {
                const logoutResponse = await fetch('logout_session_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(logoutData) });
                const logoutResult = await logoutResponse.json();
                if (logoutResult.success) {
                    logoutResponseMessage.textContent = logoutResult.message; logoutResponseMessage.style.color = 'green';
                    if (notes !== "") {
                        logoutResponseMessage.textContent += ' กำลังบันทึกหมายเหตุ...';
                        try {
                            const notesData = { logID: currentLogoutLogID, notes: notes };
                            const notesResponse = await fetch('update_notes_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(notesData) });
                            const notesResult = await notesResponse.json();
                            if (notesResult.success) logoutResponseMessage.textContent = logoutResult.message + ' และบันทึกหมายเหตุสำเร็จ!';
                            else { logoutResponseMessage.textContent = logoutResult.message + ' แต่พลาดบันทึกหมายเหตุ: ' + (notesResult.message || ''); logoutResponseMessage.style.color = 'orange'; }
                        } catch (notesError) {
                            console.error('Update notes error:', notesError);
                            logoutResponseMessage.textContent = logoutResult.message + ' แต่พลาดเชื่อมต่อบันทึกหมายเหตุ.'; logoutResponseMessage.style.color = 'orange';
                        }
                    }
                    fetchActiveUsers();
                    setTimeout(() => { closeLogoutModal(); }, notes !== "" ? 1500 : 500);
                } else {
                    logoutResponseMessage.textContent = 'ผิดพลาดในการเลิกใช้: ' + (logoutResult.message || ''); logoutResponseMessage.style.color = 'red';
                }
            } catch (error) {
                console.error('Submit logout error:', error);
                logoutResponseMessage.textContent = 'พลาดเชื่อมต่อเพื่อเลิกใช้: ' + error.message; logoutResponseMessage.style.color = 'red';
            }
        }

        /** Fetches and renders the list of currently active users. */
        async function fetchActiveUsers() {
            const activeUsersDashboardDiv = document.getElementById('activeUsersDashboard');
            try {
                const response = await fetch('get_active_users_api.php');
                if (!response.ok) throw new Error(`Network error: ${response.statusText} (Status: ${response.status})`);
                const result = await response.json();
                if (result.success && result.data) renderActiveUsers(result.data);
                else activeUsersDashboardDiv.innerHTML = `<p>โหลดข้อมูลผู้ใช้ปัจจุบันไม่ได้: ${result.message || 'ไม่มีข้อมูล'}</p>`;
            } catch (error) {
                console.error('Fetch active users error:', error);
                activeUsersDashboardDiv.innerHTML = `<p>พลาดเชื่อมต่อ (Active Users): ${error.message}</p>`;
            }
        }

        /** Renders the table of active users in the dashboard. @param {Array<object>} activeUsers */
        function renderActiveUsers(activeUsers) {
            const activeUsersDashboardDiv = document.getElementById('activeUsersDashboard');
            activeUsersDashboardDiv.innerHTML = '';
            if (activeUsers.length === 0) { activeUsersDashboardDiv.innerHTML = '<p>ขณะนี้ไม่มีผู้ใช้งาน</p>'; return; }
            const table = document.createElement('table');
            const thead = table.createTHead(); const headerRow = thead.insertRow();
            const headers = [
                { text: 'ผู้ใช้งาน', align: 'left' }, { text: 'เครื่องคอมฯ', align: 'center' },
                { text: 'หูฟัง', align: 'center' }, { text: 'วันที่ เวลาเข้า', align: 'center' },
                { text: 'จัดการ', align: 'center' }
            ];
            headers.forEach(headerInfo => { const th = document.createElement('th'); th.textContent = headerInfo.text; th.style.textAlign = headerInfo.align; headerRow.appendChild(th); });
            const tbody = table.createTBody();
            activeUsers.forEach(log => {
                const row = tbody.insertRow(); let cellIndex = 0;
                function addCellWithAlignment(data) { const cell = row.insertCell(); cell.textContent = data; if(headers[cellIndex]) cell.style.textAlign = headers[cellIndex].align; cellIndex++; }
                addCellWithAlignment(`${log.Prefix || ''}${log.FirstName} ${log.LastName}`);
                addCellWithAlignment(log.ComputerNumber);
                addCellWithAlignment((log.HeadphoneID && log.HeadphoneID !== 'NO_HEADPHONE') ? log.HeadphoneID : '-');
                addCellWithAlignment(`${log.ServiceDate} ${log.LoginTime}`);
                const manageCell = row.insertCell(); if(headers[cellIndex]) manageCell.style.textAlign = headers[cellIndex].align;
                const logoutButton = document.createElement('button');
                logoutButton.textContent = 'เลิกใช้งาน'; logoutButton.classList.add('secondary');
                logoutButton.style.fontSize = '0.9em'; logoutButton.style.padding = '5px 10px';
                logoutButton.onclick = () => handleLogout(log.LogID, log.HeadphoneID, `${log.Prefix || ''}${log.FirstName} ${log.LastName}`);
                manageCell.appendChild(logoutButton);
                 Array.from(row.cells).forEach(cell => { cell.style.padding = '10px 12px'; });
            });
            activeUsersDashboardDiv.appendChild(table);
        }
    </script>
</body>
</html>