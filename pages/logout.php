<?php
session_start();
include "../includes/db.php";

// Clear the session_id in the database for the logged-in admin
if (isset($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("UPDATE admin SET session_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
