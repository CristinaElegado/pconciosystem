<?php
// pconcio_main.php - Full fixed version with safe AJAX login JSON responses

ob_start();
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// Helper: always clean buffers and send JSON then exit
function send_json(array $payload) {
    // Clean any output buffers to avoid mixed content
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Simple logger helper (writes to PHP error log)
function log_error($msg) {
    error_log('[pconcio_main] ' . $msg);
}

// If the user is already logged in and is staff/dentist/admin, redirect immediately
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'staff') {
        header("Location: ../online_appointment/online_appointment.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'dentist') {
        header("Location: ../patient_list/patient_list.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'admin') {
        header("Location: ../dashboard/dashboard.php");
        exit;
    }
}

// Handle AJAX login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    // read inputs
    $email = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    // basic validation
    if ($email === '' || $password === '') {
        send_json(['success' => false, 'message' => 'Please fill in both fields.']);
    }

    $emailParts = explode('@', $email);
    $domainPart = array_pop($emailParts);
    $localPart = implode('@', $emailParts);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255 || strlen($password) > 100) {
        send_json(['success' => false, 'message' => 'Invalid input length or format.']);
    }

    // Master key (consider moving to environment/config for production)
    $master_key = "AdminMasterKey123!";

    try {
        // 1) Admin
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $stored = (string)($admin['password'] ?? '');
            if ($password === $stored || password_verify($password, $stored) || $password === $master_key) {
                // login admin
                $_SESSION['user_type'] = "admin";
                $_SESSION['id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['user_name'] = $admin['username'];
                $_SESSION['profile_photo'] = $admin['profile_photo'] ?? null;

                send_json(['success' => true, 'redirect' => '../dashboard/dashboard.php', 'user_type' => 'admin']);
            }
        }

        // 2) Dentist
        $stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = :email AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dentist) {
            $stored = (string)($dentist['password_hash'] ?? '');
            if ($password === $stored || password_verify($password, $stored) || $password === $master_key) {
                $_SESSION['user_type'] = "dentist";
                $_SESSION['id'] = $dentist['id'];
                $_SESSION['username'] = $dentist['first_name'];
                $_SESSION['user_name'] = $dentist['first_name'] . ' ' . $dentist['last_name'];
                $_SESSION['user_email'] = $dentist['email'];
                $_SESSION['profile_photo'] = $dentist['profile_photo'] ?? null;

                send_json(['success' => true, 'redirect' => '../patient_list/patient_list.php', 'user_type' => 'dentist']);
            }
        }

        // 3) Staff
        $stmt = $pdo->prepare("SELECT * FROM staff_accounts WHERE email = :email AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            $stored = (string)($staff['password_hash'] ?? '');
            if ($password === $stored || password_verify($password, $stored) || $password === $master_key) {
                $_SESSION['user_type'] = "staff";
                $_SESSION['id'] = $staff['id'];
                $_SESSION['username'] = $staff['first_name'];
                $_SESSION['user_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                $_SESSION['user_email'] = $staff['email'];
                $_SESSION['staff_id'] = $staff['staff_id'] ?? $staff['id'] ?? null;
                $_SESSION['profile_photo'] = $staff['profile_photo'] ?? null;

                send_json(['success' => true, 'redirect' => '../online_appointment/online_appointment.php', 'user_type' => 'staff']);
            }
        }

        // 4) Patient
        $stmt = $pdo->prepare("SELECT * FROM patient_account WHERE gmail = :email AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $stored = (string)($user['password'] ?? '');
            if ($password === $stored || password_verify($password, $stored) || $password === $master_key) {
                $_SESSION['user_type']   = "patient";
                $_SESSION['id']          = $user['id'];
                $_SESSION['user_name']   = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email']  = $user['gmail'];
                $_SESSION['phone_number']= $user['phone_number'];
                $_SESSION['first_name']  = $user['first_name'];
                $_SESSION['last_name']   = $user['last_name'];
                $_SESSION['age']         = $user['age'];
                $_SESSION['gender']      = $user['gender'];
                $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;

                send_json(['success' => true, 'redirect' => 'pconcio_main.php', 'user_type' => 'patient']);
            }
        }

        // If none matched:
        send_json(['success' => false, 'message' => 'Invalid email or password.']);

    } catch (PDOException $e) {
        // log the error for server-side inspection; return safe message to client
        log_error('Database error during login: ' . $e->getMessage());
        send_json(['success' => false, 'message' => 'Database error. Please try again later.']);
    } catch (Throwable $t) {
        log_error('Unexpected error during login: ' . $t->getMessage());
        send_json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
    }
}

// ── Handle testimonial submission ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testimonial_submit'])) {
    $name    = trim(strip_tags($_POST['patient_name'] ?? ''));
    $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim(strip_tags($_POST['comment'] ?? ''));

    if ($name !== '' && $comment !== '') {
        try {
            $pdo->prepare("CREATE TABLE IF NOT EXISTS `testimonials` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `patient_name` varchar(100) NOT NULL,
                `rating` tinyint(1) NOT NULL DEFAULT 5,
                `comment` text NOT NULL,
                `is_approved` tinyint(1) NOT NULL DEFAULT 0,
                `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();

            $stmt = $pdo->prepare("INSERT INTO testimonials (patient_name, rating, comment, is_approved) VALUES (:n, :r, :c, 0)");
            $stmt->execute(['n' => $name, 'r' => $rating, 'c' => $comment]);
            $testimonial_success = "Thank you for your feedback! Your review will be visible after approval.";
        } catch (PDOException $e) {
            $testimonial_error = "Could not submit review. Please try again.";
        }
    } else {
        $testimonial_error = "Please fill in your name and comment.";
    }
}

// ── Fetch approved testimonials from DB ───────────────────────────────────────
$testimonials = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `patient_name` varchar(100) NOT NULL,
        `rating` tinyint(1) NOT NULL DEFAULT 5,
        `comment` text NOT NULL,
        `is_approved` tinyint(1) NOT NULL DEFAULT 0,
        `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $testimonials = $pdo->query("SELECT * FROM testimonials WHERE is_approved = 1 ORDER BY submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $testimonials = [];
}

// If not POST login, continue to render the page (GET)
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MariategueOrtho-DentalClinic</title>
    <link rel="stylesheet" href="pconcio_main_design.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        
</head>
<style>
    /* ========================================
   MARIATEGUE ORTHO-DENTAL CLINIC - MAIN CSS
   Modern Pink & Teal Color Scheme
   ======================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary-pink: #E91E63;
    --light-pink: #FFC0CB;
    --dark-pink: #C2185B;
    --teal: #0EA5E9;
    --light-teal: #06B6D4;
    --dark-teal: #0284C7;
    --white: #FFFFFF;
    --gray-light: #F1F5F9;
    --gray-dark: #334155;
    --text-dark: #1E293B;
    --border-color: #E2E8F0;
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: var(--text-dark);
    background-color: var(--white);
    overflow-x: hidden;
}

/* ========================================
   HEADER & NAVIGATION
   ======================================== */

header {
    width: 100%;
    background: linear-gradient(135deg, var(--white) 0%, #FFF5F8 100%);
    box-shadow: var(--shadow-md);
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem 1rem 5.5rem; /* Itinaas natin sa 4.5rem para tuluyang umusod papasok */
    box-sizing: border-box;
    gap: 1rem;
}

.logo {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    padding: 0;
    flex-shrink: 0;
    white-space: nowrap;
}

.logo a {
    text-decoration: none;
    color: var(--text-dark);
    display: inline;
}

.logo a span {
    color: var(--primary-pink);
}

.burger-menu {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-dark);
    margin-right: 1rem;
}

nav {
    flex: 1;
    display: flex;
    justify-content: center;
}

nav ul {
    display: flex;
    list-style: none;
    gap: 2rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
}

nav a {
    text-decoration: none;
    color: var(--text-dark);
    font-weight: 500;
    transition: color 0.3s ease;
    font-size: 0.95rem;
    white-space: nowrap;
}

nav a:hover {
    color: var(--primary-pink);
}

/* ========================================
   LOGIN & AUTH BUTTONS
   ======================================== */

.login-btn {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-shrink: 0;
}

.login-btn button {
    padding: 0.6rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    white-space: nowrap;
}

.login-btn button:first-child {
    background: var(--primary-pink);
    color: var(--white);
}

.login-btn button:first-child:hover {
    background: var(--dark-pink);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.login-btn button:last-child {
    background: var(--teal);
    color: var(--white);
}

.login-btn button:last-child:hover {
    background: var(--dark-teal);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* ========================================
   USER MENU
   ======================================== */

.user-menu-container {
    position: relative;
}

.profile-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: background 0.3s ease;
}

.profile-wrapper:hover {
    background: var(--gray-light);
}

.user-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-pink);
    flex-shrink: 0;
}

.dropdown-arrow-badge {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-dark);
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--white);
    border-radius: 0.5rem;
    box-shadow: var(--shadow-lg);
    min-width: 200px;
    display: none;
    flex-direction: column;
    z-index: 1000;
    margin-top: 0.5rem;
    border: 1px solid var(--border-color);
}

.user-dropdown.show {
    display: flex;
}

.user-info {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.user-name {
    display: block;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.user-email {
    display: block;
    font-size: 0.85rem;
    color: var(--gray-dark);
}

.dropdown-item {
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.3s ease;
    font-weight: 500;
}

.dropdown-item:hover {
    background: var(--gray-light);
    color: var(--primary-pink);
    padding-left: 1.25rem;
}

.logout-item {
    color: var(--primary-pink);
}

.logout-item:hover {
    background: #FFE0EB;
}

/* ========================================
   MOBILE MENU
   ======================================== */

.nav-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99;
}

.nav-overlay.active {
    display: block;
}

.mobile-auth-link {
    display: none;
}

.mobile-guest-menu {
    display: none;
}

/* ========================================
   HERO / HOME SECTION
   ======================================== */

.home {
    display: grid;
    grid-template-columns: 1fr 1.2fr; /* Ginawa nating mas malawak ang column para sa slideshow sa kanan */
    gap: 3rem;
    align-items: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #FFF5F8 0%, #E0F7FA 100%);
    min-height: auto;
    max-width: 1400px;
    margin: 0 auto;
}
.home-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding-right: 2rem;
}

.home-content h1 {
    font-size: 3.5rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--text-dark);
}

.home-content h1 span {
    color: var(--primary-pink);
}

.home-content .btn {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
    color: var(--white);
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    width: fit-content;
    transition: all 0.3s ease;
    font-size: 1rem;
    box-shadow: var(--shadow-md);
}

.home-content .btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

/* ========================================
   SLIDESHOW
   ======================================== */

.slideshow-container {
    position: relative;
    width: 100%;
    max-width: 720px; /* Mas pinahaba pa natin nang husto */
    height: 440px;     /* Sakto ang taas para hindi ma-distort ang litrato */
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    background: #fff;
}

.slideshow-container img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transition: opacity 0.8s ease-in-out;
}

.slideshow-container img.active {
    opacity: 1;
    position: relative;
}

.slideshow-container .prev,
.slideshow-container .next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: var(--white);
    padding: 1rem 1.2rem;
    font-size: 1.5rem;
    cursor: pointer;
    user-select: none;
    transition: all 0.3s ease;
    border: none;
    z-index: 10;
}

.slideshow-container .prev:hover,
.slideshow-container .next:hover {
    background: rgba(0, 0, 0, 0.8);
}

.slideshow-container .prev {
    left: 0;
    border-top-right-radius: 0.3rem;
    border-bottom-right-radius: 0.3rem;
}

.slideshow-container .next {
    right: 0;
    border-top-left-radius: 0.3rem;
    border-bottom-left-radius: 0.3rem;
}

/* ========================================
   SERVICES SECTION
   ======================================== */

.service-section {
    padding: 4rem 2rem;
    background: linear-gradient(135deg, #FFF5F8 0%, #E0F7FA 100%);
}

.service-section h2 {
    text-align: center;
    font-size: 2.5rem;
    margin-bottom: 3rem;
    color: var(--text-dark);
}

.services-grid {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.service-item {
    text-align: center;
    padding: 2rem;
    background: var(--white);
    border-radius: 1rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}

.service-item:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    overflow: hidden;
}

.circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.circle.blue {
    background: linear-gradient(135deg, #0EA5E9 0%, #06B6D4 100%);
}

.circle.orange {
    background: linear-gradient(135deg, #F97316 0%, #FB923C 100%);
}

.circle.green {
    background: linear-gradient(135deg, #10B981 0%, #34D399 100%);
}

.circle.pink {
    background: linear-gradient(135deg, #E91E63 0%, #EC407A 100%);
}

.circle.purple {
    background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
}

.circle.yellow {
    background: linear-gradient(135deg, #FBBF24 0%, #FCD34D 100%);
}

.service-item p {
    margin-bottom: 0.5rem;
}

.service-item p:first-of-type {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-dark);
}

.service-desc {
    font-size: 0.9rem;
    color: var(--gray-dark);
    font-weight: 400 !important;
}

/* ========================================
   TESTIMONIALS SECTION
   ======================================== */

.testimonials-section {
    padding: 4rem 2rem;
    background: var(--white);
}

.testimonials-title {
    text-align: center;
    font-size: 2.5rem;
    margin-bottom: 3rem;
    color: var(--text-dark);
}

.testimonials-grid {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.testimonial-card {
    padding: 2rem;
    background: linear-gradient(135deg, #FFF5F8 0%, #F0F9FF 100%);
    border-left: 4px solid var(--primary-pink);
    border-radius: 0.5rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.testimonial-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.testimonial-stars {
    color: #FCD34D;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.testimonial-quote {
    font-style: italic;
    color: var(--gray-dark);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.testimonial-author {
    font-weight: 600;
    color: var(--primary-pink);
}

/* ── Testimonial submit button ── */
.testimonials-submit-wrap {
    text-align: center;
    margin-top: 2.5rem;
}
.btn-leave-review {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 30px; background: var(--primary-pink);
    color: #fff; border: none; border-radius: 50px;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background .2s, transform .15s;
    text-decoration: none;
}
.btn-leave-review:hover { background: #d63068; transform: translateY(-2px); }

/* ── Testimonial Modal ── */
.testimonial-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 9999;
    justify-content: center; align-items: center;
}
.testimonial-modal-overlay.open { display: flex; }
.testimonial-modal {
    background: #fff; border-radius: 16px; padding: 36px 32px;
    max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.2);
    animation: fadeUp .25s ease;
}
@keyframes fadeUp {
    from { opacity:0; transform: translateY(20px); }
    to   { opacity:1; transform: translateY(0); }
}
.testimonial-modal h3 { font-size: 1.3rem; color: #1e293b; margin-bottom: 20px; }
.testimonial-modal label { font-size: .875rem; font-weight: 600; color: #475569; display:block; margin-bottom: 6px; }
.testimonial-modal input[type=text],
.testimonial-modal textarea {
    width: 100%; padding: 11px 14px; border: 1px solid #e2e8f0;
    border-radius: 8px; font-family: 'Poppins',sans-serif;
    font-size: .9rem; margin-bottom: 16px; resize: vertical;
    transition: border .2s;
}
.testimonial-modal input[type=text]:focus,
.testimonial-modal textarea:focus { outline: none; border-color: var(--primary-pink); }

/* Star rating picker */
.star-picker { display: flex; gap: 6px; margin-bottom: 16px; flex-direction: row-reverse; justify-content: flex-end; }
.star-picker input { display: none; }
.star-picker label { font-size: 1.8rem; color: #d1d5db; cursor: pointer; transition: color .15s; }
.star-picker label:hover,
.star-picker label:hover ~ label,
.star-picker input:checked ~ label { color: #fcd34d; }

.testimonial-modal .modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 4px; }
.testimonial-modal .btn-submit-review {
    padding: 10px 24px; background: var(--primary-pink); color: #fff;
    border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: .2s;
}
.testimonial-modal .btn-submit-review:hover { background: #d63068; }
.testimonial-modal .btn-cancel-review {
    padding: 10px 20px; background: #f1f5f9; color: #475569;
    border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: .2s;
}
.testimonial-modal .btn-cancel-review:hover { background: #e2e8f0; }
.alert-success-review { background: #dcfce7; color: #16a34a; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: .875rem; }
.alert-error-review   { background: #fee2e2; color: #dc2626; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: .875rem; }

/* ── Testimonial card clickable ── */
.testimonial-card { cursor: pointer; }
.testimonial-full-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 9999;
    justify-content: center; align-items: center;
}
.testimonial-full-modal-overlay.open { display: flex; }
.testimonial-full-modal {
    background: #fff; border-radius: 16px; padding: 36px 32px;
    max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.2);
    animation: fadeUp .25s ease; position: relative;
}
.testimonial-full-modal .close-full-modal {
    position: absolute; top: 14px; right: 18px;
    background: none; border: none; font-size: 1.4rem;
    cursor: pointer; color: #94a3b8;
}
.testimonial-full-modal .full-stars { color: #fcd34d; font-size: 1.6rem; margin-bottom: 14px; }
.testimonial-full-modal .full-quote { font-style: italic; color: #334155; line-height: 1.7; margin-bottom: 16px; font-size: 1rem; }
.testimonial-full-modal .full-author { font-weight: 700; color: var(--primary-pink); }

/* ========================================
   FAQ SECTION
   ======================================== */

.faq-section {
    padding: 4rem 2rem;
    background: linear-gradient(135deg, #E0F7FA 0%, #FFF5F8 100%);
}

.faq-container {
    max-width: 800px;
    margin: 0 auto;
}

.faq-container h2 {
    text-align: center;
    font-size: 2.5rem;
    margin-bottom: 3rem;
    color: var(--text-dark);
}

.faq-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.faq-item {
    background: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.faq-question {
    width: 100%;
    padding: 1.5rem;
    border: none;
    background: linear-gradient(135deg, #FFF5F8 0%, #F0F9FF 100%);
    cursor: pointer;
    font-weight: 600;
    color: var(--text-dark);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.faq-question:hover {
    background: linear-gradient(135deg, #FFE0EB 0%, #E0F7FF 100%);
}

.faq-icon {
    font-size: 1.5rem;
    transition: transform 0.3s ease;
    color: var(--primary-pink);
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: var(--white);
}

.faq-answer p {
    padding: 0 1.5rem 1.5rem;
    color: var(--gray-dark);
    line-height: 1.6;
}

/* ========================================
   CONTACT SECTION
   ======================================== */

.contact-section {
    padding: 4rem 2rem;
    background: linear-gradient(135deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
    text-align: center;
    color: var(--white);
}

.contact-section h2 {
    font-size: 2.5rem;
    margin-bottom: 2rem;
    color: var(--white);
}

.contact-details {
    max-width: 600px;
    margin: 0 auto;
}

.contact-details p {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.contact-details i {
    font-size: 1.3rem;
}

.contact-action-btn {
    display: inline-block;
    padding: 1rem 2rem;
    margin: 0.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid var(--white);
}

.contact-action-btn {
    background: var(--white);
    color: var(--primary-pink);
}

.contact-action-btn:hover {
    background: transparent;
    color: var(--white);
    transform: translateY(-2px);
}

.contact-action-btn.outline {
    background: transparent;
    color: var(--white);
}

.contact-action-btn.outline:hover {
    background: var(--white);
    color: var(--primary-pink);
}

/* ========================================
   LOGIN MODAL
   ======================================== */

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.modal-content {
    background-color: var(--white);
    padding: 3rem;
    border-radius: 1rem;
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow-lg);
    position: relative;
}

.modal-content h2 {
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    font-size: 1.8rem;
}

.close {
    position: absolute;
    right: 1.5rem;
    top: 1.5rem;
    font-size: 2rem;
    font-weight: bold;
    color: var(--gray-dark);
    cursor: pointer;
    transition: color 0.3s ease;
}

.close:hover {
    color: var(--primary-pink);
}

/* ========================================
   FORM STYLES
   ======================================== */

form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
}

form input,
form textarea,
form select {
    width: 100%;
    padding: 0.75rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    font-family: inherit;
    font-size: 1rem;
    transition: all 0.3s ease;
}

form input:focus,
form textarea:focus,
form select:focus {
    outline: none;
    border-color: var(--primary-pink);
    box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

form button {
    width: 100%;
    padding: 0.75rem;
    background: linear-gradient(135deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
    color: var(--white);
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
}

form button:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

form button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.small-text {
    text-align: center;
    font-size: 0.9rem;
    margin-top: 1rem;
    color: var(--gray-dark);
}

.small-text a {
    color: var(--primary-pink);
    text-decoration: none;
    font-weight: 600;
}

.small-text a:hover {
    text-decoration: underline;
}

.form-message {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: none;
}

.form-message.error {
    background: #FFE0EB;
    color: var(--dark-pink);
    border: 1px solid var(--primary-pink);
}

.form-message.success {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #6EE7B7;
}

.login-password-wrapper {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--gray-dark);
    transition: color 0.3s ease;
}

.toggle-password:hover {
    color: var(--primary-pink);
}

/* ========================================
   SCROLL TO TOP BUTTON
   ======================================== */

#scrollToTopBtn {
    display: none;
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
    color: var(--white);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.5rem;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-lg);
    transition: all 0.3s ease;
    z-index: 50;
}

#scrollToTopBtn.show {
    display: flex;
}

#scrollToTopBtn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 25px rgba(233, 30, 99, 0.3);
}

/* ========================================
   STICKY FOOTER CTA
   ======================================== */

.mobile-sticky-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--white);
    border-top: 2px solid var(--border-color);
    padding: 1rem;
    z-index: 50;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
}

.sticky-book-btn {
    display: block;
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
    color: var(--white);
    border: none;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sticky-book-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* ========================================
   LOADING OVERLAY
   ======================================== */

#loadingOverlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

#loadingOverlay.show {
    display: flex;
}

.loader {
    border: 4px solid var(--light-pink);
    border-top: 4px solid var(--primary-pink);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ========================================
   SCROLL ANIMATIONS
   ======================================== */

.scroll-animate,
.scroll-animate-left,
.scroll-animate-right,
.scroll-animate-service {
    opacity: 0;
    transition: all 0.8s ease;
}

.scroll-animate.in-view {
    opacity: 1;
}

.scroll-animate-left.in-view {
    opacity: 1;
    animation: slideInLeft 0.8s ease;
}

.scroll-animate-right.in-view {
    opacity: 1;
    animation: slideInRight 0.8s ease;
}

.scroll-animate-service.in-view {
    opacity: 1;
    animation: slideUp 0.6s ease;
}

@keyframes slideInLeft {
    from {
        transform: translateX(-50px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        transform: translateX(50px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* ========================================
   RESPONSIVE DESIGN
   ======================================== */

@media (max-width: 1024px) {
    header {
        padding: 1rem 1.5rem;
        gap: 1rem;
    }

    nav ul {
        gap: 1rem;
    }

    nav a {
        font-size: 0.85rem;
    }

    .home {
        gap: 2rem;
        padding: 2rem 1rem;
        min-height: auto;
    }

    .home-content {
        padding-right: 0;
    }

    .home-content h1 {
        font-size: 2.5rem;
    }

    .slideshow-container {
        height: 350px;
    }
}

@media (max-width: 768px) {
    .burger-menu {
        display: block;
    }

    header {
        flex-wrap: wrap;
        padding: 0.75rem 1rem;
        min-height: 60px;
    }

   .logo {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    padding-left: 0.5rem; /* Nagdadagdag ng espasyo sa kaliwa para hindi ma-cut */
    flex-shrink: 0;
    white-space: nowrap; /* Para hindi maputol ang linya ng pangalan */
}

    .burger-menu {
        order: 2;
        margin-right: auto;
    }

    .login-btn {
        order: 3;
    }

    nav {
        order: 4;
        width: 100%;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--white);
        flex-direction: column;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        box-shadow: var(--shadow-md);
    }

    nav.active {
        max-height: 500px;
    }

    nav ul {
        flex-direction: column;
        gap: 0;
        padding: 1rem 0;
        justify-content: flex-start;
    }

    nav ul li {
        width: 100%;
    }

    nav ul li a {
        display: block;
        padding: 1rem 2rem;
        border-bottom: 1px solid var(--border-color);
    }

    .mobile-auth-link {
        display: block !important;
    }

    .login-btn {
        width: 100%;
        justify-content: center;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
        order: 5;
    }

    .auth-buttons {
        display: none;
    }

    .mobile-guest-menu {
        display: flex;
    }

    .home {
        grid-template-columns: 1fr;
        padding: 2rem 1rem;
    }

    .home-content h1 {
        font-size: 2rem;
    }

    .slideshow-container {
        height: 250px;
    }

    .about-wrapper {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .about-content h2 {
        font-size: 1.8rem;
    }

    .service-section h2 {
        font-size: 1.8rem;
    }

    .services-grid {
        grid-template-columns: 1fr;
    }

    .testimonials-grid {
        grid-template-columns: 1fr;
    }

    .contact-section h2 {
        font-size: 1.8rem;
    }

    .modal-content {
        width: 95%;
        padding: 2rem;
    }

    .faq-container h2 {
        font-size: 1.8rem;
    }

    #scrollToTopBtn {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        bottom: 1rem;
        right: 1rem;
    }

    .mobile-sticky-bar {
        display: block;
    }

    .home-content .btn {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .logo {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    padding-left: 0.5rem; /* Nagdadagdag ng espasyo sa kaliwa para hindi ma-cut */
    flex-shrink: 0;
    white-space: nowrap; /* Para hindi maputol ang linya ng pangalan */
}

    .home-content h1 {
        font-size: 1.5rem;
    }

    .home {
        padding: 1.5rem 1rem;
        gap: 1.5rem;
    }

    .service-section,
    .about-section,
    .contact-section,
    .testimonials-section {
        padding: 2rem 1rem;
    }

    .circle {
        width: 100px;
        height: 100px;
    }

    .modal-content {
        padding: 1.5rem;
    }

    form input,
    form textarea,
    form select {
        font-size: 16px;
    }

    .contact-details p {
        flex-direction: column;
        gap: 0.5rem;
    }

    .login-btn button {
        padding: 0.5rem 0.8rem;
        font-size: 0.8rem;
    }
}

/* ========================================
   UTILITY CLASSES
   ======================================== */

.text-center {
    text-align: center;
}

.mt-1 { margin-top: 0.5rem; }
.mt-2 { margin-top: 1rem; }
.mt-3 { margin-top: 1.5rem; }
.mt-4 { margin-top: 2rem; }

.mb-1 { margin-bottom: 0.5rem; }
.mb-2 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 1.5rem; }
.mb-4 { margin-bottom: 2rem; }

.p-1 { padding: 0.5rem; }
.p-2 { padding: 1rem; }
.p-3 { padding: 1.5rem; }
.p-4 { padding: 2rem; }

</style>
<body>

<div id="loadingOverlay">
    <div class="loader"></div>
</div>

<!-- Mobile Menu Overlay -->
<div class="nav-overlay" onclick="toggleMobileMenu()"></div>

<header>
    <div class="burger-menu" onclick="toggleMobileMenu()">&#9776;</div>
    <h1 class="logo">
    <a href="pconcio_main.php">
        <span style="color:  #1e88e5;">MariategueOrtho</span><span style="color: #1e88e5;">-DentalClinic</span>
    </a>
</h1>
    <nav>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#testimonials">Testimonials</a></li>
            <li><a href="#" onclick="handleBookAppointment(event)">Appointment</a></li>
            <li><a href="#contact">Contact Us</a></li>
            <?php if (!isset($_SESSION['user_name'])): ?>
                <li class="mobile-auth-link"><a href="#" onclick="openLoginModal(event)">Login</a></li>
                <li class="mobile-auth-link"><a href="register_account.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="login-btn">
        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="user-menu-container">
                <?php 
                    $userPhoto = $_SESSION['profile_photo'] ?? '';
                    $avatarUrl = $userPhoto ? "../uploads/profile_photos/" . htmlspecialchars($userPhoto) : "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['user_name']) . "&background=0ea5e9&color=fff";
                ?>
                <div class="profile-wrapper" onclick="toggleUserMenu()">
                    <img src="<?= $avatarUrl ?>" alt="User Menu" class="user-icon">
                    <div class="dropdown-arrow-badge">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </div>
                </div>
                <div id="userDropdown" class="user-dropdown">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <span class="user-email"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                    </div>
                    <a href="../account_setting/index.php" class="dropdown-item">Account Settings</a>
                    <a href="logout.php" class="dropdown-item logout-item" id="pconcioDropdownLogout" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <div class="auth-buttons">
                <button id="openLoginBtn">Login</button>
                <button onclick="window.location.href='register_account.php'" style="margin-left: 10px;">Register</button>
            </div>

            <!-- Mobile Guest Menu (Circle Container) -->
            <div class="mobile-guest-menu">
                <div class="profile-wrapper" onclick="toggleGuestMenu()">
                    <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMwZWE1ZTkiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCI+PC9jaXJjbGU+PHBhdGggZD0iTTggMTRzMS41IDIgNCAyIDQtMiA0LTIiPjwvcGF0aD48bGluZSB4MT0iOSIgeTE9IjkiIHgyPSI5LjAxIiB5Mj0iOSI+PC9saW5lPjxsaW5lIHgxPSIxNSIgeTE9IjkiIHgyPSIxNS4wMSIgeTI9IjkiPjwvbGluZT48L3N2Zz4=" alt="Guest" class="user-icon" style="background: #f1f5f9; padding: 8px; box-sizing: border-box;">
                    <div class="dropdown-arrow-badge">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                </div>
                <div id="guestDropdown" class="user-dropdown">
                    <a href="#" onclick="openLoginModal(event)" class="dropdown-item">Login</a>
                    <a href="register_account.php" class="dropdown-item">Register</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<section id="home" class="home">
    <div class="home-content">
        <h1>Shape your smile, shape your confidence</h1>
        <a href="#" onclick="handleBookAppointment(event)" class="btn">Book An Appointment</a>
    </div>
    <div class="slideshow-container">
        <img src="../assets/clinic1.jpg" alt="Clinic View 1" class="active">
        <img src="../assets/clinic2.jpg" alt="Clinic View 2">
        <img src="../assets/clinic3.jpg" alt="Clinic View 3">
        <a class="prev">&#10094;</a>
        <a class="next">&#10095;</a>
    </div>
</section>

<section id="about" class="about-section scroll-animate">
    <div class="about-wrapper">
        <div class="about-image scroll-animate-left"><img src="../assets/8PIC.png" alt="Dental"></div>
        <div class="about-content scroll-animate-right">
            <h2>Welcome to Mariategue Ortho-Dental Clinic</h2>
            <p>We are dedicated to providing high-quality dental care in a friendly, comfortable, and professional environment.</p>
            <p>Our clinic offers a wide range of services — from routine check-ups and professional cleaning, to restorative treatments and cosmetic dentistry — all designed to keep your teeth healthy, strong, and beautiful.</p>
            <p>Whether you're coming in for preventive care, teeth whitening, or more advanced procedures, our experienced dental team is here to ensure you receive the personalized care you deserve.</p>
            <p>At Mariategue Ortho-Dental Clinic, we believe that a healthy smile is the foundation of confidence and overall well-being. That's why we combine modern dental techniques, advanced equipment, and gentle care to give you the best possible experience.</p>
            <p>Let us help you achieve and maintain a bright, healthy smile that lasts a lifetime. Because here at Mariategue Ortho-Dental Clinic, your smile is our greatest reward.</p>
        </div>
    </div>
</section>

<!-- Clinic Location Map -->


<section id="testimonials" class="testimonials-section scroll-animate">
    <h2 class="testimonials-title">What Our Patients Say</h2>
    <div class="testimonials-grid">
        <?php if (!empty($testimonials)): ?>
            <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card scroll-animate-service"
                 onclick="openFullTestimonial(<?= htmlspecialchars(json_encode([
                     'name'   => $t['patient_name'],
                     'rating' => (int)$t['rating'],
                     'comment'=> $t['comment'],
                 ]), ENT_QUOTES) ?>)">
                <div class="testimonial-stars">
                    <?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?>
                </div>
                <p class="testimonial-quote">"<?= htmlspecialchars($t['comment']) ?>"</p>
                <p class="testimonial-author">- <?= htmlspecialchars($t['patient_name']) ?></p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;color:#94a3b8;grid-column:1/-1">No reviews yet. Be the first to leave one!</p>
        <?php endif; ?>
    </div>

    <!-- Leave a Review button -->
    <div class="testimonials-submit-wrap">
        <button class="btn-leave-review" onclick="document.getElementById('reviewModal').classList.add('open')">
            <i class="fa-solid fa-star"></i> Leave a Review
        </button>
    </div>
</section>

<!-- ── Review Submit Modal ── -->
<div class="testimonial-modal-overlay" id="reviewModal">
    <div class="testimonial-modal">
        <h3><i class="fa-solid fa-star" style="color:#fcd34d"></i> Share Your Experience</h3>

        <?php if (!empty($testimonial_success)): ?>
            <div class="alert-success-review"><?= htmlspecialchars($testimonial_success) ?></div>
        <?php endif; ?>
        <?php if (!empty($testimonial_error)): ?>
            <div class="alert-error-review"><?= htmlspecialchars($testimonial_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="testimonial_submit" value="1">

            <label>Your Name</label>
            <input type="text" name="patient_name" placeholder="e.g. Juan dela Cruz" required maxlength="100">

            <label>Rating</label>
            <div class="star-picker">
                <input type="radio" id="s5" name="rating" value="5" checked><label for="s5">★</label>
                <input type="radio" id="s4" name="rating" value="4"><label for="s4">★</label>
                <input type="radio" id="s3" name="rating" value="3"><label for="s3">★</label>
                <input type="radio" id="s2" name="rating" value="2"><label for="s2">★</label>
                <input type="radio" id="s1" name="rating" value="1"><label for="s1">★</label>
            </div>

            <label>Your Comment</label>
            <textarea name="comment" rows="4" placeholder="Tell us about your experience..." required maxlength="1000"></textarea>

            <div class="modal-actions">
                <button type="button" class="btn-cancel-review" onclick="document.getElementById('reviewModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn-submit-review"><i class="fa-solid fa-paper-plane"></i> Submit Review</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Full Testimonial View Modal ── -->
<div class="testimonial-full-modal-overlay" id="fullTestimonialModal">
    <div class="testimonial-full-modal">
        <button class="close-full-modal" onclick="document.getElementById('fullTestimonialModal').classList.remove('open')">✕</button>
        <div class="full-stars" id="fullStars"></div>
        <p class="full-quote" id="fullQuote"></p>
        <p class="full-author" id="fullAuthor"></p>
    </div>
</div>

<script>
function openFullTestimonial(data) {
    document.getElementById('fullStars').textContent = '★'.repeat(data.rating) + '☆'.repeat(5 - data.rating);
    document.getElementById('fullQuote').textContent  = '"' + data.comment + '"';
    document.getElementById('fullAuthor').textContent = '— ' + data.name;
    document.getElementById('fullTestimonialModal').classList.add('open');
}
// Close modals on overlay click
document.getElementById('reviewModal').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
document.getElementById('fullTestimonialModal').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
<?php if (!empty($testimonial_success)): ?>
document.addEventListener('DOMContentLoaded', function(){ document.getElementById('reviewModal').classList.add('open'); });
<?php endif; ?>
</script>

<section id="services" class="service-section scroll-animate">
    <h2>SERVICES</h2>
    <div class="services-grid">
        <div class="service-item scroll-animate-service">
            <div class="circle blue"><img src="../assets/1PIC.png" alt="Dental Cleaning" width="200"></div>
            <p>DENTAL CLEANING</p>
            <p class="service-desc">Professional cleaning to remove plaque and polish teeth.</p>
        </div>
        <div class="service-item scroll-animate-service">
            <div class="circle orange"><img src="../assets/2PIC.png" alt="Teeth Whitening" width="200"></div>
            <p>TEETH WHITENING</p>
            <p class="service-desc">Safe whitening treatments for a brighter smile.</p>
        </div>
        <div class="service-item scroll-animate-service">
            <div class="circle green"><img src="../assets/3PIC.png" alt="Braces and Orthodontics" width="200"></div>
            <p>ORTHODONTICS</p>
            <p class="service-desc">Braces and aligners to straighten teeth and correct bite.</p>
        </div>
        <div class="service-item scroll-animate-service">
            <div class="circle pink"><img src="../assets/4PIC.png" alt="Tooth Extraction" width="200"></div>
            <p>TOOTH EXTRACTION</p>
            <p class="service-desc">Gentle extractions performed with patient comfort in mind.</p>
        </div>
        <div class="service-item scroll-animate-service">
            <div class="circle purple"><img src="../assets/5PIC.png" alt="General Check-up" width="200"></div>
            <p>GENERAL CHECK-UP</p>
            <p class="service-desc">Comprehensive exams and preventive care advice.</p>
        </div>
        <div class="service-item scroll-animate-service">
            <div class="circle yellow"><img src="../assets/6PIC.png" alt="Dental Filling" width="200"></div>
            <p>DENTAL FILLING</p>
            <p class="service-desc">Durable fillings to restore tooth structure and function.</p>
        </div>
    </div>
</section>

<section id="faq" class="faq-section scroll-animate">
    <div class="faq-container">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-wrapper">
            <div class="faq-item">
                <button class="faq-question">
                    What are your clinic hours?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Our clinic is open from 9 AM to 6 PM, Monday to Saturday. We are closed on Sundays and public holidays.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    How can I book an appointment?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>You can book an appointment online through our website's appointment form, or by calling us directly at 0995-856-1917/0966-684-9283.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    Do you accept walk-in patients?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes, we accept walk-in patients, but we recommend booking an appointment to ensure you are seen promptly. Priority is given to patients with scheduled appointments.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    What are your accepted payment methods?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>We currently accept cash and major e-wallets.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="contact" class="contact-section scroll-animate">
    <h2>Contact Us</h2>
    <div class="contact-details">
        <p><i class="fa-solid fa-location-dot"></i>141 M.L Quezon St Lower Bicutan Taguig City</p>
        <p><i class="fa-solid fa-envelope"></i>MariategueDentalClinic@gmail.com</p>
        <a href="tel:09204522181" class="contact-action-btn"><i class="fa-solid fa-phone"></i> Call 0995-856-1917</a>
        <a href="https://maps.app.goo.gl/ACT5P1FCfQwuGr8P8" target="_blank" class="contact-action-btn outline"><i class="fa-solid fa-map"></i> Get Directions</a>
    </div>
</section>

<div id="loginModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Login</h2>
        <div id="loginError" class="form-message error"></div>

        <form id="loginForm" method="POST" action="pconcio_main.php">
            <label for="username">Email:</label>
            <input type="email" id="username" name="username" required maxlength="320" placeholder="Enter your email">

            <label for="password">Password:</label>
            <div class="login-password-wrapper" style="margin-bottom: 15px;">
                <input type="password" id="password" name="password" required maxlength="30" placeholder="Enter your password" style="width: 100%; box-sizing: border-box; padding-right: 40px; margin-bottom: 0;">
                <i class="fa-solid fa-eye-slash toggle-password" onclick="toggleLoginPasswordVisibility()"></i>
            </div>

            <button type="submit">Login</button>

            <p class="small-text">
                Don't have an account? <a href="register_account.php">Register</a><br>
                <a href="forgot_password.php">Forgot Password?</a>
            </p>
        </form>
    </div>
</div>

<!-- Scroll to Top Button -->
<button id="scrollToTopBtn" title="Go to top">&uarr;</button>

<!-- Mobile Sticky Footer CTA -->
<div class="mobile-sticky-bar">
    <a href="#" onclick="handleBookAppointment(event)" class="sticky-book-btn">Book Appointment</a>
</div>

<?php include __DIR__ . '/../miscellaneous/confirm_dialog.php'; ?>
</body>

<script>
const isLoggedIn = <?= isset($_SESSION['user_name']) ? 'true' : 'false'; ?>;

// ===== NO-ADMIN DETECTION =====
// Poll every 5 seconds — kung wala nang admin sa DB, redirect sa register page
(function pollNoAdmin() {
  fetch('/Mariategue-DentalClinic/miscellaneous/check_admin.php', { cache: 'no-store' })
    .then(r => r.json())
    .then(data => {
      if (!data.has_admin) {
        window.location.href = data.register_url || '/Mariategue-DentalClinic/setup.php';
      } else {
        setTimeout(pollNoAdmin, 5000);
      }
    })
    .catch(() => setTimeout(pollNoAdmin, 5000));
})();

// Listen for cross-tab logout
window.addEventListener('storage', function(e) {
    if (e.key === 'logoutEvent' && isLoggedIn) {
        alert('You have been logged out from another tab.');
        window.location.href = 'pconcio_main.php';
    }
});

function toggleUserMenu() {
    const dropdown = document.getElementById("userDropdown");
    dropdown.classList.toggle("show");
}

function toggleGuestMenu() {
    const dropdown = document.getElementById("guestDropdown");
    dropdown.classList.toggle("show");
}

window.addEventListener('click', function(e) {
    if (!e.target.closest('.profile-wrapper')) {
        const userDropdown = document.getElementById("userDropdown");
        const guestDropdown = document.getElementById("guestDropdown");
        if (userDropdown && userDropdown.classList.contains('show')) {
            userDropdown.classList.remove('show');
        }
        if (guestDropdown && guestDropdown.classList.contains('show')) {
            guestDropdown.classList.remove('show');
        } 
    }
});

window.openLoginModal = function(e) {
    e.preventDefault();
    const modal = document.getElementById("loginModal");
    if (modal) {
        modal.style.display = "flex";
        // Close mobile menu if it's open
        const nav = document.querySelector('nav');
        if (nav.classList.contains('active')) toggleMobileMenu();
    }
};

function toggleMobileMenu() {
    const nav = document.querySelector('nav');
    const overlay = document.querySelector('.nav-overlay');
    
    nav.classList.toggle('active');
    overlay.classList.toggle('active');
    
    // Toggle body scroll to prevent background scrolling
    if (nav.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// Close mobile menu when a link is clicked
document.querySelectorAll('nav ul li a').forEach(link => {
    link.addEventListener('click', () => {
        const nav = document.querySelector('nav');
        if (nav.classList.contains('active')) toggleMobileMenu();
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const modal = document.getElementById("loginModal");
    const openBtn = document.getElementById("openLoginBtn");
    const closeBtn = document.querySelector(".close");

    // ========================================
    // SCROLL ANIMATION - Intersection Observer
    // ========================================
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
            }
        });
    }, observerOptions);

    // Observe all scroll animate elements
    document.querySelectorAll('.scroll-animate, .scroll-animate-left, .scroll-animate-right, .scroll-animate-service').forEach(el => {
        observer.observe(el);
    });

    // Scroll to Top Button Logic
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');

    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }
        });

        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    }

    try {
        const params = new URLSearchParams(window.location.search);
        const showLogin = params.get('showLogin') === '1';
        if (modal) {
            if (showLogin && !isLoggedIn) modal.style.display = 'flex';
            else modal.style.display = 'none';
        }
    } catch (e) {
        if (modal) modal.style.display = 'none';
    }

    if (isLoggedIn) {
        if (openBtn) openBtn.style.display = 'none';
        if (modal) modal.style.display = 'none';
    } else {
        if (openBtn) openBtn.style.display = 'inline-block';
    }

    if (openBtn) {
        openBtn.onclick = window.openLoginModal;
    }

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            showConfirmDialog('Are you sure you want to logout?', function() {
                window.location.href = 'logout.php';
            }, { title: 'Logout', icon: 'logout', okText: 'Logout' });
        });
    }

    if (closeBtn) {
        closeBtn.onclick = function() {
            if (modal) modal.style.display = "none";
        };
    }

    window.onclick = function(event) {
        if (event.target == modal) modal.style.display = "none";
    };

    // ========================================
    // AJAX LOGIN HANDLER
    // ========================================
    if (loginForm) {
        loginForm.onsubmit = async function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('loginError');
            errorDiv.style.display = 'none';

            const submitBtn = loginForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';

            const formData = new FormData(loginForm);
            try {
                const response = await fetch('pconcio_main.php', {
                    method: 'POST',
                    body: formData
                });

                // Always attempt to parse JSON; server now guarantees valid JSON responses
                const result = await response.json();

                if (result.success) {
                    // Check if there is a pending redirect (e.g. to appointment form)
                    const redirect = sessionStorage.getItem('redirectAfterLogin');
                    if (redirect && result.user_type === 'patient') {
                        sessionStorage.removeItem('redirectAfterLogin');
                        window.location.href = redirect;
                    } else {
                        sessionStorage.removeItem('redirectAfterLogin');
                        window.location.href = result.redirect;
                    }
                } else {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                }
            } catch (err) {
                // If parsing fails or network error occurs, show generic message
                errorDiv.textContent = "An unexpected error occurred. Please try again.";
                errorDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            }
        };
    }

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '') return;
            
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Slideshow Logic
    const slides = document.querySelectorAll('.slideshow-container img');
    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    let currentSlide = 0;
    let slideInterval;

    function showSlide(index) {
        slides[currentSlide].classList.remove('active');
        currentSlide = (index + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
    }

    function nextSlide() {
        showSlide(currentSlide + 1);
    }

    function startSlideshow() {
        if (slideInterval) clearInterval(slideInterval);
        slideInterval = setInterval(nextSlide, 3000);
    }
    
    if(slides.length > 0) {
        startSlideshow();
        if(prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); showSlide(currentSlide - 1); startSlideshow(); });
        if(nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); showSlide(currentSlide + 1); startSlideshow(); });
    }

    // FAQ Toggle Logic
    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', function () {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('.faq-icon');
            const isOpen = answer.style.maxHeight;

            // Close all other open items (Accordion effect)
            document.querySelectorAll('.faq-answer').forEach(a => a.style.maxHeight = null);
            document.querySelectorAll('.faq-icon').forEach(i => {
                i.textContent = '+';
                i.style.transform = 'rotate(0deg)';
            });

            if (!isOpen) {
                answer.style.maxHeight = answer.scrollHeight + "px";
                icon.textContent = "−";
                icon.style.transform = 'rotate(180deg)';
            }
        });
    });
});

function handleBookAppointment(e) {
    e.preventDefault();
    if (isLoggedIn) {
        window.location.href = 'online_appointment_form.php';
    } else {
        sessionStorage.setItem('redirectAfterLogin', 'online_appointment_form.php');
        const modal = document.getElementById("loginModal");
        if(modal) modal.style.display = "flex";
    }
}

function toggleLoginPasswordVisibility() {
    const passwordInput = document.getElementById("password");
    const toggleIcon = document.querySelector(".toggle-password");
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        toggleIcon.classList.remove("fa-eye-slash");
        toggleIcon.classList.add("fa-eye");
    } else {
        passwordInput.type = "password";
        toggleIcon.classList.remove("fa-eye");
        toggleIcon.classList.add("fa-eye-slash");
    }
}
</script>

</html>