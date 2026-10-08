<?php
// Email OTP helpers for signup (customers and vendors).
// Signup details wait in the `pending_signups` table. The real account is only
// created in `users` (and `shops`) after the correct code is entered.

require_once __DIR__ . '/mailer.php';

const OTP_LENGTH         = 6;
const OTP_VALID_MINUTES  = 10;
const OTP_MAX_ATTEMPTS   = 5;
const OTP_RESEND_SECONDS = 60;

function otp_generate(): string {
    return str_pad((string)random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/** Sends the code email. */
function otp_send_email(string $email, string $name, string $code, ?string &$error = null): bool {
    $content = '
      <p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
      <p>Use this code to verify your email address:</p>
      <p style="text-align:center;margin:24px 0;">
        <span style="display:inline-block;background:#FFE4D6;color:#B83B12;font-size:32px;font-weight:700;letter-spacing:8px;padding:14px 24px;border-radius:12px;">' . $code . '</span>
      </p>
      <p>This code expires in ' . OTP_VALID_MINUTES . ' minutes. If you did not try to sign up for KaFoodie, you can ignore this email.</p>';

    return send_mail($email, $name, 'Your KaFoodie verification code',
        mail_layout('Verify your email', $content), $error);
}

/**
 * Saves the signup details, emails the first code and returns the pending token
 * (kept in the session). Returns null and sets $error when it fails.
 * $d keys: role, name, email, password_hash, business_name, business_category, cuisine
 */
function otp_start_signup(mysqli $con, array $d, ?string &$error = null): ?string {
    $error = null;

    // Clean out old abandoned signups
    $con->query("DELETE FROM pending_signups WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    // Stop people from spamming codes to the same email
    $stmt = $con->prepare(
        "SELECT id FROM pending_signups
         WHERE email = ? AND last_sent_at > DATE_SUB(NOW(), INTERVAL " . OTP_RESEND_SECONDS . " SECOND)"
    );
    $stmt->bind_param('s', $d['email']);
    $stmt->execute();
    $stmt->store_result();
    $too_soon = $stmt->num_rows > 0;
    $stmt->close();
    if ($too_soon) {
        $error = 'A code was just sent to that email. Please wait a minute before trying again.';
        return null;
    }

    // Replace any older pending signup for this email
    $stmt = $con->prepare("DELETE FROM pending_signups WHERE email = ?");
    $stmt->bind_param('s', $d['email']);
    $stmt->execute();
    $stmt->close();

    $token    = bin2hex(random_bytes(32));
    $code     = otp_generate();
    $otp_hash = password_hash($code, PASSWORD_DEFAULT);

    $stmt = $con->prepare(
        "INSERT INTO pending_signups
           (token, role, name, email, password_hash, business_name, business_category, cuisine,
            otp_hash, otp_expires_at, last_sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . OTP_VALID_MINUTES . " MINUTE), NOW())"
    );
    $stmt->bind_param('sssssssss',
        $token, $d['role'], $d['name'], $d['email'], $d['password_hash'],
        $d['business_name'], $d['business_category'], $d['cuisine'], $otp_hash);
    $stmt->execute();
    $stmt->close();

    if (!otp_send_email($d['email'], $d['name'], $code, $mail_error)) {
        $stmt = $con->prepare("DELETE FROM pending_signups WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
        $error = 'We could not send the verification email. Please check the address and try again.';
        return null;
    }

    return $token;
}

/** The pending signup for a token, plus `expired` and `cooldown_left` (seconds). */
function otp_get_pending(mysqli $con, string $token): ?array {
    $stmt = $con->prepare(
        "SELECT *,
                (otp_expires_at <= NOW()) AS expired,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(),
                    DATE_ADD(last_sent_at, INTERVAL " . OTP_RESEND_SECONDS . " SECOND))) AS cooldown_left
         FROM pending_signups WHERE token = ? LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Sends a fresh code. Returns true on success. */
function otp_resend(mysqli $con, string $token, ?string &$error = null): bool {
    $error = null;
    $p = otp_get_pending($con, $token);
    if (!$p) {
        $error = 'Your signup session expired. Please sign up again.';
        return false;
    }
    if ((int)$p['cooldown_left'] > 0) {
        $error = 'Please wait ' . (int)$p['cooldown_left'] . ' seconds before requesting a new code.';
        return false;
    }

    $code = otp_generate();
    if (!otp_send_email($p['email'], $p['name'], $code, $mail_error)) {
        $error = 'We could not send the email. Please try again in a moment.';
        return false;
    }

    $hash = password_hash($code, PASSWORD_DEFAULT);
    $stmt = $con->prepare(
        "UPDATE pending_signups
         SET otp_hash = ?, attempts = 0,
             otp_expires_at = DATE_ADD(NOW(), INTERVAL " . OTP_VALID_MINUTES . " MINUTE),
             last_sent_at = NOW()
         WHERE token = ?"
    );
    $stmt->bind_param('ss', $hash, $token);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Checks the code. If it is right, creates the account and returns its role
 * ('customer' or 'shop'). Otherwise returns null and sets $error.
 */
function otp_check(mysqli $con, string $token, string $code, ?string &$error = null): ?string {
    $error = null;
    $p = otp_get_pending($con, $token);
    if (!$p) {
        $error = 'Your signup session expired. Please sign up again.';
        return null;
    }
    if ((int)$p['expired'] === 1) {
        $error = 'This code has expired. Please request a new one.';
        return null;
    }
    if ((int)$p['attempts'] >= OTP_MAX_ATTEMPTS) {
        $error = 'Too many wrong attempts. Please request a new code.';
        return null;
    }

    if (!password_verify($code, $p['otp_hash'])) {
        $stmt = $con->prepare("UPDATE pending_signups SET attempts = attempts + 1 WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
        $left = OTP_MAX_ATTEMPTS - ((int)$p['attempts'] + 1);
        $error = $left > 0
            ? 'That code is not correct. You have ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.'
            : 'Too many wrong attempts. Please request a new code.';
        return null;
    }

    // Correct code: create the real account
    $con->begin_transaction();
    try {
        $stmt = $con->prepare(
            "INSERT INTO users (name, email, password_hash, role, email_verified) VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('ssss', $p['name'], $p['email'], $p['password_hash'], $p['role']);
        $stmt->execute();
        $owner_id = $con->insert_id;
        $stmt->close();

        if ($p['role'] === 'shop') {
            $stmt = $con->prepare(
                "INSERT INTO shops (owner_id, shop_name, business_category, cuisine) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('isss', $owner_id, $p['business_name'], $p['business_category'], $p['cuisine']);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $con->prepare("DELETE FROM pending_signups WHERE id = ?");
        $stmt->bind_param('i', $p['id']);
        $stmt->execute();
        $stmt->close();

        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        $error = 'We could not create your account. The email may already be registered.';
        return null;
    }

    return $p['role'];
}

/** Deletes a pending signup (used when the person wants to change their email). */
function otp_cancel(mysqli $con, string $token): void {
    $stmt = $con->prepare("DELETE FROM pending_signups WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
}
