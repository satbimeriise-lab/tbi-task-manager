<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
requireLogin();
if (isAdmin()) redirect(BASE_URL . '/admin/dashboard.php');
else           redirect(BASE_URL . '/employee/dashboard.php');
