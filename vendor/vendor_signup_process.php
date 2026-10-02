<?php
// Vendor registration is a separate entry point, so its account type is set
// server-side instead of being supplied by the browser.
$_POST['role'] = 'shop';

require __DIR__ . '/signup_process.php';
