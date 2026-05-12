<?php
function send_mock_email($to_email, $patient_name, $status, $date) {
    global $conn; // Access the database connection from the parent scope
    
    // Fallback if $conn isn't available
    if (!$conn) {
        require __DIR__ . '/db.php';
    }

    $subject = "Appointment Update: $status";
    $body = "Hello $patient_name,\n\nYour appointment scheduled for " . date('M j, Y g:i A', strtotime($date)) . " has been marked as: $status.\n\nThank you for choosing MediCare HMS.\n";
    
    // 1. Save to Database
    try {
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, subject, body) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $to_email, $subject, $body);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('Email database log failed: ' . $e->getMessage());
    }

    // 2. Also keep a local text file log for easy viewing without phpMyAdmin
    $logs_dir = __DIR__ . '/../logs';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0777, true);
    }
    $file = $logs_dir . '/emails.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "=================================================\n";
    $log_entry .= "Date: $timestamp\n";
    $log_entry .= "To: $to_email\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Body:\n$body\n";
    $log_entry .= "=================================================\n\n";
    file_put_contents($file, $log_entry, FILE_APPEND);
}
