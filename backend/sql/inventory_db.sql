-- =============================================================
--  inventory_db.sql
--  ระบบบริหารพัสดุโรงเรียน — สร้างฐานข้อมูลใหม่แยกจาก school_db
--  Created: 2026-02-21
-- =============================================================

CREATE DATABASE IF NOT EXISTS `inventory_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `inventory_db`;

-- -------------------------------------------------------------
-- 1. asset_categories — 17 หมวดหมู่มาตรฐานราชการ
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `code`        VARCHAR(2)     NOT NULL COMMENT 'รหัสหมวด 01-17',
  `name`        VARCHAR(100)   NOT NULL,
  `useful_life` TINYINT UNSIGNED DEFAULT NULL COMMENT 'อายุการใช้งาน (ปี)',
  `dep_rate`    DECIMAL(5,2)   DEFAULT NULL COMMENT 'อัตราค่าเสื่อม %',
  `note`        TEXT           DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `asset_categories` (`code`,`name`,`useful_life`,`dep_rate`,`note`) VALUES
('01','ครุภัณฑ์สำนักงาน',        5,  20.00, 'โต๊ะ, เก้าอี้, ตู้เก็บของ ฯลฯ'),
('02','ครุภัณฑ์การศึกษา',        5,  20.00, 'กระดาน, ชั้นวางหนังสือ ฯลฯ'),
('03','ครุภัณฑ์อาวุธ',           10, 10.00, 'อาวุธปืน, กระบอง ฯลฯ'),
('04','ครุภัณฑ์คอมพิวเตอร์',     3,  33.33, 'คอมพิวเตอร์, แท็บเล็ต, printer ฯลฯ'),
('05','ครุภัณฑ์ยานพาหนะ',        10, 10.00, 'รถยนต์, มอเตอร์ไซค์ ฯลฯ'),
('06','ครุภัณฑ์ก่อสร้าง',        5,  20.00, 'เครื่องมือก่อสร้าง ฯลฯ'),
('07','ครุภัณฑ์ไฟฟ้าและวิทยุ',   5,  20.00, 'แอร์, พัดลม, โทรทัศน์ ฯลฯ'),
('08','ครุภัณฑ์โฆษณาและเผยแพร่', 5,  20.00, 'โปรเจกเตอร์, ลำโพง ฯลฯ'),
('09','ครุภัณฑ์วิทยาศาสตร์',     10, 10.00, 'อุปกรณ์ Lab วิทยาศาสตร์ ฯลฯ'),
('10','ครุภัณฑ์การแพทย์',        10, 10.00, 'เครื่องวัดความดัน, ชั่งน้ำหนัก ฯลฯ'),
('11','ครุภัณฑ์งานบ้านงานครัว',  5,  20.00, 'ตู้เย็น, เตาแก๊ส ฯลฯ'),
('12','ครุภัณฑ์กีฬา',            5,  20.00, 'อุปกรณ์กีฬาต่าง ๆ'),
('13','ครุภัณฑ์ดนตรี/นาฏศิลป์', 10, 10.00, 'เครื่องดนตรี, ชุดแสดง ฯลฯ'),
('14','ครุภัณฑ์สนาม',            10, 10.00, 'เสาธง, ราวบันได ฯลฯ'),
('15','สิ่งปลูกสร้าง (ถาวร)',    20, 5.00,  'อาคาร, ห้องน้ำ ฯลฯ'),
('16','สิ่งปลูกสร้าง (ชั่วคราว)',5,  20.00, 'โรงจอดรถชั่วคราว ฯลฯ'),
('17','ครุภัณฑ์ต่ำกว่าเกณฑ์',   NULL, NULL, 'ราคา < 10,000 บาท — ลงทะเบียนแต่ไม่คิดค่าเสื่อม');

-- -------------------------------------------------------------
-- 2. assets — ข้อมูลครุภัณฑ์หลัก
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assets` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(30)     NOT NULL UNIQUE COMMENT 'เลขทะเบียน เช่น คว04/01/2569',
  `name`            VARCHAR(200)    NOT NULL,
  `category_code`   VARCHAR(2)      DEFAULT NULL,
  `model`           VARCHAR(100)    DEFAULT NULL COMMENT 'รุ่น/แบบ',
  `location`        VARCHAR(100)    DEFAULT NULL COMMENT 'สถานที่ตั้ง',
  `custodian`       VARCHAR(100)    DEFAULT NULL COMMENT 'ผู้รับผิดชอบ',
  `receive_date`    DATE            DEFAULT NULL,
  `doc_ref`         VARCHAR(100)    DEFAULT NULL COMMENT 'เลขที่เอกสาร/ใบส่งของ',
  `budget_type`     ENUM('budget','off_budget','donation','other') DEFAULT 'budget',
  `proc_method`     ENUM('specific','select','ebidding','price_check','donation','other') DEFAULT 'specific',
  `vendor_name`     VARCHAR(150)    DEFAULT NULL,
  `vendor_address`  TEXT            DEFAULT NULL,
  `vendor_tel`      VARCHAR(30)     DEFAULT NULL,
  `cost`            DECIMAL(12,2)   DEFAULT 0.00,
  `useful_life`     TINYINT UNSIGNED DEFAULT NULL,
  `dep_rate`        DECIMAL(5,2)    DEFAULT NULL,
  `image_path`      VARCHAR(255)    DEFAULT NULL COMMENT 'path ใน uploads/assets/',
  `status`          ENUM('available','inuse','repair','writeoff') NOT NULL DEFAULT 'available',
  `batch_qty`       TINYINT UNSIGNED DEFAULT 1,
  `note`            TEXT            DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_code`),
  KEY `idx_status`   (`status`),
  KEY `idx_code`     (`code`),
  CONSTRAINT `fk_asset_category`
    FOREIGN KEY (`category_code`) REFERENCES `asset_categories`(`code`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. depreciation_log — บันทึกค่าเสื่อมราคารายปี (ทะเบียนคุม)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `depreciation_log` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `asset_id`         INT UNSIGNED   NOT NULL,
  `log_date`         DATE           NOT NULL COMMENT 'วันสิ้นปีงบประมาณ',
  `year_no`          TINYINT        DEFAULT NULL COMMENT 'ปีที่ (1,2,3,...)',
  `months`           TINYINT        DEFAULT 12,
  `dep_amount`       DECIMAL(12,2)  DEFAULT 0.00 COMMENT 'ค่าเสื่อมปีนั้น',
  `acc_depreciation` DECIMAL(12,2)  DEFAULT 0.00 COMMENT 'ค่าเสื่อมสะสม',
  `net_value`        DECIMAL(12,2)  DEFAULT 0.00 COMMENT 'มูลค่าสุทธิ',
  `doc_ref`          VARCHAR(50)    DEFAULT NULL,
  `note`             VARCHAR(200)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dep_asset` (`asset_id`),
  CONSTRAINT `fk_dep_asset`
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. borrow_records — การยืม-คืนครุภัณฑ์
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `borrow_records` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `asset_id`           INT UNSIGNED  NOT NULL,
  `borrower_name`      VARCHAR(100)  NOT NULL,
  `borrower_dept`      VARCHAR(100)  DEFAULT NULL COMMENT 'หน่วยงาน/ห้องเรียน',
  `borrower_tel`       VARCHAR(30)   DEFAULT NULL,
  `borrow_date`        DATE          NOT NULL,
  `return_due_date`    DATE          NOT NULL,
  `actual_return_date` DATE          DEFAULT NULL COMMENT 'NULL = ยังไม่คืน',
  `purpose`            TEXT          DEFAULT NULL,
  `condition_on_return` ENUM('normal','damaged','lost') DEFAULT NULL,
  `damage_detail`      TEXT          DEFAULT NULL,
  `status`             ENUM('borrowing','returned','overdue') NOT NULL DEFAULT 'borrowing',
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_borrow_asset`  (`asset_id`),
  KEY `idx_borrow_status` (`status`),
  CONSTRAINT `fk_borrow_asset`
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. repair_records — ประวัติซ่อมบำรุง
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `repair_records` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `asset_id`     INT UNSIGNED  NOT NULL,
  `send_date`    DATE          NOT NULL,
  `problem_desc` TEXT          NOT NULL,
  `repair_shop`  VARCHAR(150)  DEFAULT NULL,
  `repair_cost`  DECIMAL(10,2) DEFAULT 0.00,
  `return_date`  DATE          DEFAULT NULL COMMENT 'NULL = ยังซ่อมอยู่',
  `status`       ENUM('repairing','done','cannot_fix') NOT NULL DEFAULT 'repairing',
  `note`         TEXT          DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_repair_asset` (`asset_id`),
  CONSTRAINT `fk_repair_asset`
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. procurement_records — เอกสารจัดซื้อจัดจ้าง
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `procurement_records` (
  `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `doc_no`             VARCHAR(50)    NOT NULL UNIQUE,
  `doc_year`           VARCHAR(4)     DEFAULT NULL COMMENT 'ปีงบประมาณ พ.ศ.',
  `budget_source`      VARCHAR(150)   DEFAULT NULL,
  `proc_method`        ENUM('specific','select','ebidding','price_check','donation','other') DEFAULT 'specific',
  `committee_members`  TEXT           DEFAULT NULL,
  `approved_by`        VARCHAR(100)   DEFAULT NULL,
  `total_amount`       DECIMAL(14,2)  DEFAULT 0.00,
  `status`             ENUM('draft','approved','complete','cancelled') NOT NULL DEFAULT 'draft',
  `note`               TEXT           DEFAULT NULL,
  `created_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. procurement_items — รายการสินค้าในแต่ละเอกสาร
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `procurement_items` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `procurement_id`   INT UNSIGNED   NOT NULL,
  `item_name`        VARCHAR(200)   NOT NULL,
  `category_code`    VARCHAR(2)     DEFAULT NULL,
  `qty`              INT UNSIGNED   DEFAULT 1,
  `unit`             VARCHAR(30)    DEFAULT NULL,
  `unit_price`       DECIMAL(12,2)  DEFAULT 0.00,
  `total_price`      DECIMAL(14,2)  DEFAULT 0.00,
  `note`             VARCHAR(255)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pitem_proc` (`procurement_id`),
  CONSTRAINT `fk_pitem_proc`
    FOREIGN KEY (`procurement_id`) REFERENCES `procurement_records`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. materials — วัสดุ-สิ้นเปลืองและสินค้าคงคลัง
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `materials` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(30)    NOT NULL UNIQUE,
  `name`         VARCHAR(200)   NOT NULL,
  `unit`         VARCHAR(30)    DEFAULT NULL COMMENT 'หน่วย: กล่อง, แผ่น, หลอด',
  `category`     VARCHAR(100)   DEFAULT NULL,
  `qty_in_stock` INT            DEFAULT 0,
  `min_stock`    INT            DEFAULT 5 COMMENT 'แจ้งเตือนเมื่อต่ำกว่า',
  `unit_cost`    DECIMAL(10,2)  DEFAULT 0.00,
  `note`         TEXT           DEFAULT NULL,
  `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_material_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 9. material_transactions — รับ/จ่ายวัสดุ
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `material_transactions` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `material_id` INT UNSIGNED  NOT NULL,
  `type`        ENUM('in','out') NOT NULL,
  `qty`         INT           NOT NULL,
  `ref_doc`     VARCHAR(100)  DEFAULT NULL,
  `requester`   VARCHAR(100)  DEFAULT NULL,
  `note`        VARCHAR(255)  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mtx_material` (`material_id`),
  KEY `idx_mtx_type`     (`type`),
  CONSTRAINT `fk_mtx_material`
    FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 10. system_users — ผู้ใช้งานระบบ
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_users` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)   NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)  NOT NULL,
  `full_name`     VARCHAR(100)  NOT NULL,
  `role`          ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`    TIMESTAMP     DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: default admin (password = admin1234)
INSERT INTO `system_users` (`username`,`password_hash`,`full_name`,`role`) VALUES
('admin', '$2y$12$placeholder_change_this_hash', 'ผู้ดูแลระบบ', 'admin');

-- =============================================================
--  END OF inventory_db.sql
-- =============================================================
