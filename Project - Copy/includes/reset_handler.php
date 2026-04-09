<?php
/**

 * Handles all 3 steps of password reset via AJAX
 * Step 1: Verify email exists → generate OTP → send email
 * Step 2: Verify OTP code
 * Step 3: Update password in DB
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/email.php";

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ── STEP 1: Send OTP 
if ($action === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    // Check email exists in DB
    $stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // Store OTP in session (no extra DB table needed)
    $_SESSION['reset_otp']     = $otp;
    $_SESSION['reset_email']   = $email;
    $_SESSION['reset_expires'] = $expires;
    $_SESSION['reset_verified'] = false;

    // Send OTP email using your existing emailTemplate helper
    $body = emailTemplate(
        "Password Reset Code",
        "
        <p>You requested a password reset for your UniMag account.</p>

        <div style='text-align:center;margin:28px 0;'>
            <div style='display:inline-block;background:#2a2d36;border:1px solid #ff4500;
                        border-radius:10px;padding:18px 36px;'>
                <p style='margin:0 0 6px;font-size:13px;color:#aaa;letter-spacing:1px;
                           text-transform:uppercase;'>Your Reset Code</p>
                <span style='font-size:38px;font-weight:800;letter-spacing:10px;
                              color:#ff4500;font-family:monospace;'>$otp</span>
            </div>
        </div>

        <p style='color:#aaa;font-size:13px;text-align:center;'>
            This code expires in <strong style='color:#e6e6e6;'>10 minutes</strong>.<br>
            If you did not request this, please ignore this email.
        </p>
        "
    );

    $sent = sendEmail($email, $user['name'], 'UniMag Your Password Reset Code', $body);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Reset code sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
    }
    exit;
}

// ── STEP 2: Verify OTP 
if ($action === 'verify_otp') {
    $entered = trim($_POST['code'] ?? '');

    if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_expires'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    if (time() > strtotime($_SESSION['reset_expires'])) {
        echo json_encode(['success' => false, 'message' => 'Code has expired. Please request a new one.']);
        exit;
    }

    if ($entered !== $_SESSION['reset_otp']) {
        echo json_encode(['success' => false, 'message' => 'Invalid code. Please try again.']);
        exit;
    }

    $_SESSION['reset_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Code verified successfully.']);
    exit;
}

// STEP 3: Update Password 
if ($action === 'reset_password') {
    if (empty($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please verify your code first.']);
        exit;
    }

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $email = $_SESSION['reset_email'] ?? '';
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->bind_param("ss", $hash, $email);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // Clear reset session data
    unset(
        $_SESSION['reset_otp'],
        $_SESSION['reset_email'],
        $_SESSION['reset_expires'],
        $_SESSION['reset_verified']
    );

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);