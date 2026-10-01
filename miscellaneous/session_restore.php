<?php
/**
 * session_restore.php
 *
 * Railway uses an ephemeral filesystem — PHP sessions in /tmp get wiped on
 * container restart. This file restores the PHP session from the database
 * using a long-lived __st cookie that survives container restarts.
 *
 * Include this AFTER session_start() and AFTER database.php.
 * It is a no-op if the session is already valid.
 */

// Nothing to do if session is already populated
if (isset($_SESSION['id'], $_SESSION['user_type'], $_SESSION['session_token'])) {
    return;
}

// Nothing to do if no token cookie
$cookieToken = $_COOKIE['__st'] ?? '';
if (empty($cookieToken) || strlen($cookieToken) !== 64) {
    return;
}

// $pdo must already be available (included before this file)
if (!isset($pdo)) {
    return;
}

try {
    // Search all user tables for a matching token
    $tables = [
        'admin'            => ['id', 'username', 'profile_photo', 'login_ip'],
        'dentist_accounts' => ['id', 'first_name', 'last_name', 'email', 'profile_photo', 'login_ip'],
        'staff_accounts'   => ['id', 'first_name', 'last_name', 'email', 'staff_id', 'profile_photo', 'login_ip'],
        'patient_account'  => ['id', 'first_name', 'last_name', 'gmail', 'phone_number', 'age', 'gender', 'profile_photo', 'login_ip'],
    ];

    foreach ($tables as $table => $cols) {
        $stmt = $pdo->prepare("SELECT " . implode(',', $cols) . " FROM `$table` WHERE session_token = :token LIMIT 1");
        $stmt->execute(['token' => $cookieToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) continue;

        // Additional active checks
        if (in_array($table, ['dentist_accounts', 'staff_accounts'])) {
            $active = $pdo->prepare("SELECT is_active, is_deleted FROM `$table` WHERE id = :id LIMIT 1");
            $active->execute(['id' => $row['id']]);
            $status = $active->fetch();
            if (!$status || !$status['is_active'] || $status['is_deleted']) continue;
        }
        if ($table === 'patient_account') {
            $active = $pdo->prepare("SELECT is_deleted FROM patient_account WHERE id = :id LIMIT 1");
            $active->execute(['id' => $row['id']]);
            $status = $active->fetch();
            if (!$status || $status['is_deleted']) continue;
        }

        // Restore session based on user type
        $_SESSION['session_token'] = $cookieToken;

        if ($table === 'admin') {
            $_SESSION['id']            = $row['id'];
            $_SESSION['user_type']     = 'admin';
            $_SESSION['username']      = $row['username'];
            $_SESSION['user_name']     = $row['username'];
            $_SESSION['profile_photo'] = $row['profile_photo'] ?? null;
            $_SESSION['login_ip']      = $row['login_ip'] ?? null;

        } elseif ($table === 'dentist_accounts') {
            $_SESSION['id']            = $row['id'];
            $_SESSION['user_type']     = 'dentist';
            $_SESSION['username']      = $row['first_name'];
            $_SESSION['user_name']     = $row['first_name'] . ' ' . $row['last_name'];
            $_SESSION['user_email']    = $row['email'];
            $_SESSION['profile_photo'] = $row['profile_photo'] ?? null;
            $_SESSION['login_ip']      = $row['login_ip'] ?? null;

        } elseif ($table === 'staff_accounts') {
            $_SESSION['id']            = $row['id'];
            $_SESSION['user_type']     = 'staff';
            $_SESSION['username']      = $row['first_name'];
            $_SESSION['user_name']     = $row['first_name'] . ' ' . $row['last_name'];
            $_SESSION['user_email']    = $row['email'];
            $_SESSION['staff_id']      = $row['staff_id'];
            $_SESSION['profile_photo'] = $row['profile_photo'] ?? null;
            $_SESSION['login_ip']      = $row['login_ip'] ?? null;

        } elseif ($table === 'patient_account') {
            $_SESSION['id']            = $row['id'];
            $_SESSION['user_type']     = 'patient';
            $_SESSION['user_name']     = $row['first_name'] . ' ' . $row['last_name'];
            $_SESSION['user_email']    = $row['gmail'];
            $_SESSION['phone_number']  = $row['phone_number'];
            $_SESSION['first_name']    = $row['first_name'];
            $_SESSION['last_name']     = $row['last_name'];
            $_SESSION['age']           = $row['age'];
            $_SESSION['gender']        = $row['gender'];
            $_SESSION['profile_photo'] = $row['profile_photo'] ?? null;
            $_SESSION['login_ip']      = $row['login_ip'] ?? null;
        }

        // Refresh the cookie lifetime
        setcookie('__st', $cookieToken, [
            'expires'  => time() + 28800, // 8 hours
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        break; // Found a match — done
    }
} catch (PDOException $e) {
    // Silent fail — let normal auth handle it
}
