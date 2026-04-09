<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("User ID not provided.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: users.php");
exit;
?>