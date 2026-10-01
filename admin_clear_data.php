<?php
session_start();
include __DIR__ . '/miscellaneous/database.php';

// Only allow access if the logged-in user is an Admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: main_page/pconcio_main.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Disable foreign key checks to allow truncation of related tables
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        if (isset($_POST['clear_transactions'])) {
            // 1. Clear only transactions and appointments
            $pdo->exec("TRUNCATE TABLE transaction_history;");
            $pdo->exec("TRUNCATE TABLE patients_list;");
            $pdo->exec("TRUNCATE TABLE patient_services;");
            $pdo->exec("TRUNCATE TABLE online_appointment;");
            $pdo->exec("TRUNCATE TABLE online_appointment_services;");
            $pdo->exec("TRUNCATE TABLE testimonials;");
            
            $message = "Transactions, Appointments, and Testimonials have been successfully cleared!";
            $msgClass = "success";
        } 
        elseif (isset($_POST['clear_all'])) {
            // 2. Factory Reset (Clear absolutely everything except the Admin table)
            $tables_to_truncate = [
                'transaction_history',
                'patients_list',
                'patient_services',
                'online_appointment',
                'online_appointment_services',
                'patient_account',
                'staff_accounts',
                'dentist_accounts',
                'dentist_schedule',
                'dentist_time_schedules',
                'services',
                'service_items',
                'item_inventory',
                'password_change_requests',
                'time_slots',
                'testimonials'
            ];

            foreach ($tables_to_truncate as $table) {
                // We use try-catch inside the loop just in case a table doesn't exist yet
                try {
                    $pdo->exec("TRUNCATE TABLE {$table};");
                } catch (PDOException $e) {
                    // Ignore missing tables
                }
            }

            // Reset the staff ID tracker back to 0
            try {
                $pdo->exec("UPDATE staff_id_tracker SET last_number = 0 WHERE id = 1;");
            } catch (PDOException $e) {}

            $message = "FACTORY RESET COMPLETE: All data and transactions have been wiped!";
            $msgClass = "success";
        }

        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        
        echo "<script>alert('{$message}'); window.location='admin_clear_data.php';</script>";
        exit;

    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        echo "<script>alert('Database Error: " . addslashes($e->getMessage()) . "'); window.location='admin_clear_data.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear System Data</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-width: 500px; text-align: center; border-top: 5px solid #dc3545; }
        h2 { color: #334155; margin-bottom: 10px; }
        p { color: #64748b; font-size: 0.95rem; margin-bottom: 30px; line-height: 1.5; }
        .btn { display: block; width: 100%; padding: 15px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-bottom: 15px; transition: 0.2s; color: white; }
        .btn-warning { background-color: #f59e0b; }
        .btn-warning:hover { background-color: #d97706; }
        .btn-danger { background-color: #ef4444; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-back { background-color: #64748b; text-decoration: none; display: inline-block; box-sizing: border-box; }
        .btn-back:hover { background-color: #475569; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/miscellaneous/confirm_dialog.php'; ?>
    <div class="container">
        <h2>System Data Reset</h2>
        <p>Warning: These actions are permanent and cannot be undone. Please ensure you have backups if needed before proceeding.</p>

        <form method="POST" id="clearTransactionsForm">
            <button type="button" class="btn btn-warning" onclick="showConfirmDialog('Are you sure you want to delete ALL transactions and appointments? Users and inventory will remain intact.', function(){ document.getElementById('clearTransactionsSubmit').click(); }, { title: 'Clear Transactions', icon: 'warning', okText: 'Yes, Clear' })">
                Clear Transactions &amp; Appointments
            </button>
            <input type="submit" name="clear_transactions" id="clearTransactionsSubmit" style="display:none;">
        </form>

        <form method="POST" id="clearAllForm">
            <button type="button" class="btn btn-danger" onclick="showConfirmDialog('DANGER: This will wipe EVERYTHING (Patients, Dentists, Staff, Inventory, Transactions). Only the Admin account will remain. Proceed?', function(){ document.getElementById('clearAllSubmit').click(); }, { title: 'Factory Reset', icon: 'danger', danger: true, okText: 'Yes, Wipe Everything' })">
                Factory Reset (Wipe All Data)
            </button>
            <input type="submit" name="clear_all" id="clearAllSubmit" style="display:none;">
        </form>
        <a href="dashboard/dashboard.php" class="btn btn-back">Return to Dashboard</a>
    </div>
</body>
</html>