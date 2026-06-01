<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
