<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

$login_success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_success']);

$role = $_GET['role'] ?? 'customer';
if (!in_array($role, ['customer', 'shop'])) {
    $role = 'customer';
}
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

    <div class="login-brand <?php echo ($role === 'shop') ? 'vendor' : ''; ?>">
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
            <?php if ($role === 'shop'): ?>
                <h1>Grow your <span style="color:var(--orange)">food</span> business with KaFoodie</h1>
                <ul class="vendor-benefits">
                    <li class="vendor-benefit-item">
                        <span class="benefit-check">✓</span> Reach thousands of hungry customers nearby
                    </li>
                    <li class="vendor-benefit-item">
                        <span class="benefit-check">✓</span> Manage your menu and orders in one dashboard
                    </li>
                    <li class="vendor-benefit-item">
                        <span class="benefit-check">✓</span> Get verified fast and start selling in days
                    </li>
                </ul>
            <?php else: ?>
                <h1>Good food is one login away</h1>
                <p>Sign in to order from your favorite local vendors and track every delivery.</p>
            <?php endif; ?>
        </div>

        <div class="login-hero-image" style="<?php echo ($role === 'shop') ? 'display:none;' : ''; ?>">
            <img src="assets/landing/food1.jpg" alt="Filipino comfort food">
        </div>

        <div class="foot">© 2026 KaFoodie</div>
    </div>

    <div class="login-form-side">
        <div class="login-card-container">
            <div class="login-card">
                <h2><?php echo ($role === 'shop') ? 'Vendor login' : 'Welcome back, foodie'; ?></h2>
                <p class="sub"><?php echo ($role === 'shop') ? 'Manage your shop, menu and orders.' : 'Log in to continue your food adventure.'; ?></p>

                <?php if ($login_success): ?>
                    <div class="alert-success"><?php echo htmlspecialchars($login_success); ?></div>
                <?php endif; ?>

                <?php if ($login_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <form action="login_process.php" method="POST">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

                    <div class="field">
                        <label for="email"><?php echo ($role === 'shop') ? 'Business email' : 'Email'; ?></label>
                        <input type="email" id="email" name="email" placeholder="<?php echo ($role === 'shop') ? 'vendor@shop.com' : 'you@email.com'; ?>" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn-primary">Log in</button>
                </form>

                <div class="signup-note" style="text-align:center; margin-bottom:15px;">
                    <?php if ($role === 'shop'): ?>
                        Want to sell on KaFoodie? <a href="vendor_signup.php" style="color:var(--orange-dark); font-weight:bold;">Apply as a vendor</a>
                    <?php else: ?>
                        New to KaFoodie? <a href="signup.php">Create an account</a>
                    <?php endif; ?>
                </div>
                <?php if ($role === 'customer'): ?>
                    <p class="signup-note login-role-switch">Are you a vendor? <a href="login.php?role=shop">Vendor login</a></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

</body>
</html>
