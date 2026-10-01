<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// Restrict access for Staff, Dentist, and Admin
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

// Enforce login
if (!isset($_SESSION['user_name']) || !isset($_SESSION['user_email'])) {
    header("Location: pconcio_main.php?showLogin=1");
    exit;
}

// Get user's appointments
$userEmail = $_SESSION['user_email'];
$appointments = [];
$error_msg = null;

try {
    $stmt = $pdo->prepare("
        SELECT 
            oa.id,
            oa.first_name,
            oa.last_name,
            oa.age,
            oa.gender,
            oa.gmail,
            oa.phone_number,
            oa.date_visit,
            oa.time_visit,
            oa.payment_method,
            oa.status,
            oa.created_at,
            oa.dental_toothxray,
            oa.dentist_id,
            COALESCE(CONCAT(da.first_name, ' ', da.last_name), 'Pending Dentist') as dentist_name,
            GROUP_CONCAT(s.service_name SEPARATOR ', ') as services
        FROM online_appointment oa
        LEFT JOIN dentist_accounts da ON oa.dentist_id = da.id
        LEFT JOIN online_appointment_services oas ON oa.id = oas.appointment_id
        LEFT JOIN services s ON oas.service_id = s.id
        WHERE oa.gmail = :email
        GROUP BY oa.id
        ORDER BY oa.date_visit DESC, oa.time_visit DESC
    ");
    $stmt->execute(['email' => $userEmail]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error loading appointments: " . $e->getMessage();
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'PENDING':
            return 'status-badge pending';
        case 'APPROVED':
            return 'status-badge approved';
        case 'COMPLETED':
            return 'status-badge completed';
        case 'CANCELLED':
            return 'status-badge cancelled';
        default:
            return 'status-badge';
    }
}

function getStatusLabel($status) {
    $labels = [
        'PENDING' => 'Pending Approval',
        'APPROVED' => 'Approved',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
        'WAITING' => 'Waiting',
        'ONGOING' => 'Ongoing'
    ];
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Records - MariategueOrtho-Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="pconcio_main_design.css?v=<?php echo time(); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #7dd3fc 100%);
            min-height: 100vh;
        }

        /* Header Styling */
        header {
            background: linear-gradient(135deg, #ffffff 0%, #e0f2fe 100%);
            padding: 0;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.12);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #0369a1;
            text-decoration: none;
            letter-spacing: -1px;
        }

        .logo span {
            color: #0284c7;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 35px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.3s ease;
            position: relative;
        }

        nav a:hover {
            color: #0284c7;
        }

        nav a:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            transition: width 0.3s ease;
        }

        nav a:hover:after {
            width: 100%;
        }

        .records-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .records-header {
            background: linear-gradient(135deg, #ffffff 0%, #e0f2fe 100%);
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.12);
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .records-header h1 {
            color: #0369a1;
            font-size: 32px;
            margin: 0;
        }

        .records-header p {
            color: #075985;
            margin-top: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .back-button {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.2);
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .error-alert {
            background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.15);
        }

        .no-records {
            background: linear-gradient(135deg, #ffffff 0%, #e0f2fe 100%);
            padding: 60px 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.12);
        }

        .no-records i {
            font-size: 64px;
            color: #7dd3fc;
            margin-bottom: 20px;
            display: block;
        }

        .no-records p {
            color: #0369a1;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .no-records a {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.2);
        }

        .no-records a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .appointments-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }

        .appointment-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.12);
            transition: all 0.3s ease;
            border: 1px solid rgba(2, 132, 199, 0.1);
        }

        .appointment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(2, 132, 199, 0.18);
        }

        .card-header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            padding: 22px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .card-date {
            font-size: 12px;
            opacity: 0.95;
            font-weight: 500;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #ffb74d 0%, #ffa726 100%);
            color: white;
        }

        .status-badge.approved {
            background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
            color: white;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%);
            color: white;
        }

        .status-badge.cancelled {
            background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
            color: white;
        }

        .card-body {
            padding: 24px;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            color: #0369a1;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
            word-break: break-word;
        }

        .services-section {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            border-left: 4px solid #0284c7;
        }

        .services-label {
            font-weight: 700;
            color: #0369a1;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .service-tag {
            display: inline-block;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 6px;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .xray-section {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 0;
            border-left: 4px solid #0284c7;
        }

        .xray-section a {
            color: #0369a1;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .xray-section a:hover {
            text-decoration: underline;
            color: #0284c7;
        }

        .burger-menu {
            display: none;
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
        }

        @media (max-width: 768px) {
            .burger-menu {
                display: block;
            }

            nav {
                display: none;
            }

            nav.active {
                display: flex;
                position: absolute;
                flex-direction: column;
                top: 60px;
                left: 0;
                right: 0;
                background: white;
                padding: 20px;
            }

            nav.active ul {
                flex-direction: column;
                gap: 15px;
            }

            .appointments-container {
                grid-template-columns: 1fr;
            }

            .records-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .appointment-details {
                grid-template-columns: 1fr;
            }

            .records-header h1 {
                font-size: 24px;
            }

            .header-wrapper {
                padding: 12px 20px;
            }

            nav ul {
                gap: 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-wrapper">
        <div style="display: flex; align-items: center; gap: 15px; width: 100%;">
            <button class="burger-menu" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="logo"><a href="pconcio_main.php" style="text-decoration: none; color: inherit;">MariategueOrtho<span>-DentalClinic</span></a></h1>
        </div>
        <nav>
            <ul>
                <li><a href="pconcio_main.php">Home</a></li>
                <li><a href="pconcio_main.php#services">Services</a></li>
                <li><a href="pconcio_main.php#contact">Contact Us</a></li>
                <li><a href="online_appointment_form.php" style="color: #0284c7;">Book Appointment</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="records-container">
    <div class="records-header">
        <div>
            <h1><i class="fas fa-calendar-check"></i> Appointment Records</h1>
            <p>View your scheduled and past appointments</p>
        </div>
        <a href="online_appointment_form.php" class="back-button">
            <i class="fas fa-plus"></i> Book New Appointment
        </a>
    </div>

    <?php if ($error_msg): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
        <div class="no-records">
            <i class="fas fa-inbox"></i>
            <p>You don't have any appointments yet</p>
            <a href="online_appointment_form.php">Book Your First Appointment</a>
        </div>
    <?php else: ?>
        <div class="appointments-container">
            <?php foreach ($appointments as $appt): ?>
                <div class="appointment-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">
                                <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?>
                            </div>
                            <div class="card-date">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('M d, Y', strtotime($appt['date_visit'])); ?> at 
                                <?php echo date('g:i A', strtotime($appt['time_visit'])); ?>
                            </div>
                        </div>
                        <span class="<?php echo getStatusBadgeClass($appt['status']); ?>">
                            <?php echo getStatusLabel($appt['status']); ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <!-- Appointment Details -->
                        <div class="appointment-details">
                            <div class="detail-item">
                                <span class="detail-label">Age</span>
                                <span class="detail-value"><?php echo htmlspecialchars($appt['age']); ?> years</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Gender</span>
                                <span class="detail-value"><?php echo htmlspecialchars($appt['gender']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Dentist</span>
                                <span class="detail-value"><?php echo htmlspecialchars($appt['dentist_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Payment</span>
                                <span class="detail-value"><?php echo htmlspecialchars($appt['payment_method']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($appt['phone_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Booked On</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($appt['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Services -->
                        <?php if ($appt['services']): ?>
                            <div class="services-section">
                                <div class="services-label">
                                    <i class="fas fa-stethoscope"></i> Services
                                </div>
                                <?php foreach (explode(', ', $appt['services']) as $service): ?>
                                    <span class="service-tag"><?php echo htmlspecialchars(trim($service)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- X-Ray Image -->
                        <?php if ($appt['dental_toothxray']): ?>
                            <div class="xray-section">
                                <a href="../uploads/xrays/<?php echo urlencode($appt['dental_toothxray']); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-image"></i> View Dental X-Ray
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleMobileMenu() {
        const nav = document.querySelector('nav');
        nav.classList.toggle('active');
    }

    window.addEventListener('click', function(e) {
        const nav = document.querySelector('nav');
        const burger = document.querySelector('.burger-menu');
        if (!e.target.closest('nav') && !e.target.closest('.burger-menu')) {
            nav.classList.remove('active');
        }
    });
</script>

</body>
</html>