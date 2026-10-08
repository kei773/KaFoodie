<?php
// SMTP settings for KaFoodie emails.
// KEEP THIS FILE OUT OF GITHUB (it is listed in .gitignore).
// On every new computer (e.g. the school PC) you must create this file again.

return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',   // 'tls' for port 587, 'ssl' for port 465

    // Your Gmail address and its 16-character APP PASSWORD (not your normal password).
    // Create it at: Google Account > Security > 2-Step Verification > App passwords
    'username'   => 'kei.automations@gmail.com',
    'password'   => 'uhid umoc govs yrqy',

    'from_email' => 'kafoodie@gmail.com',   // should be the same as username for Gmail
    'from_name'  => 'KaFoodie',
];
