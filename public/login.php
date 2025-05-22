<?php
session_start();
require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Check admin
    if ($username === "admin" && $password === "admin123") {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = "admin";
        header("Location: ../admin/dashboard.php");
        exit();
    }

    // Check tenants (using username and hashed password)
    $res = $conn->query("SELECT id, password FROM tenants WHERE username='$username'");
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = "tenant";
            header("Location: ../tenant/dashboard.php");
            exit();
        }
    }

    $error = "Invalid credentials!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Property Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --secondary: #3f37c9;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --light-gray: #e2e8f0;
            
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--dark);
            background-image: 
                radial-gradient(at 80% 0%, var(--primary-light) 0px, transparent 50%),
                radial-gradient(at 0% 50%, var(--primary-light) 0px, transparent 50%);
            animation: gradientAnimation 15s ease infinite;
            background-size: 200% 200%;
        }

        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-container {
            background-color: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            position: relative;
            overflow: hidden;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -5px rgba(67, 97, 238, 0.15);
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            animation: borderAnimation 2s linear infinite;
        }

        @keyframes borderAnimation {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
            transition: transform 0.3s;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: inline-block;
            transition: all 0.3s;
        }

        .logo:hover .logo-icon {
            color: var(--secondary);
            transform: rotate(10deg);
        }

        h2 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
        }

        h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            margin: 0.5rem auto 0;
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            transition: all 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s;
            background-color: var(--light);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
            background-color: white;
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
        }

        .input-icon {
            position: absolute;
            right: 1rem;
            top: 2.6rem;
            color: var(--gray);
            transition: all 0.3s;
        }

        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            cursor: pointer;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .error-message {
            color: var(--danger);
            background-color: rgba(239, 68, 68, 0.1);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--danger);
            animation: fadeIn 0.3s ease-out;
            display: flex;
            align-items: center;
        }

        .error-message i {
            margin-right: 0.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray);
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .footer a:hover {
            color: var(--secondary);
        }

        .footer a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s;
        }

        .footer a:hover::after {
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 2.6rem;
            cursor: pointer;
            color: var(--gray);
            transition: all 0.3s;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Floating animation */
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .floating {
            animation: floating 3s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon floating">
                <i class="fas fa-lock"></i>
            </div>
        </div>
        <h2>Welcome Back</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
                <i class="fas fa-user input-icon"></i>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt mr-2"></i> Sign In
            </button>
        </form>
        
        <div class="footer">
         <h6 class="mb-0">Powered By: J7 IT Solution and Services 
            </h6>
            <p class="mt-2"><a href="website.php">← Back to Website</a></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Password toggle functionality
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Add focus effects
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('label').style.color = 'var(--primary)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.querySelector('label').style.color = 'var(--dark)';
            });
        });

        // Add animation to error message if exists
        <?php if (isset($error)): ?>
            setTimeout(() => {
                const errorMsg = document.querySelector('.error-message');
                errorMsg.style.animation = 'fadeIn 0.3s ease-out';
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>