<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include __DIR__ . '/../PHPMailer/src/Exception.php';
include __DIR__ . '/../PHPMailer/src/PHPMailer.php';
include __DIR__ . '/../PHPMailer/src/SMTP.php';

// Password requests disabled for now
header("Location: ../dashboard/dashboard.php");
exit;

// Only Admin can access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../dashboard/dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT * FROM password_change_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request && $request['status'] === 'PENDING') {
        // Determine table based on user_type
        $table = ($request['user_type'] === 'staff') ? 'staff_accounts' : 'dentist_accounts';

        // Fetch user email
        $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM $table WHERE id = ?");
        $stmtUser->execute([$request['user_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($action === 'APPROVE') {
            // Update the user's password
            $hashed_password = password_hash($request['new_password_hash'], PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE $table SET password_hash = ? WHERE id = ?");
            $update->execute([$hashed_password, $request['user_id']]);
            
            // Mark request as approved
            $pdo->prepare("UPDATE password_change_requests SET status = 'APPROVED' WHERE id = ?")->execute([$request_id]);
            $message = "Password change approved successfully.";
            $messageType = "success";

            // Send Approval Email
            if ($user) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'beanchcanonego1212@gmail.com';
                    $mail->Password   = 'xksipzdvavxpclby';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('beanchcanonego1212@gmail.com', 'PconcioDental Admin');
                    $mail->addAddress($user['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Change Request Approved';
                    $mail->Body    = "Dear " . htmlspecialchars($user['first_name']) . ",<br><br>Your password change request has been <b>APPROVED</b>.<br>Your new current password is: <b>" . htmlspecialchars($request['new_password_hash']) . "</b><br><br>You can now login with this password.";
                    $mail->send();
                } catch (Exception $e) {
                    $message .= " (Email failed: {$mail->ErrorInfo})";
                }
            }
        } elseif ($action === 'REJECT') {
            // Mark request as rejected
            $pdo->prepare("UPDATE password_change_requests SET status = 'REJECTED' WHERE id = ?")->execute([$request_id]);
            $message = "Password change rejected.";
            $messageType = "error";

            // Send Rejection Email
            if ($user) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'beanchcanonego1212@gmail.com';
                    $mail->Password   = 'xksipzdvavxpclby';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('beanchcanonego1212@gmail.com', 'PconcioDental Admin');
                    $mail->addAddress($user['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Change Request Declined';
                    $mail->Body    = "Dear " . htmlspecialchars($user['first_name']) . ",<br><br>Your password change request for password <b>" . htmlspecialchars($request['new_password_hash']) . "</b> has been <b>DECLINED</b>.<br>Your password remains unchanged.";
                    $mail->send();
                } catch (Exception $e) {
                    $message .= " (Email failed: {$mail->ErrorInfo})";
                }
            }
        }
    }
}

include __DIR__ . '/../miscellaneous/sidebar.php';

// Fetch Pending Requests
$requests = $pdo->query("
    SELECT r.*, 
           COALESCE(d.first_name, s.first_name) as first_name, 
           COALESCE(d.last_name, s.last_name) as last_name, 
           COALESCE(d.email, s.email) as email
    FROM password_change_requests r
    LEFT JOIN dentist_accounts d ON r.user_id = d.id AND r.user_type = 'dentist'
    LEFT JOIN staff_accounts s ON r.user_id = s.id AND r.user_type = 'staff'
    WHERE r.status = 'PENDING'
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Requests</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <link rel="stylesheet" href="dentist_account_design.css">
    <style>
        body {
            background-image: none !important;
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-approve:hover { background-color: #218838; }
        .btn-reject:hover { background-color: #c82333; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="main-content">
        <h2>Password Change Requests</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>Requested Password</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" style="text-align:center;">No pending requests.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($r['user_type'])) ?></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= htmlspecialchars($r['new_password_hash']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" id="pwReqForm_<?= $r['id'] ?>">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="action" id="pwReqAction_<?= $r['id'] ?>" value="">
                                        <button type="button" class="btn-approve" onclick="showConfirmDialog('Approve this password change?', function(){ document.getElementById('pwReqAction_<?= $r['id'] ?>').value='APPROVE'; document.getElementById('pwReqForm_<?= $r['id'] ?>').submit(); }, { title: 'Approve Request', icon: 'approve', okText: 'Approve' })">Approve</button>
                                        <button type="button" class="btn-reject" onclick="showConfirmDialog('Reject this password change?', function(){ document.getElementById('pwReqAction_<?= $r['id'] ?>').value='REJECT'; document.getElementById('pwReqForm_<?= $r['id'] ?>').submit(); }, { title: 'Reject Request', icon: 'reject', danger: true, okText: 'Reject' })">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        // Listen for cross-tab logout
        window.addEventListener('storage', function(e) {
            if (e.key === 'logoutEvent') {
                alert('You have been logged out from another tab.');
                window.location.href = '../main_page/pconcio_main.php';
            }
        });
    </script>
</body>
</html>