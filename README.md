# 🏥 Hospital Appointment & Records Management System

A web-based application built with **PHP**, **MySQL**, and **XAMPP** for managing hospital appointments and patient medical records. Designed as a database subject project showcasing real-world SQL concepts including JOINs, subqueries, aggregates, and transactions.

---

## 📌 Project Overview

This system has two roles:

- **Admin (Hospital Staff)** — manages doctors, departments, appointments, and generates reports
- **Patient (User)** — registers, books appointments, and views their medical history

---

## 🛠️ Tech Stack

| Layer       | Technology              |
|-------------|-------------------------|
| Backend     | PHP (procedural / OOP)  |
| Database    | MySQL via phpMyAdmin     |
| Local Server| XAMPP (Apache + MySQL)  |
| Frontend    | HTML, CSS (Bootstrap optional) |
| Auth        | PHP Sessions            |

---

## 📁 Folder Structure

```
hospital-system/
├── index.php                  # Login & registration page
├── logout.php
├── config/
│   └── db.php                 # Database connection
├── admin/
│   ├── dashboard.php          # Stats overview
│   ├── appointments.php       # View & manage all appointments
│   ├── doctors.php            # Add/edit/delete doctors
│   ├── departments.php        # Manage departments
│   ├── patients.php           # View all patients
│   └── reports.php            # SQL-heavy reports page
├── patient/
│   ├── dashboard.php          # Patient home
│   ├── book.php               # Book an appointment
│   ├── appointments.php       # View own appointments
│   └── history.php            # Medical records & prescriptions
├── assets/
│   ├── css/
│   └── js/
└── sql/
    └── hospital.sql           # Full database schema + sample data
```

---

## 🗄️ Database Schema

### Tables

**`users`** — stores login credentials for both admins and patients
```sql
CREATE TABLE users (
  user_id     INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) UNIQUE NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin', 'patient') DEFAULT 'patient',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**`patients`** — extended profile linked to a user account
```sql
CREATE TABLE patients (
  patient_id  INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  birthdate   DATE,
  gender      ENUM('Male', 'Female', 'Other'),
  address     TEXT,
  contact     VARCHAR(20),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

**`departments`** — hospital departments (e.g. Cardiology, Pediatrics)
```sql
CREATE TABLE departments (
  dept_id     INT AUTO_INCREMENT PRIMARY KEY,
  dept_name   VARCHAR(100) NOT NULL,
  description TEXT
);
```

**`doctors`** — doctors linked to a user account and department
```sql
CREATE TABLE doctors (
  doctor_id       INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  dept_id         INT NOT NULL,
  specialization  VARCHAR(100),
  schedule        VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);
```

**`appointments`** — booking records between patients and doctors
```sql
CREATE TABLE appointments (
  appt_id     INT AUTO_INCREMENT PRIMARY KEY,
  patient_id  INT NOT NULL,
  doctor_id   INT NOT NULL,
  appt_date   DATETIME NOT NULL,
  status      ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
  notes       TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)
);
```

**`medical_records`** — diagnoses and prescriptions per patient visit
```sql
CREATE TABLE medical_records (
  record_id    INT AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT NOT NULL,
  doctor_id    INT NOT NULL,
  diagnosis    TEXT,
  prescription TEXT,
  record_date  DATE NOT NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)
);
```

---

## 🔍 SQL Concepts Demonstrated

### 1. Multi-table JOIN
Used in the admin appointments page to display full appointment details.
```sql
SELECT
  a.appt_id,
  a.appt_date,
  a.status,
  u_patient.name  AS patient_name,
  u_doctor.name   AS doctor_name,
  d.dept_name
FROM appointments a
JOIN patients   p          ON a.patient_id = p.patient_id
JOIN users      u_patient  ON p.user_id    = u_patient.user_id
JOIN doctors    doc        ON a.doctor_id  = doc.doctor_id
JOIN users      u_doctor   ON doc.user_id  = u_doctor.user_id
JOIN departments d         ON doc.dept_id  = d.dept_id
ORDER BY a.appt_date DESC;
```

### 2. Subquery
Used in reports to find patients who have never had a medical record.
```sql
SELECT name, email
FROM users
WHERE user_id IN (
  SELECT user_id FROM patients
  WHERE patient_id NOT IN (
    SELECT patient_id FROM medical_records
  )
)
AND role = 'patient';
```

### 3. Aggregate with GROUP BY
Used in the admin dashboard to count appointments per doctor.
```sql
SELECT
  u.name        AS doctor_name,
  d.dept_name,
  COUNT(a.appt_id) AS total_appointments
FROM doctors doc
JOIN users        u ON doc.user_id  = u.user_id
JOIN departments  d ON doc.dept_id  = d.dept_id
LEFT JOIN appointments a ON doc.doctor_id = a.doctor_id
GROUP BY doc.doctor_id
ORDER BY total_appointments DESC;
```

### 4. HAVING clause
Used to filter doctors who handled more than 10 appointments this month.
```sql
SELECT
  u.name AS doctor_name,
  COUNT(a.appt_id) AS monthly_count
FROM appointments a
JOIN doctors doc ON a.doctor_id = doc.doctor_id
JOIN users    u  ON doc.user_id = u.user_id
WHERE MONTH(a.appt_date) = MONTH(CURDATE())
  AND YEAR(a.appt_date)  = YEAR(CURDATE())
GROUP BY doc.doctor_id
HAVING monthly_count > 10;
```

### 5. Correlated Subquery
Used to display each doctor's most recent appointment date.
```sql
SELECT
  u.name AS doctor_name,
  (
    SELECT MAX(appt_date)
    FROM appointments a
    WHERE a.doctor_id = doc.doctor_id
  ) AS last_appointment
FROM doctors doc
JOIN users u ON doc.user_id = u.user_id;
```

### 6. LEFT JOIN
Used to list all doctors including those with zero appointments.
```sql
SELECT
  u.name AS doctor_name,
  COUNT(a.appt_id) AS appointment_count
FROM doctors doc
JOIN  users        u ON doc.user_id  = u.user_id
LEFT JOIN appointments a ON doc.doctor_id = a.doctor_id
GROUP BY doc.doctor_id;
```

### 7. Transaction (INSERT + integrity)
Used when a patient books an appointment to ensure atomicity.
```sql
START TRANSACTION;

INSERT INTO appointments (patient_id, doctor_id, appt_date, status, notes)
VALUES (?, ?, ?, 'Pending', ?);

-- Only commits if no errors occur
COMMIT;
```

---

## ⚙️ Installation & Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) installed (Apache + MySQL)
- A browser (Chrome, Firefox, etc.)

### Steps

1. **Clone or download** this repository into your XAMPP `htdocs` folder:
   ```
   C:/xampp/htdocs/hospital-system/
   ```

2. **Start XAMPP** — enable Apache and MySQL from the XAMPP Control Panel.

3. **Import the database:**
   - Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Create a new database named `hospital_db`
   - Click **Import** and upload `sql/hospital.sql`

4. **Configure the database connection** in `config/db.php`:
   ```php
   <?php
   $host     = 'localhost';
   $db_name  = 'hospital_db';
   $username = 'root';
   $password = '';  // default XAMPP password is empty

   $conn = new mysqli($host, $username, $password, $db_name);

   if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
   }
   ?>   
   ```

5. **Open the app** in your browser:
   ```
   http://localhost/hospital-system/
   ```

### Default Accounts (from sample data)

| Role    | Email                  | Password  |
|---------|------------------------|-----------|
| Admin   | admin@hospital.com     | admin123  |
| Patient | patient@example.com    | patient123|

> ⚠️ Change these credentials before any public deployment.

---

## 👤 Features by Role

### Admin
- View dashboard with total patients, appointments today, and top doctors
- Add, edit, and delete doctor and department records
- View and update appointment statuses
- Access patient medical records
- Generate reports (busiest doctor, idle patients, monthly trends)

### Patient
- Register and log in securely
- Browse doctors by department
- Book, cancel, or reschedule appointments
- View upcoming and past appointments
- Access personal medical history and prescriptions

---

## 📸 Pages Summary

| Page                        | Role    | Key SQL Feature               |
|-----------------------------|---------|-------------------------------|
| `admin/dashboard.php`       | Admin   | COUNT, GROUP BY, aggregate    |
| `admin/appointments.php`    | Admin   | 4-table JOIN                  |
| `admin/reports.php`         | Admin   | Subquery, HAVING, correlated  |
| `admin/doctors.php`         | Admin   | LEFT JOIN, INSERT, UPDATE     |
| `patient/book.php`          | Patient | JOIN, INSERT, transaction     |
| `patient/history.php`       | Patient | JOIN across 3 tables          |
| `patient/appointments.php`  | Patient | Filtered SELECT with JOIN     |

---

## 📝 Notes

- Passwords should be hashed using `password_hash()` and verified with `password_verify()` in PHP.
- Use prepared statements (`mysqli` or `PDO`) to prevent SQL injection.
- This project is intended for educational use as a database systems subject requirement.

---

## 👨‍💻 Authors

> Replace with your group members' names and student IDs.

- Name — Student ID
- Name — Student ID
- Name — Student ID

---

## 📄 License

This project is for academic purposes only.