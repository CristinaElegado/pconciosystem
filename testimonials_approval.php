<?php
session_start();
include __DIR__ . '/miscellaneous/database.php';
include __DIR__ . '/miscellaneous/log_audit.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: main_page/pconcio_main.php");
    exit;
}

// Ensure testimonials table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_name` varchar(100) NOT NULL,
    `rating` tinyint(1) NOT NULL DEFAULT 5,
    `comment` text NOT NULL,
    `is_approved` tinyint(1) NOT NULL DEFAULT 0,
    `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE testimonials SET is_approved = 1 WHERE id = :id")->execute(['id' => $id]);
        log_audit($pdo, $_SESSION['user_type'], $_SESSION['username'], 'Approved Testimonial', "Testimonial ID: $id");
        $toast = "Review approved and is now visible on the main page.";
    } elseif ($action === 'reject') {
        $pdo->prepare("DELETE FROM testimonials WHERE id = :id")->execute(['id' => $id]);
        log_audit($pdo, $_SESSION['user_type'], $_SESSION['username'], 'Rejected Testimonial', "Testimonial ID: $id");
        $toast = "Review rejected and deleted.";
    }

    header("Location: testimonials_approval.php?toast=" . urlencode($toast ?? ''));
    exit;
}

$toast = $_GET['toast'] ?? '';

// Fetch pending + approved
$pending  = $pdo->query("SELECT * FROM testimonials WHERE is_approved = 0 ORDER BY submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$approved = $pdo->query("SELECT * FROM testimonials WHERE is_approved = 1 ORDER BY submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonial Approvals — Mariategue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="miscellaneous/sidebar_design.css">
    <style>
        .main-content { padding: 30px; }
        .approval-wrapper { max-width: 860px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .page-subtitle { font-size: .875rem; color: #64748b; margin-bottom: 28px; }

        /* Toast */
        .toast {
            display: none; position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: #22c55e; color: #fff; padding: 14px 22px; border-radius: 10px;
            font-size: .9rem; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,.15);
            animation: slideIn .3s ease;
        }
        .toast.show { display: flex; align-items: center; gap: 8px; }
        @keyframes slideIn { from { transform: translateX(60px); opacity:0; } to { transform: translateX(0); opacity:1; } }

        /* Section labels */
        .section-label {
            font-size: .75rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #94a3b8; margin: 28px 0 12px;
        }

        /* Cards */
        .review-card {
            background: #fff; border-radius: 12px; padding: 20px 22px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 14px;
            border: 1px solid #e2e8f0;
            display: flex; align-items: flex-start; gap: 18px;
        }
        .review-card .star-display { font-size: 1.2rem; color: #fcd34d; white-space: nowrap; }
        .review-card .review-body { flex: 1; }
        .review-card .review-name { font-weight: 700; color: #1e293b; font-size: .95rem; }
        .review-card .review-date { font-size: .75rem; color: #94a3b8; margin-bottom: 6px; }
        .review-card .review-comment { font-size: .9rem; color: #475569; line-height: 1.6; }
        .review-card .review-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }

        .btn-approve {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; background: #22c55e; color: #fff;
            border: none; border-radius: 8px; font-size: .85rem;
            font-weight: 600; cursor: pointer; transition: .2s;
        }
        .btn-approve:hover { background: #16a34a; }
        .btn-reject {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; background: #fee2e2; color: #dc2626;
            border: none; border-radius: 8px; font-size: .85rem;
            font-weight: 600; cursor: pointer; transition: .2s;
        }
        .btn-reject:hover { background: #fecaca; }
        .btn-delete {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; background: #f1f5f9; color: #64748b;
            border: none; border-radius: 8px; font-size: .85rem;
            font-weight: 600; cursor: pointer; transition: .2s;
        }
        .btn-delete:hover { background: #e2e8f0; }

        .badge-approved { display: inline-block; padding: 3px 10px; background: #dcfce7; color: #16a34a; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-pending  { display: inline-block; padding: 3px 10px; background: #fef9c3; color: #ca8a04; border-radius: 20px; font-size: .75rem; font-weight: 600; }

        .empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: .9rem; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>
<?php include __DIR__ . '/miscellaneous/sidebar.php'; ?>
<?php include __DIR__ . '/miscellaneous/confirm_dialog.php'; ?>

<?php if ($toast): ?>
<div class="toast show" id="toastMsg">
    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($toast) ?>
</div>
<script>setTimeout(() => document.getElementById('toastMsg').classList.remove('show'), 3500);</script>
<?php endif; ?>

<div class="main-content">
<div class="approval-wrapper">
    <div class="page-title"><i class="fa-solid fa-star" style="color:#fcd34d"></i> Testimonial Approvals</div>
    <div class="page-subtitle">Review and approve or reject patient-submitted testimonials before they appear on the main page.</div>

    <!-- Pending Reviews -->
    <div class="section-label">
        <i class="fa-solid fa-clock"></i> Pending Approval
        <span style="background:#fef9c3;color:#ca8a04;padding:2px 8px;border-radius:20px;margin-left:8px"><?= count($pending) ?></span>
    </div>

    <?php if (empty($pending)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-face-smile"></i>
            No pending reviews. All caught up!
        </div>
    <?php else: foreach ($pending as $r): ?>
        <div class="review-card">
            <div class="star-display"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
            <div class="review-body">
                <div class="review-name"><?= htmlspecialchars($r['patient_name']) ?> <span class="badge-pending">Pending</span></div>
                <div class="review-date"><?= htmlspecialchars($r['submitted_at']) ?></div>
                <div class="review-comment">"<?= htmlspecialchars($r['comment']) ?>"</div>
                <div class="review-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn-approve"><i class="fa-solid fa-check"></i> Approve</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="button" class="btn-reject"
                            onclick="showConfirmDialog('Reject and delete this review from <?= htmlspecialchars(addslashes($r['patient_name'])) ?>?', function(){ document.getElementById('reject-form-<?= $r['id'] ?>').submit(); }, { title: 'Reject Review', icon: 'danger', okText: 'Yes, Reject' })">
                            <i class="fa-solid fa-xmark"></i> Reject
                        </button>
                        <input type="submit" id="reject-form-<?= $r['id'] ?>" style="display:none">
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <!-- Approved Reviews -->
    <div class="section-label" style="margin-top:36px">
        <i class="fa-solid fa-circle-check"></i> Approved Reviews
        <span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:20px;margin-left:8px"><?= count($approved) ?></span>
    </div>

    <?php if (empty($approved)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-star"></i>
            No approved reviews yet.
        </div>
    <?php else: foreach ($approved as $r): ?>
        <div class="review-card">
            <div class="star-display"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
            <div class="review-body">
                <div class="review-name"><?= htmlspecialchars($r['patient_name']) ?> <span class="badge-approved">Approved</span></div>
                <div class="review-date"><?= htmlspecialchars($r['submitted_at']) ?></div>
                <div class="review-comment">"<?= htmlspecialchars($r['comment']) ?>"</div>
                <div class="review-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="button" class="btn-delete"
                            onclick="showConfirmDialog('Remove this approved review from <?= htmlspecialchars(addslashes($r['patient_name'])) ?>? It will no longer show on the main page.', function(){ document.getElementById('reject-form-<?= $r['id'] ?>').submit(); }, { title: 'Remove Review', icon: 'warning', okText: 'Yes, Remove' })">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                        <input type="submit" id="reject-form-<?= $r['id'] ?>" style="display:none">
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div><!-- /.approval-wrapper -->
</div><!-- /.main-content -->
</html>
