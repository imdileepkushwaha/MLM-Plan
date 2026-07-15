<?php
require_once __DIR__ . '/includes/auth.php';

unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
flash('success', 'You have been logged out.');
header('Location: login.php');
exit;
