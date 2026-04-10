<?php
require "../includes/auth.php";
require "../includes/db.php"; 

if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}
// Real data grouped by faculty, now including comment statistics
$statsQuery = $conn->query("
    SELECT 
        f.faculty_name,
        COUNT(c.contribution_id)      AS total,
        SUM(c.status_id = 3)       AS selected,
        ROUND(SUM(c.status_id = 3) / COUNT(*) * 100, 1)    AS percent_selected,

        -- Count contributions that have at least one comment
        SUM(
            EXISTS (
                SELECT 1 FROM comments cm WHERE cm.contribution_id = c.contribution_id
            )
        )    AS commented,

        -- Count contributions with no comments at all
        SUM(
            NOT EXISTS (
                SELECT 1 FROM comments cm WHERE cm.contribution_id = c.contribution_id
            )
        )     AS not_commented,

        -- Percentage of contributions that have been commented on
        ROUND(
            SUM(
                EXISTS (
                    SELECT 1 FROM comments cm WHERE cm.contribution_id = c.contribution_id
                )
            ) / NULLIF(COUNT(c.contribution_id), 0) * 100
        , 1)      AS percent_commented

    FROM faculties f
    LEFT JOIN contributions c ON c.faculty_id = f.faculty_id
    GROUP BY f.faculty_id, f.faculty_name
    ORDER BY total DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketing Manager | Statistics</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="manager.css">
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
        <a href="statistics.php" class="active"><i class="fa-solid fa-chart-line"></i> Statistics</a>
    </aside>

    <main class="feed">
        <h1>Submission Insights</h1>

        <div class="stats-grid">
            <?php while ($row = $statsQuery->fetch_assoc()): ?>
            <div class="stat-card">
                <h3><?= htmlspecialchars($row['faculty_name']) ?></h3>
                <p><?= $row['total'] ?> submissions</p>
                <p><?= $row['selected'] ?> selected (<?= $row['percent_selected'] ?>%)</p>

                 <!-- Comment statistics section -->
                <hr style="margin: 8px 0; opacity: 0.3;">
                <p>
                    <i class="fa-solid fa-comment-dots"></i>
                    <?= $row['commented'] ?> commented
                    (<?= $row['percent_commented'] ?>%)
                </p>
                <p>
                    <i class="fa-regular fa-comment"></i>
                    <?= $row['not_commented'] ?> not commented yet
                </p>
            
            </div>
            <?php endwhile; ?>
        </div>
    </main>
</div>
</body>
</html>