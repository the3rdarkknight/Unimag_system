<?php
require "../includes/auth.php";
require "../includes/db.php";

if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}

$facultyOptions = $conn->query("
    SELECT DISTINCT f.faculty_id, f.faculty_name
    FROM faculties f
    JOIN contributions c ON c.faculty_id = f.faculty_id
    WHERE c.status_id = 3
    ORDER BY f.faculty_name ASC
");

$result        = $conn->query("SELECT final_closure_date FROM academic_years ORDER BY academic_year_id DESC LIMIT 1");
$year          = $result->fetch_assoc();
$final_closure = $year['final_closure_date'] ?? null;
$today         = date("Y-m-d");

 $downloads_open = $final_closure && ($today >= $final_closure);


$contributions = $conn->query("
    SELECT 
        c.contribution_id,
        c.faculty_id,
        c.title,
        c.document_path,
        c.submitted_at,
        f.faculty_name,
        COUNT(ci.image_id) AS image_count
    FROM contributions c
    JOIN faculties f ON c.faculty_id = f.faculty_id
    LEFT JOIN contribution_images ci ON ci.contribution_id = c.contribution_id
    WHERE c.status_id = 3
    GROUP BY c.contribution_id
    ORDER BY f.faculty_name ASC, c.submitted_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketing Manager | Contributions</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="manager.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- NO manager.js — JS is inline below to avoid caching issues -->
</head>
<body>

<header class="topbar">
 <div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag -- manager</span>
    </a>
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
        <a href="contributions.php" class="active"><i class="fa-solid fa-folder-open"></i> Contributions</a>
        <a href="statistics.php"><i class="fa-solid fa-chart-line"></i> Statistics</a>
        <a href="bulk-notify.php"><i class="fa-solid fa-bell"></i> Notify Users</a>
    </aside>

    <main class="feed">
        <h1 class="page-title">Selected Contributions</h1>

        <div class="banner <?= $downloads_open ? 'success' : 'warning' ?>">
            <i class="fa-solid <?= $downloads_open ? 'fa-circle-check' : 'fa-clock' ?>"></i>
            <?php if ($downloads_open): ?>
                Downloads are open. Select contributions and click Download ZIP.
            <?php else: ?>
                Downloads open after the final closure date
                <?= $final_closure ? '(' . date("d M Y", strtotime($final_closure)) . ')' : '' ?>.
            <?php endif; ?>
        </div>

        <div class="toolbar">
            <!-- Faculty filter -->
            <select id="facultyFilter">
                <option value="all">All Faculties</option>
                <?php while ($fac = $facultyOptions->fetch_assoc()): ?>
                    <option value="<?= $fac['faculty_id'] ?>">
                        <?= htmlspecialchars($fac['faculty_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <?php if ($downloads_open): ?>
                <form id="downloadForm" action="download-zip.php" method="POST" style="display:inline;">
                    <button type="submit" id="downloadZip" class="btn primary" disabled>
                        <i class="fa-solid fa-download"></i>
                        Download Selected (<span id="selectedCount">0</span>)
                    </button>
                </form>
            <?php else: ?>
                <button class="btn primary" disabled>
                    <i class="fa-solid fa-lock"></i> Locked until closure date
                </button>
            <?php endif; ?>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th><?php if ($downloads_open): ?><input type="checkbox" id="selectAll"><?php endif; ?></th>
                    <th>Title</th>
                    <th>Faculty</th>
                    <th>Images</th>
                    <th>Submitted</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($contributions && $contributions->num_rows > 0): ?>
                <?php while ($row = $contributions->fetch_assoc()): ?>
                <tr data-faculty="<?= $row['faculty_id'] ?>">
                    <td>
                        <?php if ($downloads_open): ?>
                            <input type="checkbox"
                                   class="select-item"
                                   data-id="<?= (int)$row['contribution_id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['faculty_name']) ?></td>
                    <td>
                        <?php if ($row['image_count'] > 0): ?>
                            <span class="badge approved">
                                <i class="fa-solid fa-image"></i>
                                <?= $row['image_count'] ?> image<?= $row['image_count'] > 1 ? 's' : '' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size:13px;color:#999;">None</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:#666;">
                        <?= date("d M Y", strtotime($row['submitted_at'])) ?>
                    </td>
                    <td>
                        <a href="../uploads/articles/<?= htmlspecialchars($row['document_path']) ?>"
                        target="_blank" class="btn small ghost">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                        <a href="delete-contribution.php?id=<?= (int)$row['contribution_id'] ?>"
                        class="btn small danger"
                        onclick="return confirm('Delete this contribution? This cannot be undone.')">
                            <i class="fa-solid fa-trash"></i> Delete
                        </a>
                        <?php if (isset($_GET['deleted'])): ?>
                            <div class="banner success" style="margin-bottom:16px;">
                                <i class="fa-solid fa-circle-check"></i> Contribution deleted successfully.
                            </div>
                        <?php endif; ?>
                    </td>
                
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:2rem;color:#999;">
                        No selected contributions yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<script>
// Inline JS — no external file, no caching, no path issues

var form        = document.getElementById("downloadForm");
var downloadBtn = document.getElementById("downloadZip");
var selectAllCb = document.getElementById("selectAll");
var countSpan   = document.getElementById("selectedCount");

// Update the button count and enabled state
function updateCount() {
    var checked = document.querySelectorAll(".select-item:checked").length;
    if (countSpan)   countSpan.textContent = checked;
    if (downloadBtn) downloadBtn.disabled  = (checked === 0);
}

// Select all checkbox
if (selectAllCb) {
    selectAllCb.addEventListener("change", function() {
        document.querySelectorAll(".select-item").forEach(function(cb) {
            cb.checked = selectAllCb.checked;
        });
        updateCount();
    });
}

// Individual checkboxes
document.querySelectorAll(".select-item").forEach(function(cb) {
    cb.addEventListener("change", function() {
        if (selectAllCb) {
            var total   = document.querySelectorAll(".select-item").length;
            var checked = document.querySelectorAll(".select-item:checked").length;
            selectAllCb.checked       = (total === checked);
            selectAllCb.indeterminate = (checked > 0 && checked < total);
        }
        updateCount();
    });
});

// Faculty filter
var facultyFilter = document.getElementById("facultyFilter");
if (facultyFilter) {
    facultyFilter.addEventListener("change", function() {
        var val = this.value;
        document.querySelectorAll("tbody tr").forEach(function(row) {
            var show = (val === "all" || row.dataset.faculty === val);
            row.style.display = show ? "" : "none";
            if (!show) {
                var cb = row.querySelector(".select-item");
                if (cb) cb.checked = false;
            }
        });
        updateCount();
    });
}

// Form submit — inject hidden IDs then POST to download-zip.php
if (form) {
    form.addEventListener("submit", function(e) {
        e.preventDefault();

        var checked = document.querySelectorAll(".select-item:checked");

        if (checked.length === 0) {
            alert("Please select at least one contribution.");
            return;
        }

        // Clear any previously injected inputs
        form.querySelectorAll("input[name='ids[]']").forEach(function(el) {
            el.remove();
        });

        // Inject one hidden input per selected ID
        checked.forEach(function(cb) {
            var input   = document.createElement("input");
            input.type  = "hidden";
            input.name  = "ids[]";
            input.value = cb.dataset.id;
            form.appendChild(input);
        });

        form.submit(); // POST to download-zip.php
    });
}

updateCount();
</script>

</body>
</html>