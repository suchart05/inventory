<style>
    /* Custom Modern CSS styles */
    .modern-wrapper { background-color: #f1f5f9; min-height: 100vh; padding-bottom: 2rem; }
    .modern-card { border-radius: 1rem; border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); overflow: hidden; }
    .modern-header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 1.25rem 1.75rem; }
    
    .section-title { font-weight: 700; color: #334155; display: flex; align-items: center; gap: 0.5rem; margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1.05rem; }
    .section-title i { color: #3b82f6; background: #eff6ff; padding: 0.4rem; border-radius: 0.4rem; font-size: 1rem; }
    
    /* Modern Inputs */
    .form-control, .form-select { border-radius: 0.5rem; padding: 0.4rem 0.75rem; border: 1px solid #cbd5e1; background-color: #f8fafc; color: #334155; font-size: 0.9rem; transition: all 0.2s ease; }
    .form-control:focus, .form-select:focus { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    .form-label { font-weight: 600; color: #475569; margin-bottom: 0.3rem; font-size: 0.85rem; }
    
    /* Custom Radio Cards */
    .radio-card input[type="radio"] { display: none; }
    .radio-card label { display: block; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s ease; background: #ffffff; }
    .radio-card label:hover { border-color: #cbd5e1; background-color: #f8fafc; }
    .radio-card input[type="radio"]:checked + label.type-normal { border-color: #10b981; background-color: #ecfdf5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1); }
    .radio-card input[type="radio"]:checked + label.type-defect { border-color: #ef4444; background-color: #fef2f2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1); }
    .radio-icon { font-size: 1.2rem; margin-bottom: 0.3rem; display: block; }
    
    /* Committee Box */
    .committee-box { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; margin-bottom: 0.75rem; border-left: 4px solid #3b82f6; transition: all 0.2s ease; }
    .committee-box:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transform: translateY(-1px); }
    
    /* Buttons */
    .btn-submit { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; border-radius: 0.75rem; font-weight: 600; padding: 0.6rem 1.5rem; font-size: 1rem; transition: all 0.2s ease; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 12px -3px rgba(37, 99, 235, 0.3); background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
</style>

<div class="container-fluid pt-4 modern-wrapper">
    <div class="row">
        <div class="col-xl-8 col-lg-9 mx-auto">
            <div class="card modern-card">
                
                <div class="modern-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="mb-1 fw-bold"><i class="fas fa-file-signature me-2"></i>ระบบสร้างรายงานพัสดุประจำปี</h3>
                        <p class="mb-0 text-white-50 fs-6">สร้างเอกสารแบบฟอร์ม Word อัตโนมัติ (Mega Template)</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white text-primary px-3 py-2 rounded-pill fs-6 shadow-sm">
                            <i class="fas fa-school me-1"></i> โรงเรียนบ้านโคกวิทยา
                        </span>
                        <a href="asset.php" class="btn btn-light rounded-pill px-3 py-2 shadow-sm text-primary fw-bold" style="font-size: 0.9rem;">
                            <i class="fas fa-arrow-left me-1"></i> กลับหน้าทะเบียน
                        </a>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4">
                    <form action="generate_annual_report.php" method="POST">
                        
                        <div class="section-title mt-0">
                            <i class="fas fa-sliders-h"></i> 1. ตั้งค่าพื้นฐานของรายงาน
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-md-4 mb-4">
                                <label class="form-label">ปี พ.ศ. (แสดงในรายงาน)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="number" name="year" class="form-control border-start-0 ps-0" value="2569" required>
                                </div>
                            </div>
                            <div class="col-md-8 mb-4">
                                <label class="form-label">เลือกรูปแบบรายงานที่ต้องการสร้าง</label>
                                <div class="row g-3">
                                    <div class="col-sm-6 radio-card">
                                        <input type="radio" name="report_type" id="typeNormal" value="normal" checked>
                                        <label class="type-normal text-center h-100" for="typeNormal">
                                            <i class="fas fa-check-circle radio-icon text-success"></i>
                                            <span class="fw-bold text-success d-block mb-1">แบบที่ 1</span>
                                            <small class="text-muted">กรณีไม่มีพัสดุชำรุด เสื่อมสภาพ</small>
                                        </label>
                                    </div>
                                    <div class="col-sm-6 radio-card">
                                        <input type="radio" name="report_type" id="typeDefective" value="defective">
                                        <label class="type-defect text-center h-100" for="typeDefective">
                                            <i class="fas fa-exclamation-triangle radio-icon text-danger"></i>
                                            <span class="fw-bold text-danger d-block mb-1">แบบที่ 2</span>
                                            <small class="text-muted">กรณีมีพัสดุชำรุด และต้องจำหน่าย</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

<div class="section-title">
    <i class="fas fa-calendar-check"></i> 2. ข้อมูลเลขที่และไทม์ไลน์วันที่
</div>
<div class="row bg-light rounded-3 p-3 mb-3 mx-0 border border-light-subtle shadow-sm">
    <div class="col-md-3 mb-3">
        <label class="form-label text-muted">เลขที่บันทึก / คำสั่ง</label>
        <div class="input-group">
            <input type="text" name="doc_no" class="form-control" placeholder="ตส" value="ตส">
            <input type="text" name="order_no" class="form-control" placeholder="99/2569" value="99/2569">
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label text-primary fw-bold">1. วันที่ตั้งกรรมการ</label>
        <input type="text" name="start_date" class="form-control border-primary-subtle" placeholder="เช่น 2 ตุลาคม 2569" value="2 ตุลาคม 2569" required>
        <small class="text-muted" style="font-size: 0.75rem;">สำหรับหน้า 1 และ 2</small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label text-success fw-bold">2. วันที่รายงานผล</label>
        <input type="text" name="report_date" class="form-control border-success-subtle" placeholder="เช่น 4 พฤศจิกายน 2569" required>
        <small class="text-muted" style="font-size: 0.75rem;">สำหรับหน้า 3 (ภายใน 30 วัน)</small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label text-danger fw-bold">3. วันที่นำส่งเขตฯ</label>
        <input type="text" name="send_date" class="form-control border-danger-subtle" placeholder="เช่น 15 พฤศจิกายน 2569" required>
        <small class="text-muted" style="font-size: 0.75rem;">สำหรับหน้า 5 (หนังสือนำส่ง)</small>
    </div>
</div>

                        <div class="section-title">
                            <i class="fas fa-user-tie"></i> 3. ผู้บริหารและเจ้าหน้าที่พัสดุ
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-primary">ผู้อำนวยการโรงเรียน</label>
                                <input type="text" name="director_name" class="form-control border-primary-subtle" value="นายตฤณ ทีงาม" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">หัวหน้าเจ้าหน้าที่</label>
                                <input type="text" name="head_staff_name" class="form-control" value="นายสุชาติ คู่แก้ว">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เจ้าหน้าที่</label>
                                <input type="text" name="staff_name" class="form-control" value="นางสาวณัฐนรี พื้นผา">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="fas fa-users-cog"></i> 4. คณะกรรมการตรวจสอบพัสดุ
                        </div>
                        
                        <div class="committee-wrapper bg-light rounded-3 p-3 border border-light-subtle">
                            <div class="row committee-box mx-0">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label text-primary">1. ประธานกรรมการ (ชื่อ-สกุล)</label>
                                    <select name="comm1_name" class="form-select">
                                        <option value="นายสุริยะ พรมตวง" selected>นายสุริยะ พรมตวง</option>
                                        <option value="นางอำนวย แก้วสง่า">นางอำนวย แก้วสง่า</option>
                                        <option value="น.ส.รัตน์ดา เนียมมูล">น.ส.รัตน์ดา เนียมมูล</option>
                                        <option value="นางดอกจาน แก้วพิกุล">นางดอกจาน แก้วพิกุล</option>
                                        <option value="น.ส.พิติยา ลาโสพันธ์">น.ส.พิติยา ลาโสพันธ์</option>
                                        <option value="น.ส.รัญญภัสร์ จิตธนาชัยพงศ์">น.ส.รัญญภัสร์ จิตธนาชัยพงศ์</option>
                                        <option value="น.ส.ศิรินันท์ พันธ์ทอง">น.ส.ศิรินันท์ พันธ์ทอง</option>
                                        <option value="น.ส.เนตรศิริ ชินชัย">น.ส.เนตรศิริ ชินชัย</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ตำแหน่ง/วิทยฐานะ</label>
                                    <select name="comm1_pos" class="form-select">
                                        <option value="ครู คศ.1">ครู คศ.1</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการ">ครู วิทยฐานะ ครูชำนาญการ</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการพิเศษ" selected>ครู วิทยฐานะ ครูชำนาญการพิเศษ</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row committee-box mx-0">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label">2. กรรมการ (ชื่อ-สกุล)</label>
                                    <select name="comm2_name" class="form-select">
                                        <option value="นายสุริยะ พรมตวง">นายสุริยะ พรมตวง</option>
                                        <option value="นางอำนวย แก้วสง่า" selected>นางอำนวย แก้วสง่า</option>
                                        <option value="น.ส.รัตน์ดา เนียมมูล">น.ส.รัตน์ดา เนียมมูล</option>
                                        <option value="นางดอกจาน แก้วพิกุล">นางดอกจาน แก้วพิกุล</option>
                                        <option value="น.ส.พิติยา ลาโสพันธ์">น.ส.พิติยา ลาโสพันธ์</option>
                                        <option value="น.ส.รัญญภัสร์ จิตธนาชัยพงศ์">น.ส.รัญญภัสร์ จิตธนาชัยพงศ์</option>
                                        <option value="น.ส.ศิรินันท์ พันธ์ทอง">น.ส.ศิรินันท์ พันธ์ทอง</option>
                                        <option value="น.ส.เนตรศิริ ชินชัย">น.ส.เนตรศิริ ชินชัย</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ตำแหน่ง/วิทยฐานะ</label>
                                    <select name="comm2_pos" class="form-select">
                                        <option value="ครู คศ.1">ครู คศ.1</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการ">ครู วิทยฐานะ ครูชำนาญการ</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการพิเศษ" selected>ครู วิทยฐานะ ครูชำนาญการพิเศษ</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row committee-box mx-0 mb-0">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label">3. กรรมการ (ชื่อ-สกุล)</label>
                                    <select name="comm3_name" class="form-select">
                                        <option value="นายสุริยะ พรมตวง">นายสุริยะ พรมตวง</option>
                                        <option value="นางอำนวย แก้วสง่า">นางอำนวย แก้วสง่า</option>
                                        <option value="น.ส.รัตน์ดา เนียมมูล" selected>น.ส.รัตน์ดา เนียมมูล</option>
                                        <option value="น.ส.พิติยา ลาโสพันธ์">น.ส.พิติยา ลาโสพันธ์</option>
                                        <option value="น.ส.รัญญภัสร์ จิตธนาชัยพงศ์">น.ส.รัญญภัสร์ จิตธนาชัยพงศ์</option>
                                        <option value="น.ส.ศิรินันท์ พันธ์ทอง">น.ส.ศิรินันท์ พันธ์ทอง</option>
                                        <option value="น.ส.เนตรศิริ ชินชัย">น.ส.เนตรศิริ ชินชัย</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ตำแหน่ง/วิทยฐานะ</label>
                                    <select name="comm3_pos" class="form-select">
                                        <option value="ครู คศ.1">ครู คศ.1</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการ" selected>ครู วิทยฐานะ ครูชำนาญการ</option>
                                        <option value="ครู วิทยฐานะ ครูชำนาญการพิเศษ">ครู วิทยฐานะ ครูชำนาญการพิเศษ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-5 pt-3 border-top border-light-subtle">
                            <button type="submit" class="btn btn-primary btn-submit text-white">
                                <i class="fas fa-file-word me-2 fs-5 align-middle"></i> 
                                <span class="align-middle">สร้างและดาวน์โหลดรายงาน (.docx)</span>
                            </button>
                            <p class="text-muted mt-3 small"><i class="fas fa-info-circle"></i> ระบบจะรวมไฟล์เอกสารทุกหน้าให้อัตโนมัติในคลิกเดียว</p>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>