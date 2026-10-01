<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// Security check: Ensure only logged-in dentists can access this page
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dentist') {
    header("Location: pconcio_main.php");
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';

$dentist_id = $_SESSION['id'];
$message = "";

$stmt = $pdo->prepare("
    SELECT *
    FROM dentist_schedule
    WHERE dentist_id = ?
");
$stmt->execute([$dentist_id]);
$my_schedule = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dentist Dashboard - Manage Schedule</title>
    <link rel="stylesheet" href="pconcio_main_design.css">
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <style>
        .dashboard-container { max-width: 800px; margin: 50px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .day-group { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .day-title { font-weight: bold; font-size: 1.1rem; color: #333; margin-bottom: 10px; text-transform: capitalize; }
        .time-badge { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 5px 10px; border-radius: 15px; margin-right: 5px; margin-bottom: 5px; font-size: 0.9rem; }
        .no-slots { color: #999; font-style: italic; }
        .info-box { background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin-bottom: 20px; color: #0c4a6e; }

        /* Blue text for headings and instructions */
        .dashboard-container h2,
        .dashboard-container h3,
        .dashboard-container p {
            color: #0284c7;
        }

        /* Red color for logout link */
        .logout-link {
            color: #dc3545 !important;
            font-weight: bold;
        }
        .logout-link:hover {
            color: #c82333 !important;
        }
    </style>
</head>
<body>
    <div class="main-content">
    <div class="dashboard-container">
        <h2>Your Weekly Schedule</h2>

        <div class="info-box">
            <p><strong>Note:</strong> This schedule is managed by the administrator. Please contact the clinic admin if you need to change your availability.</p>
        </div>

        <?php 
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day): 
            $isActive = $my_schedule && $my_schedule[$day] == 1;
            $startTime = $isActive ? substr($my_schedule[$day.'_start'], 0, 5) : '';
            $endTime = $isActive ? substr($my_schedule[$day.'_end'], 0, 5) : '';
        ?>
            <div class="day-group">
                <div class="day-title"><?= ucfirst($day) ?></div>
                <?php if ($isActive && $startTime && $endTime): ?>
                    <span class="time-badge">In: <?= htmlspecialchars($startTime) ?> - Out: <?= htmlspecialchars($endTime) ?> (Military Time)</span>
                <?php else: ?>
                    <span class="no-slots">Not Available</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    </div>
    <script>
        // Listen for cross-tab logout
        window.addEventListener('storage', function(e) {
            if (e.key === 'logoutEvent') {
                showAlert('You have been logged out from another tab.');
                window.location.href = 'pconcio_main.php';
            }
        });
    </script>
</body>
</html>

