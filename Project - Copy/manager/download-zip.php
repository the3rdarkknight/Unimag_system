<?php
require "../includes/auth.php";
require "../includes/db.php";

// Only marketing manager
if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}

// Closure date check
$result = $conn->query("
    SELECT final_closure_date 
    FROM academic_years 
    ORDER BY academic_year_id DESC 
    LIMIT 1
");
$year  = $result->fetch_assoc();
$today = date("Y-m-d");

if (!$year || $today < $year['final_closure_date']) {
    die("Downloads are not available until after the final closure date: "
        . ($year['final_closure_date'] ?? 'unknown'));
}

// ── Get selected IDs from POST
$rawIds = $_POST['ids'] ?? [];

if (empty($rawIds)) {
    die("No contributions selected.");
}

// Sanitize keep only integers
$ids = array_filter(array_map('intval', $rawIds));

if (empty($ids)) {
    die("Invalid selection.");
}

// ─Look up ALL file paths from DB using the IDs 

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

$stmt = $conn->prepare("
    SELECT 
        c.contribution_id,
        c.document_path,
        f.faculty_name
    FROM contributions c
    JOIN faculties f ON c.faculty_id = f.faculty_id
    WHERE c.contribution_id IN ($placeholders)
      AND c.status_id = 3
");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$contributions = $stmt->get_result();

if ($contributions->num_rows === 0) {
    die("No valid selected contributions found for the given IDs.");
}

// Absolute paths to upload folders
$articlesDir = __DIR__ . "/../uploads/articles/";
$imagesDir   = __DIR__ . "/../uploads/images/";

// ── Build ZIP 
$tmpFile = tempnam(sys_get_temp_dir(), "unimag_zip_");
$zip     = new ZipArchive();

if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    die("Could not create ZIP archive. Please try again.");
}

$filesAdded = 0;

while ($row = $contributions->fetch_assoc()) {

    // Clean faculty name for use as folder name
    $folder  = preg_replace('/[^A-Za-z0-9_\- ]/', '', $row['faculty_name']);
    $folder  = trim($folder);

    // ── Add Word document
    $docFile = basename($row['document_path']); // basename prevents path traversal
    $docPath = $articlesDir . $docFile;

    if (file_exists($docPath)) {
       
        $zipEntry = $folder . "/" . $docFile;
        $zip->addFile($docPath, $zipEntry);
        $idx = $zip->locateName($zipEntry);
        if ($idx !== false) {
            $zip->setCompressionIndex($idx, ZipArchive::CM_STORE);
        }
        $filesAdded++;
    } else {
        error_log("download-zip.php: doc NOT FOUND at: " . $docPath);
    }

    // ── Add images for this contribution
    $imgStmt = $conn->prepare("
        SELECT image_path 
        FROM contribution_images 
        WHERE contribution_id = ?
    ");
    $imgStmt->bind_param("i", $row['contribution_id']);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();

    while ($img = $imgResult->fetch_assoc()) {
        $imgFile = basename($img['image_path']);
        $imgPath = $imagesDir . $imgFile;

        if (file_exists($imgPath)) {
            // Images go into Faculty/images/ subfolder
            $zip->addFile($imgPath, $folder . "/images/" . $imgFile);
            $filesAdded++;
        } else {
            error_log("download-zip.php: image NOT FOUND at: " . $imgPath);
        }
    }
}

$zip->close();

// Nothing was added tell the user clearly 
if ($filesAdded === 0) {
    unlink($tmpFile);
    die("No files could be found on the server. "
      . "Check that uploaded files exist in /uploads/articles/ and /uploads/images/");
}

// Stream ZIP to browser
header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"UniMag_Selected_Contributions.zip\"");
header("Content-Length: " . filesize($tmpFile));
header("Pragma: no-cache");
header("Expires: 0");

readfile($tmpFile);

// Clean up temp file after sending
unlink($tmpFile);
exit();
?>