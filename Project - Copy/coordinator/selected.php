<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];
$facultyName = "Faculty";
$selectedContributions = [];


$facultyQuery = "SELECT faculties.faculty_id, faculties.faculty_name
                 FROM users
                 LEFT JOIN faculties ON users.faculty_id = faculties.faculty_id
                 WHERE users.user_id = ?";

$stmtFaculty = $conn->prepare($facultyQuery);
$stmtFaculty->bind_param("i", $userId);
$stmtFaculty->execute();
$facultyResult = $stmtFaculty->get_result();

$facultyId = null;

if ($facultyRow = $facultyResult->fetch_assoc()) {
    $facultyId = $facultyRow['faculty_id'];
    $facultyName = $facultyRow['faculty_name'];
}


if ($facultyId !== null) {
    $sql = "SELECT contributions.contribution_id,
                   contributions.title,
                   users.name AS student_name,
                   contributions.submitted_at,
                   contribution_status.status_name
            FROM contributions
            LEFT JOIN users ON contributions.student_id = users.user_id
            LEFT JOIN contribution_status ON contributions.status_id = contribution_status.status_id
            WHERE contributions.faculty_id = ?
              AND contribution_status.status_name = 'Selected'
            ORDER BY contributions.submitted_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $facultyId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $selectedContributions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator | Selected</title>
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
    <a href="review.php"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
    <a href="selected.php" class="active"><i class="fa-solid fa-check"></i> Selected</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-column"></i> Statistics</a>
</aside>

<main class="feed">
    <h1>Selected Contributions</h1>

    <?php if (!empty($selectedContributions)): ?>
        <ul class="selected-list">
            <?php foreach ($selectedContributions as $item): ?>
                <li>
                    <div>
                        <strong><?= htmlspecialchars($item['title']) ?></strong><br>
                        <small>
                            By <?= htmlspecialchars($item['student_name'] ?? 'Unknown') ?>
                            • <?= date("d M Y", strtotime($item['submitted_at'])) ?>
                        </small>
                    </div>
                    <span>✔ <?= htmlspecialchars($item['status_name']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No selected contributions yet.</p>
    <?php endif; ?>
</main>

</div>
</body>
</html>