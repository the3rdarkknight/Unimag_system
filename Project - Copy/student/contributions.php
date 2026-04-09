<?php
require "../includes/db.php";
require "../includes/auth.php";


$user_id = $_SESSION["user_id"];

/*
Query contributions made by this student
- faculties  get faculty name
- contribution_status get status name
- comments get coordinator comments
*/

$sql = "
SELECT 
c.contribution_id,
c.title,
f.faculty_name,
cs.status_name,
cm.comment_text
FROM contributions c
JOIN faculties f ON c.faculty_id = f.faculty_id
JOIN contribution_status cs ON c.status_id = cs.status_id
LEFT JOIN comments cm ON c.contribution_id = cm.contribution_id
WHERE c.student_id = ?
ORDER BY c.submitted_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Contributions | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<header class="topbar">
 <div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag -- Student</span>
    </a>
</div>

<div class="right">
    <form action="../includes/logout.php" method="POST" style="display:inline;">
        <button type="submit" class="btn ghost">Logout</button>
    </form>
</div>
</header>

<div class="container">

<!-- SIDEBAR -->
<aside class="sidebar">

<a href="dashboard.php">
<i class="fa-solid fa-gauge"></i> Dashboard
</a>

<a href="submit.php">
<i class="fa-solid fa-file-circle-plus"></i> Submit Article
</a>

<a href="contributions.php" class="active">
<i class="fa-solid fa-folder-open"></i> My Contributions
</a>

<a href="terms.php">
<i class="fa-solid fa-scale-balanced"></i> Terms
</a>

</aside>

<!-- MAIN -->
<main class="feed">

<h1 class="page-title">My Contributions</h1>

<table class="data-table">

<thead>
<tr>
<th>Title</th>
<th>Faculty</th>
<th>Status</th>
<th>Comments</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<tbody>

    <?php if ($result->num_rows > 0): ?>

    <?php while ($row = $result->fetch_assoc()): ?>

    <tr>

    <td><?php echo htmlspecialchars($row["title"]); ?></td>

    <td><?php echo htmlspecialchars($row["faculty_name"]); ?></td>

    <td>
<span class="badge">

<?php echo htmlspecialchars($row["status_name"]); ?>

</span>
</td>

<td>
<?php echo $row["comment_text"] ? htmlspecialchars($row["comment_text"]) : "No comment yet"; ?>
</td>

<td> 
    <a href="edit_contribution.php?id=<?php echo $row["contribution_id"]; ?>" class="btn small ghost">Edit</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="5">No contributions submitted yet.</td>
</tr>

<?php endif; ?>

</tbody>

</tbody>

</table>

</main>

</div>

</body>
</html>
