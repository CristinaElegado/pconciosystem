<?php
session_start();
include __DIR__ . '/database.php';
header('Content-Type: application/json');

// Only accessible to admin and staff
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    echo json_encode(['total' => 0, 'sections' => []]);
    exit;
}

$isAdmin = $_SESSION['user_type'] === 'admin';
$sections = [];
$total    = 0;

// ─── SECTION 1: Inventory Expiry Alerts ───────────────────────────────────────
try {
    $today   = date('Y-m-d');
    $in7days = date('Y-m-d', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        SELECT
            item_name,
            item_type,
            expiration_date,
            quantity,
            CASE
                WHEN expiration_date < :today THEN 'EXPIRED'
                ELSE 'EXPIRING SOON'
            END AS status
        FROM item_inventory
        WHERE expiration_date IS NOT NULL
          AND expiration_date <= :in7days
        ORDER BY expiration_date ASC
    ");
    $stmt->execute([':today' => $today, ':in7days' => $in7days]);
    $expiryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expiryItems = [];
    foreach ($expiryRows as $r) {
        $expiryItems[] = [
            'icon'    => $r['status'] === 'EXPIRED' ? 'fa-skull-crossbones' : 'fa-clock',
            'color'   => $r['status'] === 'EXPIRED' ? 'expired' : 'expiring',
            'title'   => $r['item_name'],
            'detail'  => 'Exp: ' . date('M d, Y', strtotime($r['expiration_date'])) . ' • Qty: ' . $r['quantity'],
            'badge'   => $r['status'],
            'link'    => '/inventory/inventory.php',
        ];
    }

    if (!empty($expiryItems)) {
        $sections[] = [
            'key'        => 'expiry',
            'label'      => 'Inventory Expiry Alerts',
            'icon'       => 'fa-triangle-exclamation',
            'icon_color' => '#f59e0b',
            'count'      => count($expiryItems),
            'items'      => $expiryItems,
            'footer_text'=> 'View Inventory',
            'footer_link'=> '/inventory/inventory.php',
        ];
        $total += count($expiryItems);
    }
} catch (Exception $e) {
    // silently skip
}

// ─── SECTION 2: Pending Reviews (Admin only) ─────────────────────────────────
if ($isAdmin) {
    try {
        $pendingReviews = $pdo->query("
            SELECT patient_name, rating, submitted_at
            FROM testimonials
            WHERE is_approved = 0
            ORDER BY submitted_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $reviewItems = [];
        foreach ($pendingReviews as $r) {
            $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']);
            $reviewItems[] = [
                'icon'   => 'fa-star',
                'color'  => 'review',
                'title'  => $r['patient_name'],
                'detail' => $stars . ' • ' . date('M d, Y', strtotime($r['submitted_at'])),
                'badge'  => 'Pending Review',
                'link'   => '/testimonials_approval.php',
            ];
        }

        if (!empty($reviewItems)) {
            $sections[] = [
                'key'        => 'reviews',
                'label'      => 'Pending Reviews',
                'icon'       => 'fa-star',
                'icon_color' => '#8b5cf6',
                'count'      => count($reviewItems),
                'items'      => $reviewItems,
                'footer_text'=> 'View All Reviews',
                'footer_link'=> '/testimonials_approval.php',
            ];
            $total += count($reviewItems);
        }
    } catch (Exception $e) {
        // silently skip (table may not exist)
    }
}

echo json_encode([
    'total'    => $total,
    'sections' => $sections,
]);
exit;
