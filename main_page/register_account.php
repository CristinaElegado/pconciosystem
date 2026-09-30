<?php
include __DIR__ . '/../miscellaneous/database.php';
session_start();

// Restrict access for logged-in users
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
    } elseif ($_SESSION['user_type'] === 'patient') {
        header("Location: pconcio_main.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name   = trim($_POST['first_name']);
    $last_name    = trim($_POST['last_name']);
    $birthday     = trim($_POST['birthday']);
    $gender       = trim($_POST['gender']);
    $gmail        = trim($_POST['gmail']);
    $phone_number = trim($_POST['phone_number']);
    $password     = trim($_POST['password']);
    $confirm      = trim($_POST['confirm_password']);

    header('Content-Type: application/json');

    $emailParts = explode('@', $gmail);
    $domainPart = array_pop($emailParts);
    $localPart = implode('@', $emailParts);

    // Added validations
    if (strlen($first_name) > 50 || !preg_match("/^[a-zA-Z\s'-]+$/", $first_name)) {
        echo json_encode(['success' => false, 'message' => 'First name must be under 50 characters and only contain letters, spaces, hyphens, or apostrophes.']);
        exit;
    } elseif (strlen($last_name) > 50 || !preg_match("/^[a-zA-Z\s'-]+$/", $last_name)) {
        echo json_encode(['success' => false, 'message' => 'Last name must be under 50 characters and only contain letters, spaces, hyphens, or apostrophes.']);
        exit;
    } elseif (empty($birthday) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid birthdate.']);
        exit;
    } elseif (strtotime($birthday) > time()) {
        echo json_encode(['success' => false, 'message' => 'Birthdate cannot be in the future.']);
        exit;
    } elseif (
        empty($first_name) || empty($last_name) || empty($birthday) || 
        empty($gender) || empty($gmail) || empty($phone_number) || 
        empty($password) || empty($confirm)
    ) {
        echo json_encode(['success' => false, 'message' => 'Please fill out all fields.']);
        exit;
    } elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address or exceeds length limits (64 chars before @, 255 after).']);
        exit;
    } elseif (strlen($phone_number) !== 11 || !preg_match('/^09\d{9}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits and start with 09.']);
        exit;
    } elseif ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,30}$/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must be between 8 and 30 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.']);
        exit;
    } else {
        $birthDate = new DateTime($birthday);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;

        if ($age < 2) {
            echo json_encode(['success' => false, 'message' => 'Patient must be at least 2 years old.']);
            exit;
        } else {

            $first_name = ucfirst(strtolower($first_name));
            $last_name  = ucfirst(strtolower($last_name));

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO patient_account 
                    (first_name, last_name, birthday, age, gender, gmail, phone_number, password) 
                    VALUES (:first_name, :last_name, :birthday, :age, :gender, :gmail, :phone_number, :password)");

                $stmt->bindValue(':first_name', $first_name);
                $stmt->bindValue(':last_name', $last_name);
                $stmt->bindValue(':birthday', $birthday);
                $stmt->bindValue(':age', $age, PDO::PARAM_INT);
                $stmt->bindValue(':gender', $gender);
                $stmt->bindValue(':gmail', $gmail);
                $stmt->bindValue(':phone_number', $phone_number);
                $stmt->bindValue(':password', $hashedPassword);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Registration successful!', 'redirect' => 'pconcio_main.php']);
                    exit();
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    echo json_encode(['success' => false, 'message' => 'This email or phone number is already registered.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            }
        }
    }
}

$today = new DateTime();
$today->modify('-2 years');
$maxDate = $today->format('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - PconcioDental</title>
    <link rel="stylesheet" href="pconcio_main_design.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Inherit .form-message from pconcio_main_design.css but ensure visibility logic works */
        .form-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: none;
        }
        .form-message.error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #f87171;
        }
        .form-message.success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        /* Page Layout */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
        }

        .page-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px -10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 700px;
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }

        .back-button {
            text-decoration: none;
            color: var(--primary);
            background: rgba(14, 165, 233, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 auto 30px auto;
            width: fit-content;
        }

        .back-button:hover {
            background: var(--primary);
            color: white;
            transform: translateX(-3px);
        }

        .register-container h2 {
            text-align: center;
            color: var(--primary-dark);
            margin-bottom: 15px;
            font-size: 2rem;
            font-weight: 700;
            margin-top: 15px;
        }

        #registerForm {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 600;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-family: inherit;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-light);
            outline: none;
        }

        button[name="register"] {
            grid-column: 1 / -1;
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            box-shadow: 0 10px 20px -5px rgba(14, 165, 233, 0.4);
        }

        button[name="register"]:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(14, 165, 233, 0.5);
        }

        .small-text {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95rem;
            color: var(--secondary);
        }

        .small-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .small-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
                margin-top: 20px;
            }

            #registerForm {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .register-container h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="page-content">
    <div class="register-container">
    <h2>Patient Registration</h2>
    <a href="pconcio_main.php" class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
    <div id="registerMessage" class="form-message"></div>

    <form method="POST" id="registerForm">
        <div class="input-group">
            <label>First Name</label>
            <input type="text" name="first_name" required maxlength="50" style="text-transform: uppercase;" placeholder="e.g. Juan">
        </div>

        <div class="input-group">
            <label>Last Name</label>
            <input type="text" name="last_name" required maxlength="50" style="text-transform: uppercase;" placeholder="e.g. Dela Cruz">
        </div>

        <div class="input-group">
            <label>Birthdate</label>
            <input type="date" name="birthday" max="<?= $maxDate ?>" required>
        </div>

        <div class="input-group">
            <label>Gender</label>
            <select name="gender" required>
                <option value="">Select Gender</option>
                <option value="MALE">Male</option>
                <option value="FEMALE">Female</option>
            </select>
        </div>

        <div class="input-group full-width">
            <label>Email Address</label>
            <input type="email" name="gmail" required maxlength="320" placeholder="e.g. juan@example.com">
        </div>

        <div class="input-group full-width">
            <label>Phone Number</label>
            <input type="tel" 
                   name="phone_number" 
                   pattern="^09\d{9}$" 
                   maxlength="11"
                   minlength="11"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0,11)"
                   required placeholder="09xxxxxxxxx">
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required maxlength="30" placeholder="8-30 chars">
        </div>

        <div class="input-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required maxlength="30" placeholder="Retype password">
        </div>

        <button type="submit" name="register">Register</button>
    </form>

    <div class="small-text">
        Already have an account? <a href="pconcio_main.php">Login</a>
    </div>
    </div>
    </div>

    <script>
        document.getElementById('registerForm').onsubmit = async function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            const messageDiv = document.getElementById('registerMessage');
            messageDiv.style.display = 'none';
            messageDiv.className = 'form-message';

            const submitBtn = this.querySelector('button[name="register"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            const formData = new FormData(this);
            formData.append('register', '1');

            try {
                const response = await fetch('register_account.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    messageDiv.textContent = result.message;
                    messageDiv.classList.add('success');
                    messageDiv.style.display = 'block';
                    setTimeout(() => window.location.href = result.redirect, 2000);
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.classList.add('error');
                    messageDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Register';
                }
            } catch (err) {
                messageDiv.textContent = "An unexpected error occurred. Please try again.";
                messageDiv.classList.add('error');
                messageDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register';
            }
        };
    </script>
</body>
</html>
