-- ============================================
-- MediCare HMS — Database Schema & Sample Data
-- ============================================

DROP DATABASE IF EXISTS hospital_db;
CREATE DATABASE hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE hospital_db;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    profile_pic VARCHAR(255) NULL,
    role       ENUM('admin','patient','doctor') NOT NULL DEFAULT 'patient',
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- Table: patients
-- ----------------------------
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL,
    birthdate  DATE          NULL,
    gender     ENUM('Male','Female','Other') NULL,
    address    VARCHAR(255)  NULL,
    contact    VARCHAR(20)   NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------
-- Table: departments
-- ----------------------------
CREATE TABLE departments (
    dept_id     INT AUTO_INCREMENT PRIMARY KEY,
    dept_name   VARCHAR(100)  NOT NULL,
    description TEXT          NULL
) ENGINE=InnoDB;

-- ----------------------------
-- Table: doctors
-- ----------------------------
CREATE TABLE doctors (
    doctor_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    dept_id        INT           NOT NULL,
    specialization VARCHAR(150)  NOT NULL,
    schedule       VARCHAR(255)  NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------
-- Table: appointments
-- ----------------------------
CREATE TABLE appointments (
    appt_id   INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT          NOT NULL,
    doctor_id  INT          NOT NULL,
    appt_date  DATETIME     NOT NULL,
    status     ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    notes      TEXT         NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------
-- Table: medical_records
-- ----------------------------
CREATE TABLE medical_records (
    record_id    INT AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT          NOT NULL,
    doctor_id    INT          NOT NULL,
    diagnosis    TEXT         NOT NULL,
    prescription TEXT         NULL,
    record_date  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Sample data
-- ============================================

-- 2 Admins (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin',           'admin',               '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'admin'),
('James Rodriguez', 'james@medicare.com',  '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'admin');

-- Departments
INSERT INTO departments (dept_name, description) VALUES
('Cardiology',      'Heart and cardiovascular care, including chest pain, hypertension, ECG review, and follow-up for chronic cardiac conditions'),
('Neurology',       'Brain, spinal cord, nerve, and movement disorder care, including headaches, seizures, stroke follow-up, and neuropathy concerns'),
('Pediatrics',      'Primary and preventive care for infants, children, and adolescents, including wellness visits, vaccines, fever, asthma, and growth concerns'),
('Orthopedics',     'Bone, joint, muscle, and sports injury care, including fractures, arthritis, back pain, sprains, and mobility concerns'),
('Dermatology',     'Skin, hair, and nail care, including rashes, acne, eczema, suspicious lesions, allergies, and minor skin procedures'),
('Obstetrics and Gynecology', 'Women''s health services, including prenatal care, menstrual concerns, contraception counseling, pelvic pain, and routine exams'),
('Internal Medicine', 'Adult primary care and chronic disease management, including diabetes, hypertension, infections, preventive screening, and medication review'),
('Emergency Medicine', 'Urgent assessment and stabilization for acute illness or injury, including severe pain, breathing difficulty, trauma, and sudden symptoms'),
('Ophthalmology',   'Eye and vision care, including blurred vision, eye pain, infections, glaucoma screening, cataract evaluation, and diabetic eye concerns'),
('ENT',             'Ear, nose, and throat care, including sinus problems, sore throat, hearing concerns, ear infections, allergies, and voice issues'),
('Psychiatry',      'Mental health assessment and treatment, including anxiety, depression, sleep problems, medication follow-up, and stress-related concerns'),
('Radiology',       'Diagnostic imaging services and interpretation, including X-ray, ultrasound, CT, MRI coordination, and image-guided consultation');

-- Doctors (also users, password: doctor123)
INSERT INTO users (name, email, password, role) VALUES
('Dr. Emily Park',    'emily@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Michael Torres', 'michael@medicare.com','$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Aisha Patel',   'aisha@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Noah Bennett',  'noah@medicare.com',    '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Sofia Ramos',   'sofia@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Liam Chen',     'liam@medicare.com',    '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Maya Singh',    'maya@medicare.com',    '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Daniel Brooks', 'daniel@medicare.com',  '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Hannah Lee',    'hannah@medicare.com',  '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Omar Khalid',   'omar@medicare.com',    '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Grace Wilson',  'grace@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Ethan Rivera',  'ethan@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor'),
('Dr. Priya Nair',    'priya@medicare.com',   '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'doctor');

INSERT INTO doctors (user_id, dept_id, specialization, schedule) VALUES
(3, 1, 'Interventional Cardiology',  'Mon,Tue,Wed,Thu,Fri 09:00-17:00'),
(4, 1, 'Electrophysiology',          'Mon,Wed,Fri 08:00-15:00'),
(5, 2, 'Clinical Neurology',         'Tue,Thu 10:00-18:00'),
(6, 3, 'Pediatrics',                 'Mon,Tue,Wed,Thu,Fri 08:00-16:00'),
(7, 4, 'Orthopedics',                'Mon,Wed,Fri 09:00-17:00'),
(8, 5, 'Dermatology',                'Tue,Thu,Sat 08:00-14:00'),
(9, 6, 'Obstetrics and Gynecology',  'Mon,Tue,Wed,Thu,Fri 09:00-17:00'),
(10, 7, 'Internal Medicine',         'Mon,Tue,Wed,Thu,Fri 08:00-16:00'),
(11, 8, 'Emergency Medicine',        'Mon,Tue,Wed,Thu,Fri 10:00-18:00'),
(12, 9, 'Ophthalmology',             'Mon,Wed,Fri 09:00-13:00'),
(13, 10, 'ENT',                      'Tue,Thu 10:00-18:00'),
(14, 11, 'Psychiatry',               'Mon,Wed,Fri 08:00-15:00'),
(15, 12, 'Radiology',                'Tue,Thu,Sat 08:00-14:00');

-- 5 Patients (password: patient123)
INSERT INTO users (name, email, password, role) VALUES
('Alice Johnson',   'alice@email.com',     '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'patient'),
('Bob Williams',    'bob@email.com',       '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'patient'),
('Clara Reyes',     'clara@email.com',     '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'patient'),
('David Kim',       'david@email.com',     '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'patient'),
('Eva Martinez',    'eva@email.com',       '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm', 'patient');

INSERT INTO patients (user_id, birthdate, gender, address, contact) VALUES
(16, '1990-03-15', 'Female', '123 Oak Street, Springfield',     '555-0101'),
(17, '1985-07-22', 'Male',   '456 Maple Ave, Riverside',        '555-0102'),
(18, '1998-11-03', 'Female', '789 Pine Road, Lakewood',         '555-0103'),
(19, '1976-01-30', 'Male',   '321 Elm Boulevard, Fairview',     '555-0104'),
(20, '2001-09-12', 'Female', '654 Cedar Lane, Greenville',      '555-0105');

-- 15 Appointments (dynamic dates spanning relative to current week)
INSERT INTO appointments (patient_id, doctor_id, appt_date, status, notes) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL -1 DAY) + INTERVAL 9 HOUR, 'Completed',  'Routine cardiac checkup'),
(2, 1, CURDATE() + INTERVAL 10 HOUR, 'Pending',    'Follow-up on blood pressure medication'),
(3, 2, CURDATE() + INTERVAL 11 HOUR, 'Confirmed',  'ECG interpretation review'),
(1, 3, CURDATE() + INTERVAL 14 HOUR, 'Pending',    'Migraine assessment'),
(4, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 9 HOUR, 'Confirmed',  'Post-surgery follow-up'),
(5, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 10 HOUR, 'Pending',    'Heart rhythm consultation'),
(2, 3, DATE_ADD(CURDATE(), INTERVAL -2 DAY) + INTERVAL 15 HOUR, 'Completed',  'Neurological exam results'),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 9 HOUR, 'Cancelled',  'Patient requested reschedule'),
(4, 3, DATE_ADD(CURDATE(), INTERVAL -3 DAY) + INTERVAL 11 HOUR, 'Completed',  'EEG review and diagnosis'),
(5, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 14 HOUR, 'Pending',    'Initial cardiac consultation'),
(1, 2, CURDATE() + INTERVAL 15 HOUR, 'Confirmed', 'Palpitations check'),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 14 HOUR, 'Pending', 'Stent follow-up'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL -1 DAY) + INTERVAL 10 HOUR, 'Completed', 'Sciatica evaluation'),
(4, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 11 HOUR, 'Confirmed', 'Echocardiogram review'),
(5, 3, CURDATE() + INTERVAL 16 HOUR, 'Pending', 'Numbness in extremities');

-- Medical records
INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, record_date) VALUES
(1, 1, 'Mild hypertension detected. Blood pressure 145/92.',                    'Lisinopril 10mg daily. Low sodium diet recommended.',                         DATE_ADD(CURDATE(), INTERVAL -14 DAY) + INTERVAL 10 HOUR),
(2, 3, 'Tension-type headache with mild cervical strain.',                       'Ibuprofen 400mg as needed. Physical therapy 2x/week.',                        DATE_ADD(CURDATE(), INTERVAL -10 DAY) + INTERVAL 14 HOUR),
(4, 3, 'Benign positional vertigo confirmed via Dix-Hallpike test.',            'Epley maneuver performed. Meclizine 25mg for acute episodes.',                DATE_ADD(CURDATE(), INTERVAL -5 DAY) + INTERVAL 11 HOUR),
(5, 1, 'Routine checkup. Patient reported occasional shortness of breath.',     'Ordered ECG and prescribed Albuterol inhaler as needed.',                     DATE_ADD(CURDATE(), INTERVAL -30 DAY) + INTERVAL 9 HOUR),
(5, 2, 'ECG results normal. Shortness of breath likely allergy-induced.',       'Cetirizine 10mg daily. Follow up in 3 months.',                               DATE_ADD(CURDATE(), INTERVAL -20 DAY) + INTERVAL 10 HOUR),
(3, 1, 'Elevated cholesterol levels found in recent blood work.',               'Atorvastatin 20mg daily. Dietary changes advised.',                           DATE_ADD(CURDATE(), INTERVAL -8 DAY) + INTERVAL 13 HOUR);
