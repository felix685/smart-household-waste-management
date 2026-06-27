-- ============================================================
-- Smart Household Waste Management System
-- schema.sql — Drop, create, seed
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS Collection_Recyclable;
DROP TABLE IF EXISTS Collection_Record;
DROP TABLE IF EXISTS Recyclable_Type;
DROP TABLE IF EXISTS QA_Reply;
DROP TABLE IF EXISTS QA;
DROP TABLE IF EXISTS Transportation;
DROP TABLE IF EXISTS WasteBin;
DROP TABLE IF EXISTS Household;
DROP TABLE IF EXISTS Admin;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Admin
-- ------------------------------------------------------------
CREATE TABLE Admin (
    admin_id      INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Household  (the "user")
-- ------------------------------------------------------------
CREATE TABLE Household (
    household_id INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20)  NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- WasteBin
-- ------------------------------------------------------------
CREATE TABLE WasteBin (
    wastebin_id INT AUTO_INCREMENT PRIMARY KEY,
    location    VARCHAR(255) NOT NULL,
    type        ENUM('general','recycling','organic','hazardous') NOT NULL,
    capacity    DECIMAL(8,2) NOT NULL COMMENT 'capacity in litres'
);

-- ------------------------------------------------------------
-- Transportation  (composite PK)
-- ------------------------------------------------------------
CREATE TABLE Transportation (
    household_id  INT NOT NULL,
    wastebin_id   INT NOT NULL,
    status        ENUM('scheduled','in_transit','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    schedule_time DATETIME NOT NULL,
    PRIMARY KEY (household_id, wastebin_id, schedule_time),
    FOREIGN KEY (household_id) REFERENCES Household(household_id) ON DELETE CASCADE,
    FOREIGN KEY (wastebin_id)  REFERENCES WasteBin(wastebin_id)   ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Recyclable_Type
-- ------------------------------------------------------------
CREATE TABLE Recyclable_Type (
    recyclable_type_id INT AUTO_INCREMENT PRIMARY KEY,
    category           VARCHAR(100) NOT NULL,
    is_toxic           TINYINT(1)   NOT NULL DEFAULT 0
);

-- ------------------------------------------------------------
-- Collection_Record
-- ------------------------------------------------------------
CREATE TABLE Collection_Record (
    collection_id INT AUTO_INCREMENT PRIMARY KEY,
    household_id  INT            NOT NULL,
    date          DATE           NOT NULL,
    amount        DECIMAL(10,2)  NOT NULL COMMENT 'total weight in kg',
    FOREIGN KEY (household_id) REFERENCES Household(household_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Collection_Recyclable  (junction: record ↔ type)
-- ------------------------------------------------------------
CREATE TABLE Collection_Recyclable (
    collection_id      INT           NOT NULL,
    recyclable_type_id INT           NOT NULL,
    weight             DECIMAL(10,2) NOT NULL COMMENT 'weight in kg for this type',
    PRIMARY KEY (collection_id, recyclable_type_id),
    FOREIGN KEY (collection_id)      REFERENCES Collection_Record(collection_id) ON DELETE CASCADE,
    FOREIGN KEY (recyclable_type_id) REFERENCES Recyclable_Type(recyclable_type_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- QA
-- ------------------------------------------------------------
CREATE TABLE QA (
    qa_id        INT AUTO_INCREMENT PRIMARY KEY,
    household_id INT          NOT NULL,
    date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    content      TEXT         NOT NULL,
    status       ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    FOREIGN KEY (household_id) REFERENCES Household(household_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- QA_Reply  (admin replies)
-- ------------------------------------------------------------
CREATE TABLE QA_Reply (
    reply_id   INT AUTO_INCREMENT PRIMARY KEY,
    qa_id      INT      NOT NULL,
    admin_id   INT      NOT NULL,
    content    TEXT     NOT NULL,
    replied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (qa_id)    REFERENCES QA(qa_id)       ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admins  (password: admin123)
INSERT INTO Admin (username, email, password_hash) VALUES
('admin',       'admin@wastesystem.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('superadmin',  'super@wastesystem.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Households  (password: house123)
INSERT INTO Household (name, address, phone_number, email, password_hash) VALUES
('Chen Family',    '12 Maple St, Taipei',        '0912-345-678', 'chen@mail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Lin Family',     '45 Oak Ave, Taichung',        '0923-456-789', 'lin@mail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Wang Family',    '78 Pine Rd, Kaohsiung',       '0934-567-890', 'wang@mail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Huang Family',   '23 Cedar Blvd, Tainan',       '0945-678-901', 'huang@mail.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Wu Family',      '56 Bamboo Lane, Hsinchu',     '0956-789-012', 'wu@mail.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- WasteBins
INSERT INTO WasteBin (location, type, capacity) VALUES
('Taipei District A, Block 1',   'general',   240.00),
('Taipei District A, Block 2',   'recycling', 360.00),
('Taichung Zone B, Sector 1',    'organic',   180.00),
('Taichung Zone B, Sector 2',    'general',   240.00),
('Kaohsiung Area C, Point 1',    'hazardous', 120.00),
('Tainan Region D, Unit 1',      'recycling', 360.00),
('Hsinchu Park E, Station 1',    'general',   240.00),
('Hsinchu Park E, Station 2',    'organic',   180.00);

-- Transportation
INSERT INTO Transportation (household_id, wastebin_id, status, schedule_time) VALUES
(1, 1, 'completed',  '2026-06-01 08:00:00'),
(1, 2, 'completed',  '2026-06-08 08:00:00'),
(2, 3, 'completed',  '2026-06-03 09:00:00'),
(2, 4, 'in_transit', '2026-06-27 09:00:00'),
(3, 5, 'scheduled',  '2026-06-28 10:00:00'),
(4, 6, 'completed',  '2026-06-10 07:30:00'),
(5, 7, 'scheduled',  '2026-06-29 08:00:00'),
(5, 8, 'cancelled',  '2026-06-15 08:00:00');

-- Recyclable Types
INSERT INTO Recyclable_Type (category, is_toxic) VALUES
('Paper',           0),
('Plastic',         0),
('Glass',           0),
('Metal',           0),
('Electronic Waste',1),
('Battery',         1),
('Organic Compost', 0),
('Textile',         0);

-- Collection Records
INSERT INTO Collection_Record (household_id, date, amount) VALUES
(1, '2026-06-01', 12.50),
(1, '2026-06-08', 10.20),
(2, '2026-06-03', 8.75),
(2, '2026-06-10', 15.00),
(3, '2026-06-05', 6.30),
(4, '2026-06-10', 9.80),
(5, '2026-06-12', 11.40),
(1, '2026-06-15', 13.60),
(2, '2026-06-17', 7.90),
(3, '2026-06-20', 14.20);

-- Collection_Recyclable (junction)
INSERT INTO Collection_Recyclable (collection_id, recyclable_type_id, weight) VALUES
(1, 1, 4.00),(1, 2, 3.50),(1, 3, 2.00),(1, 4, 3.00),
(2, 1, 3.20),(2, 2, 4.00),(2, 7, 3.00),
(3, 2, 3.00),(3, 5, 1.75),(3, 6, 2.00),(3, 4, 2.00),
(4, 1, 5.00),(4, 2, 4.00),(4, 3, 3.00),(4, 7, 3.00),
(5, 1, 2.30),(5, 3, 2.00),(5, 8, 2.00),
(6, 2, 3.80),(6, 4, 3.00),(6, 6, 1.00),(6, 5, 2.00),
(7, 1, 4.40),(7, 2, 3.00),(7, 7, 4.00),
(8, 1, 5.00),(8, 2, 4.00),(8, 3, 2.60),(8, 4, 2.00),
(9, 2, 3.00),(9, 5, 2.00),(9, 6, 1.90),(9, 8, 1.00),
(10,1, 5.20),(10,2, 4.00),(10,3, 2.00),(10,7, 3.00);

-- QA
INSERT INTO QA (household_id, date, content, status) VALUES
(1, '2026-06-02 10:00:00', 'What items are considered hazardous waste?',           'answered'),
(2, '2026-06-04 14:00:00', 'Can I schedule a pickup for large furniture?',         'answered'),
(3, '2026-06-06 09:30:00', 'The wastebin at Kaohsiung Area C is overflowing.',     'open'),
(4, '2026-06-11 11:00:00', 'How do I separate organic from general waste?',        'answered'),
(5, '2026-06-13 16:00:00', 'Is there a limit on how much I can dispose per month?','open');

-- QA Replies
INSERT INTO QA_Reply (qa_id, admin_id, content, replied_at) VALUES
(1, 1, 'Hazardous waste includes batteries, electronics, chemicals, and fluorescent bulbs. Please use designated hazardous bins.', '2026-06-02 15:00:00'),
(2, 1, 'Yes, large-item pickups can be scheduled through the Transportation module. Please select the nearest bin and choose a date.', '2026-06-05 10:00:00'),
(4, 2, 'Organic waste includes food scraps, garden trimmings, and biodegradable materials. Keep them separate from plastic and paper.', '2026-06-12 09:00:00');
