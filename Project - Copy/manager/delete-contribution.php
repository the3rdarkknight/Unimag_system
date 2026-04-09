<?php
require "../includes/auth.php";
require "../includes/db.php";

if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: contributions.php");
    exit();
}

$id = intval($_GET['id']);

// Get file paths before deleting
$stmt = $conn->prepare("SELECT document_path FROM contributions WHERE contribution_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$contribution = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contribution) {
    header("Location: contributions.php");
    exit();
}

// Delete associated images from DB and disk
$imgStmt = $conn->prepare("SELECT image_path FROM contribution_images WHERE contribution_id = ?");
$imgStmt->bind_param("i", $id);
$imgStmt->execute();
$images = $imgStmt->get_result();
while ($img = $images->fetch_assoc()) {
    $imgFile = "../uploads/images/" . $img['image_path'];
    if (file_exists($imgFile)) unlink($imgFile);
}
$imgStmt->close();

// Delete image records
$conn->query("DELETE FROM contribution_images WHERE contribution_id = $id");

// Delete the article file from disk
$docFile = "../uploads/articles/" . $contribution['document_path'];
if (file_exists($docFile)) unlink($docFile);

// Delete contribution from DB
$stmt = $conn->prepare("DELETE FROM contributions WHERE contribution_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: contributions.php?deleted=1");
exit();