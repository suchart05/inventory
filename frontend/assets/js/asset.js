/* =====================================================
   Asset Control — JavaScript Logic  (asset.js)
   ===================================================== */

// ==================== Category Master Data ====================
const categoryMaster = {
    '01': { name: 'ครุภัณฑ์สำนักงาน',          age: 5,    rate: 20,    note: 'โต๊ะ, เก้าอี้, ตู้เก็บของ ฯลฯ' },
    '02': { name: 'ครุภัณฑ์การศึกษา',           age: 5,    rate: 20,    note: 'สื่อการสอน, หุ่นจำลอง, กระดานดำ ฯลฯ' },
    '03': { name: 'ครุภัณฑ์อาวุธ',              age: 5,    rate: 20,    note: 'ปืน, กุญแจมือ ฯลฯ' },
    '04': { name: 'ครุภัณฑ์คอมพิวเตอร์',       age: 3,    rate: 33.33, note: 'PC, Notebook, Printer, Scanner, Tablet ฯลฯ' },
    '05': { name: 'ครุภัณฑ์ยานพาหนะ',          age: 5,    rate: 20,    note: 'รถยนต์, รถกระบะ, รถจักรยานยนต์ ฯลฯ' },
    '06': { name: 'ครุภัณฑ์ก่อสร้าง',          age: 5,    rate: 20,    note: 'รถแทรกเตอร์, รถเกลี่ยดิน ฯลฯ' },
    '07': { name: 'ครุภัณฑ์ไฟฟ้าและวิทยุ',     age: 5,    rate: 20,    note: 'ทีวี, แอร์, พัดลม, ตู้เย็น ฯลฯ' },
    '08': { name: 'ครุภัณฑ์โฆษณาและเผยแพร่',   age: 5,    rate: 20,    note: 'โปรเจกเตอร์, กล้องถ่ายรูป, ลำโพง ฯลฯ' },
    '09': { name: 'ครุภัณฑ์วิทยาศาสตร์',       age: 5,    rate: 20,    note: 'กล้องจุลทรรศน์, ตาชั่ง ฯลฯ' },
    '10': { name: 'ครุภัณฑ์การแพทย์',          age: 5,    rate: 20,    note: 'เตียงคนไข้, เครื่องวัดความดัน ฯลฯ' },
    '11': { name: 'ครุภัณฑ์งานบ้านงานครัว',    age: 5,    rate: 20,    note: 'เตาแก๊ส, เครื่องซักผ้า, ตู้น้ำเย็น ฯลฯ' },
    '12': { name: 'ครุภัณฑ์กีฬา',             age: 5,    rate: 20,    note: 'โต๊ะปิงปอง, ลู่วิ่ง, จักรยาน ฯลฯ' },
    '13': { name: 'ครุภัณฑ์ดนตรี/นาฏศิลป์',   age: 5,    rate: 20,    note: 'เปียโน, กลอง, กีตาร์, ระนาด ฯลฯ' },
    '14': { name: 'ครุภัณฑ์สนาม',             age: 5,    rate: 20,    note: 'เต็นท์, ชิงช้า, ม้าหมุน, สไลเดอร์ ฯลฯ' },
    '15': { name: 'สิ่งปลูกสร้าง (ถาวร)',      age: 40,   rate: 2.5,   note: 'อาคารเรียน, หอประชุม, บ้านพักครู ฯลฯ' },
    '16': { name: 'สิ่งปลูกสร้าง (ชั่วคราว)', age: 10,   rate: 10,    note: 'ถนน, รั้ว, โรงจอดรถ ฯลฯ' },
    '17': { name: 'ครุภัณฑ์ต่ำกว่าเกณฑ์',     age: null, rate: null,  note: 'ราคา < 10,000 บาท — ลงทะเบียนแต่ไม่คิดค่าเสื่อม' },
};

// ==================== Auto-fill when category changes ====================
function onCategoryChange() {
    const sel       = document.getElementById('assetCategorySelect');
    const ageInput  = document.getElementById('assetAgeInput');
    const rateInput = document.getElementById('assetRateInput');
    const noteBox   = document.getElementById('categoryNote');
    const noteText  = document.getElementById('categoryNoteText');
    const code      = sel ? sel.value : '';

    if (!code || !categoryMaster[code]) {
        if (ageInput)  ageInput.value  = '';
        if (rateInput) rateInput.value = '';
        if (noteBox)   noteBox.style.setProperty('display', 'none', 'important');
        return;
    }
    const cat = categoryMaster[code];
    if (ageInput)  ageInput.value  = cat.age  !== null ? cat.age  : '-';
    if (rateInput) rateInput.value = cat.rate !== null ? cat.rate : '-';
    if (noteText)  noteText.textContent = cat.note;
    if (noteBox)   noteBox.style.setProperty('display', 'flex', 'important');
}

// ==================== Main asset list page functions ====================
function initAssetList() {
    // Tab filter
    document.querySelectorAll('.asset-tabs .nav-link').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.asset-tabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('#assetTableBody tr').forEach(function (row) {
                row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
            });
        });
    });

    // Category filter
    const catFilter = document.getElementById('categoryFilter');
    if (catFilter) {
        catFilter.addEventListener('change', function () {
            const cat = this.value;
            document.querySelectorAll('#assetTableBody tr').forEach(function (row) {
                row.style.display = (!cat || row.dataset.category === cat) ? '' : 'none';
            });
        });
    }

    // Search
    const searchInput = document.getElementById('assetSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#assetTableBody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
}

// ==================== Navigation helpers ====================
function goToAdd()    { window.location.href = 'add_asset.php'; }
function goToBorrow(id) { window.location.href = 'borrow_asset.php?id=' + id; }
function goToReturn(id) { window.location.href = 'return_asset.php?id=' + id; }
function goToDetail(id) { window.location.href = 'asset_detail.php?id=' + id; }
function goToEdit(id)   { window.location.href = 'add_asset.php?id=' + id; }

// ==================== Status update (SweetAlert) ====================
function openStatusModal(id) {
    Swal.fire({
        title: 'อัปเดตสถานะทรัพย์สิน',
        input: 'select',
        inputOptions: { available: 'พร้อมใช้งาน', inuse: 'กำลังยืมใช้', repair: 'ซ่อมบำรุง', writeoff: 'จำหน่ายออก' },
        inputPlaceholder: '-- เลือกสถานะ --',
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#4e54c8',
        inputValidator: (v) => !v && 'กรุณาเลือกสถานะ'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: 'อัปเดตสถานะเรียบร้อยแล้ว', timer: 1800, showConfirmButton: false });
        }
    });
}

// ==================== Write-off confirm (SweetAlert) ====================
function confirmWriteOff(id) {
    Swal.fire({
        title: 'ยืนยันการจำหน่ายออก?',
        text: 'การดำเนินการนี้ไม่สามารถย้อนกลับได้!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, จำหน่ายออก',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ icon: 'success', title: 'จำหน่ายออกเรียบร้อย!', timer: 1800, showConfirmButton: false });
        }
    });
}

// ==================== Init on DOMContentLoaded ====================
document.addEventListener('DOMContentLoaded', function () {
    // Set today's date on any date inputs
    const today = new Date().toISOString().split('T')[0];
    ['borrowDate', 'actualReturnDate', 'receiveDate'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.value = today;
    });

    // Init list-page specifics if the table exists
    if (document.getElementById('assetTableBody')) {
        initAssetList();
    }

    // Init category auto-fill if the select exists
    if (document.getElementById('assetCategorySelect')) {
        onCategoryChange();
    }
});
