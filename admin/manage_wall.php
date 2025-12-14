<?php
// Legacy redirection
$wall_id = $_GET['wall_id'] ?? '';
header("Location: manage_list.php?wall_id=$wall_id");
exit;
