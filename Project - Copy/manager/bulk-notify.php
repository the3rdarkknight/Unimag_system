<?php
require "../includes/auth.php";
require "../includes/db.php";
require "../includes/email.php";

if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}

$sent    = 0;
$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        $error = "Both subject and message are required.";
    } else {
        $sent    = notifyAllUsers($subject, $message, $conn);
        $success = "Announcement sent to $sent users successfully.";
    }
}

// Count active users for display
$userCount = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bulk Notify | UniMag Manager</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<header class="topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag — Manager</span>
    </div>
    <div class="right">
        <form action="../includes/logout.php" method="POST" style="display:inline;">
            <button type="submit" class="btn ghost">Logout</button>
        </form>
    </div>
</header>

<div class="container">
    <aside class="sidebar">
        <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> Contributions</a>
        <a href="statistics.php"><i class="fa-solid fa-chart-line"></i> Statistics</a>
        <a href="bulk-notify.php" class="active"><i class="fa-solid fa-bell"></i> Notify Users</a>
    </aside>

    <main class="feed">
        <h1 class="page-title">Bulk Notification</h1>

        <div class="banner warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            This will send an email to all <strong><?= $userCount ?></strong> registered users.
        </div>

        <?php if (!empty($success)): ?>
            <div style="background:#1a3a1a;color:#4caf50;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #4caf50;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background:#3a1a1a;color:#ff4d4d;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #ff4d4d;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="review-card" style="max-width:600px;">
            <form method="POST">
                <label style="display:block;margin-bottom:6px;font-size:14px;">Email Subject</label>
                <input type="text" name="subject"
                       placeholder="e.g. Reminder: Submission deadline approaching"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                       style="width:100%;padding:10px;background:#2a2d36;border:none;border-radius:6px;color:#fff;margin-bottom:16px;"
                       required>

                <label style="display:block;margin-bottom:6px;font-size:14px;">Message</label>
                <textarea name="message" rows="8"
                          placeholder="Write your announcement here..."
                          style="width:100%;padding:10px;background:#2a2d36;border:none;border-radius:6px;color:#fff;resize:vertical;"
                          required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>

                <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                    <button type="submit" class="btn primary"
                            onclick="return confirm('Send this email to all <?= $userCount ?> users?')">
                        <i class="fa-solid fa-paper-plane"></i> Send to All Users
                    </button>
                    <span style="font-size:13px;color:#aaa;">Sending to <?= $userCount ?> users</span>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>