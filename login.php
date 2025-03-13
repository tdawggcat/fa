<?php
session_start(); // Start session to track logged-in user

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Read database credentials from file
$cred_file = '/home/tdawggcat/.mysql_user';
if (!file_exists($cred_file)) {
    die("Error: Credentials file not found at $cred_file");
}

$credentials = file_get_contents($cred_file);
$credentials = trim($credentials);

if (empty($credentials)) {
    die("Error: Credentials file is empty");
}

list($username, $password) = explode(':', $credentials, 2);
$username = trim($username);
$password = trim($password);

if ($username === '' || $password === '') {
    die("Error: Invalid credentials format in $cred_file");
}

// Database connection settings
$host = 'localhost';
$database = 'tdawggcat_fa';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $input_username = trim($_POST['username']);
    $input_password = trim($_POST['password']);

    // Query user from fa_users
    $stmt = $conn->prepare("SELECT id, password_hash, admin FROM fa_users WHERE username = ? AND active = 1");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($input_password, $row['password_hash'])) {
            // Successful login
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['admin'] = $row['admin'];

            // Determine redirect location
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 
                        (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'page.php');
            
            // Prevent redirect to login.php itself
            if (strpos($redirect, 'login.php') !== false) {
                $redirect = 'page.php';
            }

            header("Location: $redirect");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
}

// If already logged in, redirect to the referring page or default
if (isset($_SESSION['user_id'])) {
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 
                (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'page.php');
    if (strpos($redirect, 'login.php') !== false) {
        $redirect = 'page.php';
    }
    header("Location: $redirect");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .login-form {
            max-width: 300px;
            margin: 0 auto;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 5px;
            margin-bottom: 10px;
        }
        button {
            padding: 5px 10px;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>Login</h1>
    <div class="login-form">
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
            
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
            
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>
