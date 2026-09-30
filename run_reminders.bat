@echo off
REM ============================================================
REM  run_reminders.bat
REM  Mariategue Dental Clinic — SMS Appointment Reminders
REM
REM  This batch file runs send_appointment_reminders.php using
REM  the PHP bundled with XAMPP.
REM
REM  HOW TO SET UP (one-time setup):
REM  ─────────────────────────────────────────────────────────
REM  1. Open Windows Task Scheduler
REM     (Search "Task Scheduler" in Start Menu)
REM
REM  2. Click "Create Basic Task" on the right panel
REM
REM  3. Name: SMS Appointment Reminders
REM     Description: Sends SMS reminders to dental patients
REM
REM  4. Trigger: Daily
REM     Start: today's date, Time: 12:00 AM
REM     Recur every: 1 day
REM
REM  5. Action: Start a program
REM     Program/script:  (browse to this .bat file)
REM     C:\xampp\htdocs\Mariategue-DentalClinic\run_reminders.bat
REM
REM  6. Finish, then RIGHT-CLICK the task → Properties
REM     → Triggers tab → Edit the trigger
REM     → Check "Repeat task every: 1 minute"
REM     → For a duration of: Indefinitely
REM     → OK → OK
REM
REM  That's it! The script will now run every minute automatically.
REM  XAMPP must be running (Apache + MySQL) for it to work.
REM ============================================================

REM -- Path to XAMPP's php.exe (change if your XAMPP is in a different drive)
SET PHP_EXE=C:\xampp\php\php.exe

REM -- Path to the reminder script
SET SCRIPT=C:\Users\Ax\Downloads\o\Mariategue-DentalClinic\send_appointment_reminders.php

REM -- Run the PHP script (silently, no window popup)
"%PHP_EXE%" "%SCRIPT%"
