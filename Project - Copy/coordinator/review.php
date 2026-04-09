<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];
$facultyName = "Faculty";
$facultyId = null;

/* Get coordinator faculty */
$facultyQuery = "SELECT faculties.faculty_id, faculties.faculty_name
                 FROM users
                 LEFT JOIN faculties ON users.faculty_id = faculties.faculty_id
                 WHERE users.user_id = ?";

$stmtFaculty = $conn->prepare($facultyQuery);
$stmtFaculty->bind_param("i", $userId);
$stmtFaculty->execute();
$facultyResult = $stmtFaculty->get_result();

if ($facultyRow = $facultyResult->fetch_assoc()) {
    $facultyId = $facultyRow['faculty_id'];
    $facultyName = $facultyRow['faculty_name'];
}

if ($facultyId === null) {
    die("Coordinator faculty not found.");
}

// PENDING LIST PAGE
if (!isset($_GET['id']) || empty($_GET['id'])) {

    $pendingQuery = "SELECT contributions.contribution_id,
                        contributions.title,
                        contributions.submitted_at,
                        users.name AS student_name
                 FROM contributions
                 LEFT JOIN users ON contributions.student_id = users.user_id
                 WHERE contributions.faculty_id = ?
                   AND contributions.status_id = 1
                 ORDER BY contributions.submitted_at DESC";
    $stmtPending = $conn->prepare($pendingQuery);
    $stmtPending->bind_param("i", $facultyId);
    $stmtPending->execute();
    $pendingResult = $stmtPending->get_result();


    
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator | Pending Reviews</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="coordinator.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<header class="topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag — Coordinator</span>
    </div>
    <div class="right">
        <span class="faculty-tag"><?= htmlspecialchars($facultyName) ?></span>
        <a href="../includes/logout.php" class="btn ghost">Logout</a>
    </div>
</header>
<div class="container">
<aside class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> Contributions</a>
    <a href="review.php" class="active"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
    <a href="selected.php"><i class="fa-solid fa-check"></i> Selected</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-column"></i> Statistics</a>
</aside>
<main class="feed">
    <h1>Pending Reviews</h1>
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Date Submitted</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($pendingResult && $pendingResult->num_rows > 0): ?>
                <?php while ($row = $pendingResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['student_name'] ?? 'Unknown') ?></td>
                        <td>
                            <?= date("d M Y", strtotime($row['submitted_at'])) ?>
                            <?php
                            $daysSince = (time() - strtotime($row['submitted_at'])) / 86400;
                            if ($daysSince > 14):
                            ?>
                                <span class="badge rejected" title="<?= round($daysSince) ?> days since submission">
                                    ⚠ Overdue
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="review.php?id=<?= $row['contribution_id'] ?>" class="btn small">Open Review</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No pending contributions found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>
</div>
</body>
</html>
<?php
exit;
}

$contributionId = intval($_GET['id']);

/* Handle POST actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $comment = trim($_POST['comment'] ?? '');

    // Check which button was clicked
    if (isset($_POST['submit_comment'])) {
        if (!empty($comment)) {

            // Fetch submission date to check 14-day window
            $stmtDate = $conn->prepare(
                "SELECT submitted_at FROM contributions WHERE contribution_id = ? AND faculty_id = ?"
            );
            $stmtDate->bind_param("ii", $contributionId, $facultyId);
            $stmtDate->execute();
            $dateRow = $stmtDate->get_result()->fetch_assoc();
            $stmtDate->close();

            $submittedAt   = $dateRow ? strtotime($dateRow['submitted_at']) : 0;
            $daysSince     = (time() - $submittedAt) / 86400;
            $withinWindow  = ($daysSince <= 14);

            $stmtComment = $conn->prepare(
                "INSERT INTO comments (contribution_id, coordinator_id, comment_text)
                 VALUES (?, ?, ?)"
            );
            $stmtComment->bind_param("iis", $contributionId, $userId, $comment);

            if ($stmtComment->execute()) {
                // Update status to Commented (status_id = 2)
                $commentedStatusId = 2;
                $stmtUpdate = $conn->prepare(
                    "UPDATE contributions SET status_id = ?
                     WHERE contribution_id = ? AND faculty_id = ?"
                );
                $stmtUpdate->bind_param("iii", $commentedStatusId, $contributionId, $facultyId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Send email notification to student
                require_once "../includes/email.php";
                notifyStudent($contributionId, $comment, $conn);

                // Warn coordinator if comment is outside the 14-day window
                if (!$withinWindow) {
                    $daysLate = round($daysSince - 14);
                    $_SESSION['success_message'] = "Comment submitted, but note: this submission is "
                        . $daysLate . " day(s) past the 14-day review window.";
                } else {
                    $_SESSION['success_message'] = "Comment submitted successfully.";
                }
            } else {
                $_SESSION['error_message'] = "Failed to save comment.";
            }
            $stmtComment->close();
        } else {
            $_SESSION['error_message'] = "Please enter a comment.";
        }
    }
    
    /* Select Contribution - FIXED */
    if (isset($_POST['select_contribution'])) {
        $selectedStatusId = 3;
        $stmtUpdate = $conn->prepare(
            "UPDATE contributions SET status_id = ? 
             WHERE contribution_id = ? AND faculty_id = ?"
        );
        $stmtUpdate->bind_param("iii", $selectedStatusId, $contributionId, $facultyId);
        
        if ($stmtUpdate->execute()) {
            $_SESSION['success_message'] = "Contribution selected successfully!";
        } else {
            $_SESSION['error_message'] = "Error selecting contribution.";
        }
        $stmtUpdate->close();
        
        // Redirect to selected page instead of back to review
        header("Location: selected.php");
        exit;
    }

    /* Reject Contribution */
    if (isset($_POST['reject_contribution'])) {
        $rejectedStatusId = 4;
        $stmtUpdate = $conn->prepare(
            "UPDATE contributions SET status_id = ? 
             WHERE contribution_id = ? AND faculty_id = ?"
        );
        $stmtUpdate->bind_param("iii", $rejectedStatusId, $contributionId, $facultyId);
        
        if ($stmtUpdate->execute()) {
            $_SESSION['success_message'] = "Contribution rejected.";
        } else {
            $_SESSION['error_message'] = "Error rejecting contribution.";
        }
        $stmtUpdate->close();
        
        header("Location: review.php");
        exit;
    }

    header("Location: review.php?id=" . $contributionId);
    exit;
}

/* Get contribution */
$sql = "SELECT contributions.contribution_id,
               contributions.title,
               contributions.description,
               contributions.document_path,
               contributions.submitted_at,
               contribution_status.status_name,
               users.name AS student_name
        FROM contributions
        LEFT JOIN users ON contributions.student_id = users.user_id
        LEFT JOIN contribution_status ON contributions.status_id = contribution_status.status_id
        WHERE contributions.contribution_id = ?
          AND contributions.faculty_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $contributionId, $facultyId);
$stmt->execute();
$result = $stmt->get_result();
$contribution = $result->fetch_assoc();
$stmt->close();

if (!$contribution) {
    die("Contribution not found or access denied.");
}

/* Get comments */
$commentsQuery = "SELECT comments.comment_text,
                         comments.created_at,
                         users.name AS coordinator_name
                  FROM comments
                  LEFT JOIN users ON comments.coordinator_id = users.user_id
                  WHERE comments.contribution_id = ?
                  ORDER BY comments.created_at DESC";

$stmtComments = $conn->prepare($commentsQuery);
$stmtComments->bind_param("i", $contributionId);
$stmtComments->execute();
$commentsResult = $stmtComments->get_result();

// Display messages from session
if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator | Review</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="coordinator.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        border-left: 4px solid #28a745;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        border-left: 4px solid #dc3545;
    }
</style>
</head>
<body>
<header class="topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag — Coordinator</span>
    </div>
    <div class="right">
        <span class="faculty-tag"><?= htmlspecialchars($facultyName) ?></span>
        <a href="../includes/logout.php" class="btn ghost">Logout</a>
    </div>
</header>
<div class="container">
<aside class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> Contributions</a>
    <a href="review.php" class="active"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
    <a href="selected.php"><i class="fa-solid fa-check"></i> Selected</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-column"></i> Statistics</a>
</aside>
<main class="feed">
    <h1>Review Contribution</h1>
    
    <?php if (isset($successMsg)): ?>
        <div class="alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    
    <?php if (isset($errorMsg)): ?>
        <div class="alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="review-card">
        <h3><?= htmlspecialchars($contribution['title']) ?></h3>
        <p>
            Submitted by <strong><?= htmlspecialchars($contribution['student_name'] ?? 'Unknown') ?></strong>
            • <?= date("d M Y", strtotime($contribution['submitted_at'])) ?>
        </p>
        <p><strong>Status:</strong> <?= htmlspecialchars($contribution['status_name']) ?></p>

        <?php if (!empty($contribution['description'])): ?>
            <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($contribution['description'])) ?></p>
        <?php endif; ?>

        <!-- Document download -->
        <?php if (!empty($contribution['document_path'])): ?>
            <p>
                <strong>Document:</strong>
                <a href="../uploads/articles/<?= htmlspecialchars($contribution['document_path']) ?>" 
                   target="_blank" class="btn">
                    Download Article
                </a>
            </p>
        <?php endif; ?>

        <?php
        // Fetch images for this contribution
        $imgQuery = $conn->prepare(
            "SELECT image_path FROM contribution_images WHERE contribution_id = ?"
        );
        $imgQuery->bind_param("i", $contributionId);
        $imgQuery->execute();
        $imgResult = $imgQuery->get_result();
        
        if ($imgResult && $imgResult->num_rows > 0): ?>
            <p><strong>Images:</strong></p>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:8px;">
                <?php while ($img = $imgResult->fetch_assoc()): ?>
                    <div style="text-align:center;">
                        <img src="../uploads/images/<?= htmlspecialchars($img['image_path']) ?>"
                             style="max-width:160px; max-height:120px; border-radius:6px; 
                                    border:1px solid #ddd; display:block;">
                        <a href="../uploads/images/<?= htmlspecialchars($img['image_path']) ?>"
                           download class="btn small" style="margin-top:5px; display:inline-block;">
                            Download
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; 
        $imgQuery->close();
        ?>
        
        <form method="POST" action="review.php?id=<?= $contributionId ?>">
            <?php
            $daysSince = (time() - strtotime($contribution['submitted_at'])) / 86400;
            if ($daysSince > 14):
            ?>
                <div class="alert-error" style="margin-bottom:12px;">
                    ⚠ <strong>Overdue:</strong> This submission is <?= round($daysSince) ?> days old —
                    past the 14-day review window. Please comment as soon as possible.
                </div>
            <?php endif; ?>
            <textarea name="comment" placeholder="Enter your comment here..." rows="4"></textarea>

            <div class="actions">
                <button type="submit" name="submit_comment" class="btn primary">Submit Comment</button>

                <button type="submit" name="select_contribution" class="btn">Select</button>
                <button type="submit" name="reject_contribution" class="btn danger">Reject</button>
            </div>
        </form>
    </div>

    <br>

    <div class="review-card">
        <h3>Comments History</h3>
        <?php if ($commentsResult && $commentsResult->num_rows > 0): ?>
            <?php while ($commentRow = $commentsResult->fetch_assoc()): ?>
                <div style="margin-bottom: 15px;">
                    <p><strong><?= htmlspecialchars($commentRow['coordinator_name'] ?? 'Coordinator') ?></strong></p>
                    <p><?= nl2br(htmlspecialchars($commentRow['comment_text'])) ?></p>
                    <small><?= date("d M Y H:i", strtotime($commentRow['created_at'])) ?></small>
                </div>
                <hr>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No comments yet.</p>
        <?php endif; 
        $stmtComments->close();?>
    </div>
</main>
</div>
</body>
</html>