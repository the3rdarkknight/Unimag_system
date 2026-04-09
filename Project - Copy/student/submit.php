<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

require "../includes/db.php";
require "../includes/auth.php";
require "../includes/email.php"; // ← email helper



if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title       = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $user_id     = $_SESSION["user_id"];
    $status_id   = 1;
    $today       = date("Y-m-d");

    // Single query for academic year — removed the duplicate that was here before
    $result = $conn->query("
        SELECT academic_year_id, submission_closure_date 
        FROM academic_years 
        ORDER BY academic_year_id DESC 
        LIMIT 1
    ");
    $year = $result->fetch_assoc();

    if (!$year) {
        $error = "No academic year configured. Contact your administrator.";
    } elseif ($today > $year['submission_closure_date']) {
        $error = "Submission deadline has passed (" 
               . date("d M Y", strtotime($year['submission_closure_date'])) . ").";
    } else {

        $academic_year_id = $year['academic_year_id'];

        $stmt = $conn->prepare("SELECT faculty_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user       = $stmt->get_result()->fetch_assoc();
        $faculty_id = $user["faculty_id"];

        if (!$faculty_id) {
            $error = "You are not assigned to a faculty. Contact your administrator.";
        } else {

            $fileName  = $_FILES["article"]["name"] ?? '';
            $fileExt   = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileSize  = $_FILES["article"]["size"] ?? 0;
            $allowedTypes = ["doc", "docx"];
            $maxSize      = 10 * 1024 * 1024;

            if (empty($fileName)) {
                $error = "Please select a Word document to upload.";
            } elseif (!in_array($fileExt, $allowedTypes)) {
                $error = "Only Word documents (.doc, .docx) are allowed.";
            } elseif ($fileSize > $maxSize) {
                $error = "File too large. Maximum size is 10MB.";
            } else {

                $uploadDir = "../uploads/articles/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $newFileName = "article_" . time() . "." . $fileExt;
                $filePath    = $uploadDir . $newFileName;

                if (!move_uploaded_file($_FILES["article"]["tmp_name"], $filePath)) {
                    $error = "File upload failed. Please try again.";
                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO contributions 
                            (student_id, faculty_id, academic_year_id, status_id, title, description, document_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiiisss",
                        $user_id, $faculty_id, $academic_year_id,
                        $status_id, $title, $description, $newFileName
                    );

                    if (!$stmt->execute()) {
                        $error = "Database error. Please try again.";
                        unlink($filePath);
                    } else {

                        $contribution_id = $stmt->insert_id;

                        // Images
                        if (!empty($_FILES["image"]["name"][0])) {
                            $imageDir      = "../uploads/images/";
                            $allowedImages = ["jpg", "jpeg", "png"];
                            $maxImageSize  = 5 * 1024 * 1024;
                            if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

                            foreach ($_FILES["image"]["tmp_name"] as $key => $tmp_name) {
                                $imageName = basename($_FILES["image"]["name"][$key]);
                                $imageExt  = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                                $imageSize = $_FILES["image"]["size"][$key];
                                if (!in_array($imageExt, $allowedImages)) continue;
                                if ($imageSize > $maxImageSize) continue;

                                $newImageName = "img_" . time() . "_" . $key . "." . $imageExt;
                                $imagePath    = $imageDir . $newImageName;

                                if (move_uploaded_file($tmp_name, $imagePath)) {
                                    $imgStmt = $conn->prepare("
                                        INSERT INTO contribution_images (contribution_id, image_path)
                                        VALUES (?, ?)
                                    ");
                                    $imgStmt->bind_param("is", $contribution_id, $newImageName);
                                    $imgStmt->execute();
                                    $imgStmt->close();
                                }
                            }
                        }

                        // ── Email coordinator ──────────────────────────────────
                        // Fires after everything saved. Fails silently if SMTP error.
                        notifyCoordinator($contribution_id, $conn);
                        // ──────────────────────────────────────────────────────

                        $success = "Your contribution has been submitted successfully!";
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Article | UniMag</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="student.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag Student</span>
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
        <a href="submit.php" class="active"><i class="fa-solid fa-file-circle-plus"></i> Submit Article</a>
        <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> My Contributions</a>
        <a href="terms.php"><i class="fa-solid fa-scale-balanced"></i> Terms</a>
    </aside>
    <main class="feed">
        <h1 class="page-title">Submit New Contribution</h1>
        <div class="submit-card">
            <?php if (!empty($success)): ?>
                <div style="background:#1a3a1a;color:#4caf50;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #4caf50;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
                    <br><small style="color:#aaa;margin-top:4px;display:block;">Your coordinator has been notified by email.</small>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div style="background:#3a1a1a;color:#ff4d4d;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #ff4d4d;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Article Title</label>
                <input type="text" name="title" placeholder="Enter article title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                <label>Description</label>
                <textarea name="description" rows="5" placeholder="Brief description of your article"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <label>Upload Article <span style="font-size:12px;color:#aaa;font-weight:400;">— .doc or .docx only · Max 10MB</span></label>
                <input type="file" name="article" accept=".doc,.docx" required>
                <label>Upload Images <span style="font-size:12px;color:#aaa;font-weight:400;">— optional · .jpg, .jpeg, .png · Max 5MB each</span></label>
                <input type="file" name="image[]" multiple accept=".jpg,.jpeg,.png">
                <label class="checkbox">
                    <input type="checkbox" required>
                    I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a>
                </label>
                <button class="btn primary full">
                    <i class="fa-solid fa-paper-plane"></i> Submit Contribution
                </button>
            </form>
        </div>
    </main>
</div>
</body>
</html>