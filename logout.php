<?php
// Logout page
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Log the user out
logoutUser();

// Redirect to the login page with a message
setFlashMessage('info', 'You have been successfully logged out.', 'info');
redirect(BASE_URL . 'login.php');
?>

