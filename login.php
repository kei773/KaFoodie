<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

$login_success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaFoodie — Log in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="login-wrap">

    <div class="login-brand">
        <div class="login-icon-pattern">
            <!-- Row 1 -->
            <img src="assets/images/drumstick.svg" class="icon-p1" alt="">
            <img src="assets/images/coffee.svg" class="icon-p2" alt="">
            <img src="assets/images/forkspoon.svg" class="icon-p3" alt="">
            <img src="assets/images/bowl.svg" class="icon-p4" alt="">
            <img src="assets/images/burger.svg" class="icon-p5" alt="">
            <!-- Row 2 -->
            <img src="assets/images/drumstick.svg" class="icon-p6" alt="">
            <img src="assets/images/coffee.svg" class="icon-p7" alt="">
            <img src="assets/images/forkspoon.svg" class="icon-p8" alt="">
            <img src="assets/images/bowl.svg" class="icon-p9" alt="">
            <img src="assets/images/burger.svg" class="icon-p10" alt="">
            <!-- Row 3 -->
            <img src="assets/images/drumstick.svg" class="icon-p11" alt="">
            <img src="assets/images/coffee.svg" class="icon-p12" alt="">
            <img src="assets/images/forkspoon.svg" class="icon-p13" alt="">
            <img src="assets/images/bowl.svg" class="icon-p14" alt="">
            <img src="assets/images/burger.svg" class="icon-p15" alt="">
            <!-- Row 4 -->
            <img src="assets/images/drumstick.svg" class="icon-p16" alt="">
            <img src="assets/images/coffee.svg" class="icon-p17" alt="">
            <img src="assets/images/forkspoon.svg" class="icon-p18" alt="">
            <img src="assets/images/bowl.svg" class="icon-p19" alt="">
            <img src="assets/images/burger.svg" class="icon-p20" alt="">
            <!-- Row 5 -->
            <img src="assets/images/drumstick.svg" class="icon-p21" alt="">
            <img src="assets/images/coffee.svg" class="icon-p22" alt="">
            <img src="assets/images/forkspoon.svg" class="icon-p23" alt="">
            <img src="assets/images/bowl.svg" class="icon-p24" alt="">
            <img src="assets/images/burger.svg" class="icon-p25" alt="">
        </div>

        <div class="brand-row">
            <img class="mark" src="assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
            <span class="brand-word">KaFoodie</span>
        </div>


        <div class="pitch">
            <h1>Good food is one login away</h1>
            <p>Sign in to order from your favorite local vendors and track every delivery.</p>
        </div>

        <div class="login-hero-image">
            <img src="assets/landing/food1.jpg" alt="Filipino comfort food">
        </div>
    </div>

    <div class="login-form-side">
        <div class="login-card-container">
            <div class="login-card">
                <h2>Welcome back, foodie</h2>
                <p class="sub">Log in to continue your food adventure.</p>

                <?php if ($login_success): ?>
                    <div class="alert-success"><?php echo htmlspecialchars($login_success); ?></div>
                <?php endif; ?>

                <?php if ($login_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <form action="login_process.php" method="POST">
                    <!-- Maintain backend role expectation -->
                    <input type="hidden" name="role" value="customer">

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="you@email.com" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn-primary">Log in</button>
                </form>

                <p class="signup-note">New to KaFoodie? <a href="signup.php">Create an account</a></p>
            </div>
        </div>

        <div class="login-footer">
            <span>Are you a vendor? <a href="login.php?role=vendor">Vendor login</a></span>
        </div>
    </div>

</div>

</body>
</html>
