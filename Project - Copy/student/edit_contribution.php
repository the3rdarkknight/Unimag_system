<?php
require "../includes/auth.php";
require "../includes/db.php";

// Ensure only students access
if ($_SESSION['role'] != 1) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get contribution ID
if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$contribution_id = intval($_GET['id']);

// ===============================
// GET CONTRIBUTION DETAILS
// ===============================

$stmt = $conn->prepare("
    SELECT c.document_path, c.title, a.final_closure_date
    FROM contributions c
    JOIN academic_years a 
    ON c.academic_year_id = a.academic_year_id
    WHERE c.contribution_id = ? AND c.student_id = ?
");

$stmt->bind_param("ii", $contribution_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Contribution not found.");
}

$contribution = $result->fetch_assoc();


// CHECK FINAL CLOSURE DATE


$today = date("Y-m-d");

if ($today > $contribution['final_closure_date']) {
    echo "<script>
        alert('Submission deadline has passed.');
        window.location.href = 'contributions.php';
    </script>";
    exit;
    
}


// HANDLE FILE UPDATE


$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['article']) || $_FILES['article']['error'] != 0) {
        $message = "Please upload a valid document.";
        $messageType = "error";
    } else {

        $uploadDir = "../uploads/articles/";
        $fileName = time() . "_" . basename($_FILES["article"]["name"]);
        $filePath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowed = ["doc", "docx", "pdf"];

        if (!in_array($fileType, $allowed)) {
            $message = "Only Word or PDF files are allowed.";
            $messageType = "error";
        } else {

            // Delete old file
            if (!empty($contribution['document_path'])) {
                $oldFile = $uploadDir . $contribution['document_path'];
                if (file_exists($oldFile) && !is_dir($oldFile)) {
                    unlink($oldFile);
                }
            }

            // Upload new file
            if (move_uploaded_file($_FILES["article"]["tmp_name"], $filePath)) {
                
                // Update database
                $update = $conn->prepare("
                    UPDATE contributions 
                    SET document_path = ?, status_id = 1, updated_at = NOW()
                    WHERE contribution_id = ?
                ");

                $update->bind_param("si", $fileName, $contribution_id);
                
                if ($update->execute()) {
                    $message = "Document successfully updated!";
                    $messageType = "success";
                    
                    // Refresh contribution data
                    $contribution['document_path'] = $fileName;
                    
                    // TODO: NOTIFY COORDINATOR//////////////////////////
                    if ($update->execute()) {
                        $message = "Document successfully updated!";
                        $messageType = "success";

                        $contribution['document_path'] = $fileName;

                        // Notify coordinator of the update
                        require_once "../includes/email.php";
                        notifyCoordinatorEdit($contribution_id, $conn);

                    } else {}
                    
                } else {
                    $message = "Database update failed. Please try again.";
                    $messageType = "error";
                }
                $update->close();
            } else {
                $message = "File upload failed. Please try again.";
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
 <link rel="stylesheet" href="edit_contri.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Contribution | UniMag</title>
    <link rel="stylesheet" href="../style.css">
   
   
</head>

<body>

<div class="edit-container">

    <div class="page-header">
        <h1>✏️ Edit Article</h1>
        <p style="color: #666; margin: 5px 0 0 0;">Update your contribution document</p>
    </div>

    <div class="contribution-info">
        <div class="info-row">
            <span class="label">Article Title:</span>
            <span class="value"><?php echo htmlspecialchars($contribution['title']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Final Closure Date:</span>
            <span class="value"><?php echo date('F j, Y', strtotime($contribution['final_closure_date'])); ?></span>
        </div>
        <?php if (!empty($contribution['document_path'])): ?>
        <div class="info-row">
            <span class="label">Current File:</span>
            <span class="value">📄 <?php echo htmlspecialchars($contribution['document_path']); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($message != ""): ?>
        <div class="alert <?php echo $messageType; ?>">
            <span class="alert-icon"><?php echo $messageType == 'success' ? '✓' : '⚠'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="upload-section">
            <h3>📤 Upload Updated Document</h3>
            
            <div class="file-input-wrapper">
                <input type="file" name="article" accept=".doc,.docx,.pdf" required>
            </div>

            <div class="file-requirements">
                <strong>Requirements:</strong> Only .doc, .docx,  files are accepted
            </div>
        </div>

        <div class="button-group">
            <button type="submit" class="btn btn-primary">
                💾 Upload New Version
            </button>
            <a href="contributions.php" class="btn btn-secondary">
                🔙 Back to Contributions
            </a>
        </div>

    </form>

</div>

</body>
</html>