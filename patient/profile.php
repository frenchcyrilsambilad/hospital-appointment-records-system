<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') { header('Location: ../index.php'); exit; }
$pid = $_SESSION['patient_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $gender = trim($_POST['gender']);
    $birthdate = trim($_POST['birthdate']) ?: null;

    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
        $s1->bind_param("ssi", $name, $email, $pid);
        $s1->execute();
        
        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $sp = $conn->prepare("UPDATE users SET password=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
            $sp->bind_param("si", $password, $pid);
            $sp->execute();
        }

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $profile_pic = uniqid('pic_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic)) {
                    $spic = $conn->prepare("UPDATE users SET profile_pic=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
                    $spic->bind_param("si", $profile_pic, $pid);
                    $spic->execute();
                    $_SESSION['profile_pic'] = $profile_pic; // Update session
                }
            }
        }

        $s2 = $conn->prepare("UPDATE patients SET contact=?, gender=?, birthdate=? WHERE patient_id=?");
        $s2->bind_param("sssi", $contact, $gender, $birthdate, $pid);
        $s2->execute();
        
        $conn->commit();
        $_SESSION['name'] = $name; // Update session
        $success = "Profile updated successfully!";
    } catch(Exception $e) {
        $conn->rollback();
        $error = "Failed to update profile.";
    }
}

$u = $conn->prepare("SELECT u.name, u.email, u.profile_pic, p.contact, p.gender, p.birthdate FROM patients p JOIN users u ON p.user_id = u.user_id WHERE p.patient_id=?");
$u->bind_param("i", $pid); $u->execute();
$user = $u->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Profile — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Edit Profile</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content" style="max-width:600px">
      <?php if(isset($success)): ?><div class="alert alert-success" style="margin-bottom:1.5rem"><?= $success ?></div><?php endif; ?>
      <?php if(isset($error)): ?><div class="alert alert-error" style="margin-bottom:1.5rem"><?= $error ?></div><?php endif; ?>

      <div class="card">
        <form method="POST" enctype="multipart/form-data">
          <div style="display:flex;align-items:center;gap:16px;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border)">
            <?php $bg = $user['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($user['profile_pic']).');background-size:cover;background-position:center;color:transparent' : 'background:var(--accent);color:#fff'; ?>
            <div class="avatar avatar-lg" style="<?= $bg ?>;font-size:32px;width:80px;height:80px">
              <?= $user['profile_pic'] ? '' : strtoupper(substr($user['name'],0,1)) ?>
            </div>
            <div style="flex:1">
              <label style="display:block;font-weight:500;margin-bottom:8px">Profile Picture</label>
              <label class="upload-picker">
                <input type="file" name="profile_pic" accept="image/*">
                <span class="upload-picker-action">Choose image</span>
                <span class="upload-picker-name">No image selected</span>
              </label>
            </div>
          </div>

          <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-input" value="<?= htmlspecialchars($user['name']) ?>" required></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" required></div>
          <div class="form-group"><label>New Password (Optional)</label><input type="password" name="password" class="form-input" placeholder="Leave blank to keep current password"></div>
          
          <div class="two-col" style="margin-bottom:0">
            <div class="form-group"><label>Gender</label>
              <select name="gender" class="form-input">
                <option value="" <?= !$user['gender'] ? 'selected' : '' ?>>Select</option>
                <option value="Male" <?= $user['gender']==='Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $user['gender']==='Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= $user['gender']==='Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" class="form-input" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>"></div>
          </div>
          <div class="form-group"><label>Contact Number</label><input type="text" name="contact" class="form-input" value="<?= htmlspecialchars($user['contact'] ?? '') ?>"></div>

          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;height:44px">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.upload-picker input[type="file"]').forEach(function(input) {
    input.addEventListener('change', function() {
        var name = input.closest('.upload-picker').querySelector('.upload-picker-name');
        var fileName = input.files.length ? input.files[0].name : 'No image selected';
        name.textContent = fileName;
        name.classList.toggle('has-file', input.files.length > 0);
    });
});
</script>
</body>
</html>
