USE hospital_db;

SET @doctor_password = '$2y$10$HDa5o1gOD50No1JsvPvN4./1IYE3ojjd7a59aK99hsjqOFmHv0rEm';

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Noah Bennett', 'noah@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'noah@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Pediatrics', 'Mon,Tue,Wed,Thu,Fri 08:00-16:00'
FROM users u JOIN departments d ON d.dept_name = 'Pediatrics'
WHERE u.email = 'noah@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Sofia Ramos', 'sofia@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'sofia@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Orthopedics', 'Mon,Wed,Fri 09:00-17:00'
FROM users u JOIN departments d ON d.dept_name = 'Orthopedics'
WHERE u.email = 'sofia@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Liam Chen', 'liam@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'liam@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Dermatology', 'Tue,Thu,Sat 08:00-14:00'
FROM users u JOIN departments d ON d.dept_name = 'Dermatology'
WHERE u.email = 'liam@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Maya Singh', 'maya@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'maya@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Obstetrics and Gynecology', 'Mon,Tue,Wed,Thu,Fri 09:00-17:00'
FROM users u JOIN departments d ON d.dept_name = 'Obstetrics and Gynecology'
WHERE u.email = 'maya@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Daniel Brooks', 'daniel@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'daniel@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Internal Medicine', 'Mon,Tue,Wed,Thu,Fri 08:00-16:00'
FROM users u JOIN departments d ON d.dept_name = 'Internal Medicine'
WHERE u.email = 'daniel@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Hannah Lee', 'hannah@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'hannah@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Emergency Medicine', 'Mon,Tue,Wed,Thu,Fri 10:00-18:00'
FROM users u JOIN departments d ON d.dept_name = 'Emergency Medicine'
WHERE u.email = 'hannah@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Omar Khalid', 'omar@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'omar@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Ophthalmology', 'Mon,Wed,Fri 09:00-13:00'
FROM users u JOIN departments d ON d.dept_name = 'Ophthalmology'
WHERE u.email = 'omar@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Grace Wilson', 'grace@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'grace@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'ENT', 'Tue,Thu 10:00-18:00'
FROM users u JOIN departments d ON d.dept_name = 'ENT'
WHERE u.email = 'grace@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Ethan Rivera', 'ethan@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ethan@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Psychiatry', 'Mon,Wed,Fri 08:00-15:00'
FROM users u JOIN departments d ON d.dept_name = 'Psychiatry'
WHERE u.email = 'ethan@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);

INSERT INTO users (name, email, password, role)
SELECT 'Dr. Priya Nair', 'priya@medicare.com', @doctor_password, 'doctor'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'priya@medicare.com');

INSERT INTO doctors (user_id, dept_id, specialization, schedule)
SELECT u.user_id, d.dept_id, 'Radiology', 'Tue,Thu,Sat 08:00-14:00'
FROM users u JOIN departments d ON d.dept_name = 'Radiology'
WHERE u.email = 'priya@medicare.com'
  AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.user_id);
