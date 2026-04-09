<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];
$facultyName = "Faculty";
$contributions = [];
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

/* Fetch contributions for this faculty */
if ($facultyId !== null) {
    $sql = "SELECT contributions.contribution_id,
                   contributions.title,
                   contributions.submitted_at,
                   contributions.status_id,
                   users.name AS student_name
            FROM contributions
            LEFT JOIN users ON contributions.student_id = users.user_id
            WHERE contributions.faculty_id = ?
            ORDER BY contributions.submitted_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $facultyId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $contributions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator | Contributions</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="coordinator.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="topbar">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag -- coordinator</span>
    </a>
</div>
    <div class="right">
        <span class="faculty-tag"><?= htmlspecialchars($facultyName) ?></span>
        <a href="../includes/logout.php" class="btn ghost">Logout</a>
    </div>
</header>

<div class="container">

<aside class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="contributions.php" class="active"><i class="fa-solid fa-folder-open"></i> Contributions</a>
    <a href="review.php"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
    <a href="selected.php"><i class="fa-solid fa-check"></i> Selected</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-column"></i> Statistics</a>
</aside>

<main class="feed">
    <h1>Faculty Contributions</h1>

    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Date Submitted</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($contributions)): ?>
                <?php foreach ($contributions as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['student_name'] ?? 'Unknown') ?></td>
                        <td><?= date("d M Y", strtotime($row['submitted_at'])) ?></td>
                        <td>
                            <?php if ($row['status_id'] == 1): ?>
                                <span class="badge pending">submitted</span>
                            <?php elseif ($row['status_id'] == 3): ?>
                                <span class="badge selected">Selected</span>
                            <?php else: ?>
                                <span class="badge rejected">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status_id'] == 1): ?>
                                <a href="review.php?id=<?= $row['contribution_id'] ?>" class="btn small">Review</a>
                            <?php else: ?>
                                <a href="selected.php?id=<?= $row['contribution_id'] ?>" class="btn small ghost">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No contributions found for this faculty.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

</div>
</body>
</html>