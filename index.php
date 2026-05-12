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
      <div class="logo-icon">
        <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><path d="M8 1v14M1 8h14" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
      MediCare HMS
    </div>
    <div class="login-hero animate-fade-in-up">
      <h1>Healthcare <span class="gradient-text">made simple.</span></h1>
      <p>Streamlined appointment booking, patient records, and departmental management — all in one secure platform.</p>
    </div>
    <div style="display:flex; gap: 3rem; margin-top: 3rem; animation: fadeInUp 0.7s forwards; animation-delay: 0.2s; opacity: 0;">
      <div><div style="font-family:'Calistoga',serif; font-size:2.5rem; line-height:1; color:var(--foreground);"><?= $totalPatients ?></div><div style="font-size:13px; color:var(--muted-foreground); font-weight:500;">Total patients</div></div>
      <div><div style="font-family:'Calistoga',serif; font-size:2.5rem; line-height:1; color:var(--foreground);"><?= $totalDoctors ?></div><div style="font-size:13px; color:var(--muted-foreground); font-weight:500;">Doctors</div></div>
      <div><div style="font-family:'Calistoga',serif; font-size:2.5rem; line-height:1; color:var(--foreground);">99.9%</div><div style="font-size:13px; color:var(--muted-foreground); font-weight:500;">Uptime</div></div>
    </div>
  </div>

  <!-- Right panel -->
  <div class="login-right">
    <div class="login-form" id="loginFormWrap" style="<?= $mode === 'register' ? 'display:none' : '' ?>">
      <h2>Welcome back</h2>
      <p class="sub">Sign in to your account</p>


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
          <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>
      <div class="login-footer">No account? <a href="#" onclick="toggleMode('register');return false;">Register as patient</a></div>
    </div>

    <div class="login-form" id="registerFormWrap" style="<?= $mode === 'register' ? '' : 'display:none' ?>">
      <h2>Create account</h2>
      <p class="sub">Register as a new patient</p>

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
          <input type="password" name="password" class="form-input" placeholder="Min. 6 characters" required minlength="6">
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
</script>
</body>
</html>
