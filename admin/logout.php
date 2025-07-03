<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Logout user
Auth::logout();

// Redirect to login page
redirect('/admin/login.php', 'Anda telah logout berhasil', 'success');
?>
