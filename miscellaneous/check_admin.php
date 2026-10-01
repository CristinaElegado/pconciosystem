<?php
/**
 * check_admin.php
 * Checks if at least one admin exists in the database.
 * Works without a session — for use on public pages like pconcio_main.php
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

include __DIR__ . '/database.php';

try {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($count === 0) {
        echo json_encode([
            'has_admin'    => false,
            'register_url' => '/setup.php'
        ]);
    } else {
        echo json_encode(['has_admin' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['has_admin' => true]); // fail safe — don't redirect on DB error
}
