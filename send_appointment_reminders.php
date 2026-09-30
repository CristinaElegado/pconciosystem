<?php
/**
 * send_appointment_reminders.php
 *
 * Sends SMS reminders to patients for upcoming appointments.
 * Covers BOTH online (APPROVE) and walk-in appointments in patients_list.
 *
 * TWO reminders per appointment:
 *   1. "5_min_before" — sent when current time = appointment time MINUS 5 minutes
 *   2. "on_time"      — sent when current time = exact appointment time
 *
 * HOW THIS WORKS:
 *   This script should be triggered EVERY MINUTE by Windows Task Scheduler.
 *   Each time it runs, it checks: "Is there any appointment that starts in
 *   exactly 5 minutes from now, or starts right now?"
 *   If yes → send SMS (and log it so it never sends twice).
 *
 * EXAMPLE:
 *   Patient appointment: 11:00 PM
 *   Script runs at 10:55 PM → sends "5 minutes na lang!" SMS
 *   Script runs at 11:00 PM → sends "Your appointment is NOW" SMS
 *
 * SETUP (Windows Task Scheduler — run every 1 minute):
 *   See run_reminders.bat in the project root for easy setup instructions.
 *
 * PREREQUISITES:
 *   1. Run sms_notifications_migration.sql in phpMyAdmin.
 *   2. Put your Semaphore API key in miscellaneous/send_sms.php.
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────
$base = __DIR__;
require_once $base . '/miscellaneous/database.php';
require_once $base . '/miscellaneous/send_sms.php';

// ── Timezone ────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Current time windows ────────────────────────────────────────────────────
// We get the current date and two "target" times:
//   $time_now      = H:i  (e.g. "23:00") — for on_time check
//   $time_5min     = H:i  (e.g. "22:55") — for 5_min_before check
//
// The time_visit in the DB is stored as "8:00 AM", "2:00 PM", etc.
// We'll convert both to a comparable H:i (24-hr) format.

$today      = date('Y-m-d');                     // e.g. 2026-09-30
$time_now   = date('H:i');                        // e.g. 23:00
$time_5min  = date('H:i', strtotime('-5 minutes')); // e.g. 22:55

log_msg("Running at {$today} {$time_now} (checking for on_time={$time_now}, 5_min_before={$time_5min})");

// ── Query: get all WAITING appointments today ───────────────────────────────
// Only "WAITING" patients need reminders — not ONGOING / TREATED / CANCELLED.

$sql = "
    SELECT
        pl.id                                        AS patient_id,
        pl.first_name,
        pl.last_name,
        pl.phone_number,
        pl.date_visit,
        pl.time_visit,
        pl.type_of_appointment,
        CONCAT(da.first_name, ' ', da.last_name)     AS dentist_name
    FROM patients_list pl
    LEFT JOIN dentist_accounts da ON pl.dentist_id = da.id
    WHERE
        pl.status    = 'WAITING'
        AND pl.date_visit = :today
    ORDER BY pl.time_visit ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':today' => $today]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($appointments)) {
    log_msg("No WAITING appointments today. Exiting.");
    exit;
}

log_msg("Found " . count($appointments) . " WAITING appointment(s) today.");

// ── Process each appointment ─────────────────────────────────────────────────
$sent = $skipped = $failed = 0;

foreach ($appointments as $appt) {
    $apptId      = (int)  $appt['patient_id'];
    $firstName   =        $appt['first_name'];
    $lastName    =        $appt['last_name'];
    $patientName = trim($firstName . ' ' . $lastName);
    $phone       =        $appt['phone_number'];
    $timeVisit   =        $appt['time_visit'];   // stored as "8:00 AM", "11:00 PM", etc.
    $apptType    =        $appt['type_of_appointment'];
    $dentistName =        $appt['dentist_name'] ?? 'your dentist';

    // Convert stored time (12-hr) to 24-hr H:i for comparison
    $apptTime24 = date('H:i', strtotime($timeVisit)); // e.g. "23:00"

    // ── Determine which reminder type applies right now ─────────────────────
    $reminderType = null;

    if ($apptTime24 === $time_now) {
        $reminderType = 'on_time';
    } elseif ($apptTime24 === $time_5min) {
        $reminderType = '5_min_before';
    }

    // This appointment is not in any trigger window right now — skip
    if ($reminderType === null) {
        continue;
    }

    // ── Check duplicate: already sent this reminder for this appointment? ───
    $dupCheck = $pdo->prepare("
        SELECT COUNT(*) FROM sms_notifications_log
        WHERE source_table   = 'patients_list'
          AND appointment_id = :id
          AND reminder_type  = :type
    ");
    $dupCheck->execute([':id' => $apptId, ':type' => $reminderType]);
    if ((int) $dupCheck->fetchColumn() > 0) {
        $skipped++;
        log_msg("SKIP [{$reminderType}] #{$apptId} {$patientName} — already sent.");
        continue;
    }

    // ── Build SMS message ────────────────────────────────────────────────────
    $appointmentLabel = ($apptType === 'ONLINE') ? 'online appointment' : 'walk-in appointment';

    if ($reminderType === '5_min_before') {
        $smsText =
            "Hello {$firstName}! Reminder from Mariategue Dental Clinic: " .
            "Your {$appointmentLabel} with {$dentistName} starts in 5 minutes " .
            "({$timeVisit}). Please proceed to the clinic now. Thank you!";
    } else {
        // on_time
        $smsText =
            "Hello {$firstName}! Your dental {$appointmentLabel} with {$dentistName} " .
            "is NOW ({$timeVisit}) at Mariategue Dental Clinic. " .
            "We are ready for you!";
    }

    // ── Send ─────────────────────────────────────────────────────────────────
    $result = send_sms($phone, $smsText);

    // ── Log to DB ────────────────────────────────────────────────────────────
    $logStmt = $pdo->prepare("
        INSERT INTO sms_notifications_log
            (source_table, appointment_id, reminder_type,
             phone_number, patient_name, appointment_date, appointment_time,
             status, api_message_id, error_message)
        VALUES
            ('patients_list', :appt_id, :reminder_type,
             :phone, :patient_name, :appt_date, :appt_time,
             :status, :msg_id, :err_msg)
    ");
    $logStmt->execute([
        ':appt_id'       => $apptId,
        ':reminder_type' => $reminderType,
        ':phone'         => $phone,
        ':patient_name'  => $patientName,
        ':appt_date'     => $appt['date_visit'],
        ':appt_time'     => $timeVisit,
        ':status'        => $result['success'] ? 'sent' : 'failed',
        ':msg_id'        => $result['message_id'],
        ':err_msg'       => $result['error'],
    ]);

    if ($result['success']) {
        $sent++;
        log_msg("SENT [{$reminderType}] #{$apptId} {$patientName} → {$phone}");
    } else {
        $failed++;
        log_msg("FAIL [{$reminderType}] #{$apptId} {$patientName} → {$phone} | {$result['error']}");
    }
}

// ── Summary ──────────────────────────────────────────────────────────────────
log_msg("Done. Sent: {$sent} | Skipped (dup): {$skipped} | Failed: {$failed}");

// ── Helper ───────────────────────────────────────────────────────────────────
function log_msg(string $msg): void
{
    $ts   = date('Y-m-d H:i:s');
    $line = "[{$ts}] {$msg}";
    echo $line . PHP_EOL;
    error_log($line);
}
