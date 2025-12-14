<?php
// Legacy redirection
$id = $_GET['id'] ?? '';
header("Location: list.php?id=$id");
exit;
