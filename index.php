<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'doctor') {
        header('Location: doctor/dashboard.php');
    } else {
        header('Location: patient/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login or register

// ── Handle login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT user_id, name, email, password, role, profile_pic FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_pic'] = $row['profile_pic'];

            if ($row['role'] === 'patient') {
                $ps = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
                $ps->bind_param("i", $row['user_id']);
                $ps->execute();
                $pr = $ps->get_result();
                if ($prow = $pr->fetch_assoc()) {
                    $_SESSION['patient_id'] = $prow['patient_id'];
                }
                $ps->close();
            } elseif ($row['role'] === 'doctor') {
                $ps = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
                $ps->bind_param("i", $row['user_id']);
                $ps->execute();
                $pr = $ps->get_result();
                if ($prow = $pr->fetch_assoc()) {
                    $_SESSION['doctor_id'] = $prow['doctor_id'];
                }
                $ps->close();
            }

            if ($row['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($row['role'] === 'doctor') {
                header('Location: doctor/dashboard.php');
            } else {
                header('Location: patient/dashboard.php');
            }
            exit;
        }
    }
    $error = 'Invalid email or password.';
    $stmt->close();
}

// ── Handle registration ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $birthdate = $_POST['birthdate'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $contact = trim($_POST['contact'] ?? '');

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = 'Email already registered.';
        $mode = 'register';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $role = 'patient';

        $conn->begin_transaction();
        try {
            $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("ssss", $name, $email, $hashed, $role);
            $stmt2->execute();
            $uid = $conn->insert_id;
            $stmt2->close();

            $stmt3 = $conn->prepare("INSERT INTO patients (user_id, birthdate, gender, contact) VALUES (?, ?, ?, ?)");
            $stmt3->bind_param("isss", $uid, $birthdate, $gender, $contact);
            $stmt3->execute();
            $stmt3->close();

            $conn->commit();
            $success = 'Account created! You can now log in.';
            $mode = 'login';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Registration failed. Please try again.';
            $mode = 'register';
        }
    }
    $stmt->close();
}

// Counts for hero stats
$totalPatients = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
$totalDoctors = $conn->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'];
$totalAppointments = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="MediCare HMS — Hospital appointment and records management system">
<title>MediCare HMS — Login</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="login-wrap">
  <!-- Left panel -->
  <div class="login-left">
    <div class="logo">
      <div class="logo-icon">+</div>
      <span>MediCare HMS</span>
    </div>
    <div class="login-hero animate-fade-in-up">
      <div class="login-kicker">Hospital operations portal</div>
      <h1>Care coordination, without the paperwork drag.</h1>
      <p>Manage appointments, patient profiles, medical records, doctors, and department reports from one focused workspace.</p>
    </div>
    <div class="login-stats">
      <div>
        <span class="login-stat-value"><?= $totalPatients ?></span>
        <span class="login-stat-label">Patients</span>
      </div>
      <div>
        <span class="login-stat-value"><?= $totalDoctors ?></span>
        <span class="login-stat-label">Doctors</span>
      </div>
      <div>
        <span class="login-stat-value"><?= $totalAppointments ?></span>
        <span class="login-stat-label">Appointments</span>
      </div>
    </div>
  </div>

  <!-- Right panel -->
  <div class="login-right">
    <div class="login-form" id="loginFormWrap" style="<?= $mode === 'register' ? 'display:none' : '' ?>">
      <div class="auth-card-head">
        <span class="pill pill-blue">Secure access</span>
        <h2>Welcome back</h2>
        <p class="sub">Sign in with your hospital account.</p>
      </div>


      <?php if ($error && $mode === 'login'): ?>
      <script>
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: <?= json_encode($error) ?>
        });
      </script>
      <?php endif; ?>
      <?php if ($success): ?>
      <script>
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: <?= json_encode($success) ?>
        });
      </script>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label>Username or email</label>
          <input type="text" name="email" class="form-input" placeholder="admin" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="password-field">
            <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
            <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePassword(this)">&#128065;</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>
      <div class="login-footer">No account? <a href="#" onclick="toggleMode('register');return false;">Register as patient</a></div>
    </div>

    <div class="login-form" id="registerFormWrap" style="<?= $mode === 'register' ? '' : 'display:none' ?>">
      <div class="auth-card-head">
        <span class="pill pill-green">Patient registration</span>
        <h2>Create account</h2>
        <p class="sub">Register as a new patient and book appointments.</p>
      </div>

      <?php if ($error && $mode === 'register'): ?>
      <script>
        Swal.fire({
          icon: 'error',
          title: 'Registration Failed',
          text: <?= json_encode($error) ?>
        });
      </script>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label>Full name</label>
          <input type="text" name="name" class="form-input" placeholder="John Doe" required>
        </div>
        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" class="form-input" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="password-field">
            <input type="password" name="password" class="form-input" placeholder="Min. 6 characters" required minlength="6">
            <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePassword(this)">&#128065;</button>
          </div>
        </div>
        <div style="display:flex;gap:10px">
          <div class="form-group" style="flex:1">
            <label>Date of birth</label>
            <input type="date" name="birthdate" class="form-input">
          </div>
          <div class="form-group" style="flex:1">
            <label>Gender</label>
            <select name="gender" class="form-input">
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Contact number</label>
          <input type="text" name="contact" class="form-input" placeholder="555-0100">
        </div>
        <button type="submit" class="btn btn-primary">Create account</button>
      </form>
      <div class="login-footer">Already have an account? <a href="#" onclick="toggleMode('login');return false;">Sign in</a></div>
    </div>
  </div>
</div>
<script>

function toggleMode(mode) {
  document.getElementById('loginFormWrap').style.display = mode === 'login' ? '' : 'none';
  document.getElementById('registerFormWrap').style.display = mode === 'register' ? '' : 'none';
}

function togglePassword(button) {
  const field = button.closest('.password-field');
  const input = field.querySelector('input');
  const isHidden = input.type === 'password';

  input.type = isHidden ? 'text' : 'password';
  button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
  button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
  button.classList.toggle('active', isHidden);
}
</script>
</body>
</html>
