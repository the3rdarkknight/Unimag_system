<?php
require "../includes/auth.php";
require "../includes/db.php";

// ─── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];

// ─── Fetch coordinator's faculty ──────────────────────────────────────────────
$stmtFaculty = $conn->prepare(
    "SELECT f.faculty_id, f.faculty_name
     FROM users u
     LEFT JOIN faculties f ON u.faculty_id = f.faculty_id
     WHERE u.user_id = ?"
);
$stmtFaculty->bind_param("i", $userId);
$stmtFaculty->execute();
$facultyRow = $stmtFaculty->get_result()->fetch_assoc();
$stmtFaculty->close();

if (!$facultyRow || $facultyRow['faculty_id'] === null) {
    die("Coordinator faculty not found.");
}

$facultyId   = $facultyRow['faculty_id'];
$facultyName = $facultyRow['faculty_name'];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function daysSince(string $dateStr): float {
    return (time() - strtotime($dateStr)) / 86400;
}

function flashSuccess(string $msg): void { $_SESSION['flash_success'] = $msg; }
function flashError(string $msg): void   { $_SESSION['flash_error']   = $msg; }

function popFlash(): array {
    $msgs = [
        'success' => $_SESSION['flash_success'] ?? null,
        'error'   => $_SESSION['flash_error']   ?? null,
    ];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $msgs;
}

// ═════════════════════════════════════════════════════════════════════════════
// PENDING LIST PAGE  (no ?id parameter)
// ═════════════════════════════════════════════════════════════════════════════
if (empty($_GET['id'])) {

    $stmtPending = $conn->prepare(
        "SELECT c.contribution_id, c.title, c.submitted_at, u.name AS student_name
         FROM contributions c
         LEFT JOIN users u ON c.student_id = u.user_id
         WHERE c.faculty_id = ? AND c.status_id = 1
         ORDER BY c.submitted_at DESC"
    );
    $stmtPending->bind_param("i", $facultyId);
    $stmtPending->execute();
    $pendingRows = $stmtPending->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtPending->close();

    $flash = popFlash();
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

        <?php if ($flash['success']): ?>
            <div class="alert alert--success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>
        <?php if ($flash['error']): ?>
            <div class="alert alert--error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

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
                <?php if ($pendingRows): ?>
                    <?php foreach ($pendingRows as $row): ?>
                        <?php $age = daysSince($row['submitted_at']); ?>
                        <tr>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['student_name'] ?? 'Unknown') ?></td>
                            <td>
                                <?= date("d M Y", strtotime($row['submitted_at'])) ?>
                                <?php if ($age > 14): ?>
                                    <span class="badge badge--overdue"
                                          title="<?= round($age) ?> days since submission">
                                        ⚠ Overdue
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="review.php?id=<?= $row['contribution_id'] ?>"
                                   class="btn btn--small">Open Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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

// ═════════════════════════════════════════════════════════════════════════════
// SINGLE REVIEW PAGE  (?id=N)
// ═════════════════════════════════════════════════════════════════════════════
$contributionId = intval($_GET['id']);

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Determine which action was submitted — only one branch runs (if-elseif).
    $action = match (true) {
        isset($_POST['submit_comment'])      => 'comment',
        isset($_POST['select_contribution']) => 'select',
        isset($_POST['reject_contribution']) => 'reject',
        default                              => null,
    };

    switch ($action) {

        // ── Comment ──────────────────────────────────────────────────────────
        case 'comment':
            $comment = trim($_POST['comment'] ?? '');

            if ($comment === '') {
                flashError("Please enter a comment before submitting.");
                break;
            }

            // Fetch submission date to check 14-day window
            $stmtDate = $conn->prepare(
                "SELECT submitted_at FROM contributions
                 WHERE contribution_id = ? AND faculty_id = ?"
            );
            $stmtDate->bind_param("ii", $contributionId, $facultyId);
            $stmtDate->execute();
            $dateRow = $stmtDate->get_result()->fetch_assoc();
            $stmtDate->close();

            $age          = $dateRow ? daysSince($dateRow['submitted_at']) : 0;
            $withinWindow = ($age <= 14);

            // Insert comment
            $stmtComment = $conn->prepare(
                "INSERT INTO comments (contribution_id, coordinator_id, comment_text)
                 VALUES (?, ?, ?)"
            );
            $stmtComment->bind_param("iis", $contributionId, $userId, $comment);

            if ($stmtComment->execute()) {
                // Move status → Commented (status_id = 2)
                $stmtStatus = $conn->prepare(
                    "UPDATE contributions SET status_id = 2
                     WHERE contribution_id = ? AND faculty_id = ?"
                );
                $stmtStatus->bind_param("ii", $contributionId, $facultyId);
                $stmtStatus->execute();
                $stmtStatus->close();

                // Email notification
                require_once "../includes/email.php";
                notifyStudent($contributionId, $comment, $conn);

                if ($withinWindow) {
                    flashSuccess("Comment submitted successfully.");
                } else {
                    $daysLate = round($age - 14);
                    flashSuccess(
                        "Comment submitted, but note: this submission is {$daysLate} day(s) "
                        . "past the 14-day review window."
                    );
                }
            } else {
                flashError("Failed to save comment. Please try again.");
            }

            $stmtComment->close();
            break;

        // ── Select ───────────────────────────────────────────────────────────
        case 'select':
            $stmtSelect = $conn->prepare(
                "UPDATE contributions SET status_id = 3
                 WHERE contribution_id = ? AND faculty_id = ?"
            );
            $stmtSelect->bind_param("ii", $contributionId, $facultyId);

            if ($stmtSelect->execute() && $stmtSelect->affected_rows > 0) {
                flashSuccess("Contribution selected successfully!");
            } else {
                flashError("Could not select the contribution. Please try again.");
            }
            $stmtSelect->close();

            header("Location: selected.php");
            exit;

        // ── Reject ───────────────────────────────────────────────────────────
        case 'reject':
            $stmtReject = $conn->prepare(
                "UPDATE contributions SET status_id = 4
                 WHERE contribution_id = ? AND faculty_id = ?"
            );
            $stmtReject->bind_param("ii", $contributionId, $facultyId);

            if ($stmtReject->execute() && $stmtReject->affected_rows > 0) {
                flashSuccess("Contribution rejected.");
            } else {
                flashError("Could not reject the contribution. Please try again.");
            }
            $stmtReject->close();

            header("Location: review.php");
            exit;

        default:
            flashError("Unknown action.");
    }

    // Redirect back to the same review page (PRG pattern)
    header("Location: review.php?id=" . $contributionId);
    exit;
}

// ─── Fetch contribution ───────────────────────────────────────────────────────
$stmtContrib = $conn->prepare(
    "SELECT c.contribution_id, c.title, c.description, c.document_path,
            c.submitted_at, cs.status_name, u.name AS student_name
     FROM contributions c
     LEFT JOIN users u              ON c.student_id  = u.user_id
     LEFT JOIN contribution_status cs ON c.status_id = cs.status_id
     WHERE c.contribution_id = ? AND c.faculty_id = ?"
);
$stmtContrib->bind_param("ii", $contributionId, $facultyId);
$stmtContrib->execute();
$contribution = $stmtContrib->get_result()->fetch_assoc();
$stmtContrib->close();

if (!$contribution) {
    die("Contribution not found or access denied.");
}

// ─── Fetch images ─────────────────────────────────────────────────────────────
$stmtImgs = $conn->prepare(
    "SELECT image_path FROM contribution_images WHERE contribution_id = ?"
);
$stmtImgs->bind_param("i", $contributionId);
$stmtImgs->execute();
$images = $stmtImgs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtImgs->close();

// ─── Fetch comments ───────────────────────────────────────────────────────────
$stmtComments = $conn->prepare(
    "SELECT cm.comment_text, cm.created_at, u.name AS coordinator_name
     FROM comments cm
     LEFT JOIN users u ON cm.coordinator_id = u.user_id
     WHERE cm.contribution_id = ?
     ORDER BY cm.created_at DESC"
);
$stmtComments->bind_param("i", $contributionId);
$stmtComments->execute();
$comments = $stmtComments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtComments->close();

$flash = popFlash();
$age   = daysSince($contribution['submitted_at']);
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
        .alert { padding: 10px 14px; border-radius: 4px; margin-bottom: 15px; }
        .alert--success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert--error   { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert--warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .image-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
        .image-grid figure { margin: 0; text-align: center; }
        .image-grid img { max-width: 160px; max-height: 120px; border-radius: 6px;
                          border: 1px solid #ddd; display: block; }
        .comment-entry { margin-bottom: 15px; }
        .comment-entry small { color: #666; }
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

        <?php if ($flash['success']): ?>
            <div class="alert alert--success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>
        <?php if ($flash['error']): ?>
            <div class="alert alert--error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

        <!-- ── Contribution detail ─────────────────────────────────────── -->
        <div class="review-card">
            <h3><?= htmlspecialchars($contribution['title']) ?></h3>
            <p>
                Submitted by
                <strong><?= htmlspecialchars($contribution['student_name'] ?? 'Unknown') ?></strong>
                &bull; <?= date("d M Y", strtotime($contribution['submitted_at'])) ?>
            </p>
            <p><strong>Status:</strong> <?= htmlspecialchars($contribution['status_name']) ?></p>

            <?php if (!empty($contribution['description'])): ?>
                <p>
                    <strong>Description:</strong>
                    <?= nl2br(htmlspecialchars($contribution['description'])) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($contribution['document_path'])): ?>
                <p>
                    <strong>Document:</strong>
                    <a href="../uploads/articles/<?= htmlspecialchars($contribution['document_path']) ?>"
                       target="_blank" class="btn">
                        Download Article
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($images): ?>
                <p><strong>Images:</strong></p>
                <div class="image-grid">
                    <?php foreach ($images as $img): ?>
                        <figure>
                            <img src="../uploads/images/<?= htmlspecialchars($img['image_path']) ?>"
                                 alt="Contribution image">
                            <a href="../uploads/images/<?= htmlspecialchars($img['image_path']) ?>"
                               download class="btn btn--small" style="margin-top:5px; display:inline-block;">
                                Download
                            </a>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── Action form ──────────────────────────────────────────── -->
            <form method="POST" action="review.php?id=<?= $contributionId ?>" style="margin-top:20px;">

                <?php if ($age > 14): ?>
                    <div class="alert alert--warning" style="margin-bottom:12px;">
                        ⚠ <strong>Overdue:</strong> This submission is <?= round($age) ?> days old —
                        past the 14-day review window. Please comment as soon as possible.
                    </div>
                <?php endif; ?>

                <textarea name="comment"
                          placeholder="Enter your comment here…"
                          rows="4"
                          style="width:100%;"></textarea>

                <div class="actions" style="margin-top:10px; display:flex; gap:10px;">
                    <button type="submit" name="submit_comment" class="btn btn--primary">
                        Submit Comment
                    </button>
                    <button type="submit" name="select_contribution" class="btn"
                            onclick="return confirm('Mark this contribution as selected?')">
                        Select
                    </button>
                    <button type="submit" name="reject_contribution" class="btn btn--danger"
                            onclick="return confirm('Are you sure you want to reject this contribution?')">
                        Reject
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Comments history ───────────────────────────────────────── -->
        <div class="review-card" style="margin-top:20px;">
            <h3>Comments History</h3>
            <?php if ($comments): ?>
                <?php foreach ($comments as $c): ?>
                    <div class="comment-entry">
                        <p><strong><?= htmlspecialchars($c['coordinator_name'] ?? 'Coordinator') ?></strong></p>
                        <p><?= nl2br(htmlspecialchars($c['comment_text'])) ?></p>
                        <small><?= date("d M Y H:i", strtotime($c['created_at'])) ?></small>
                    </div>
                    <hr>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No comments yet.</p>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>