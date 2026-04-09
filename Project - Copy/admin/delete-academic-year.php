<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Academic year ID not provided.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM academic_years WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: academic-years.php");
exit;
?>