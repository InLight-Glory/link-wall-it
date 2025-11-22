<?php
require_once __DIR__ . '/../app/core/functions.php';
logout_user();
header('Location: login.php');
exit;
