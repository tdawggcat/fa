<?php
session_start(); // Add session start

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to detect mobile device
function isMobile() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $mobile_keywords = [
        'mobile', 'android', 'iphone', 'ipad', 'ipod', 'blackberry', 
        'windows phone', 'opera mini', 'silk', 'kindle', 'webos'
    ];
    foreach ($mobile_keywords as $keyword) {
        if (strpos($user_agent, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

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

$host = 'localhost';
$database = 'tdawggcat_fa';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT page, date, title FROM fa_readings ORDER BY sort_key";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table of Contents - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 600px;
        }
        th, td {
            padding: 2px 5px;
            border: none;
            text-align: left;
        }
        .no-wrap {
            white-space: nowrap;
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
            text-decoration: underline;
        }
        .navigation {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navigation .left {
            flex: 0 0 auto;
        }
        .navigation a {
            margin: 0 10px;
            text-decoration: none;
            color: #0066cc;
        }
        .navigation a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navigation">
        <div class="left">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user_readings.php">History</a> | <a href="page.php">Today</a>
            <?php else: ?>
                <a href="page.php">Today</a> | <a href="login.php?redirect=toc.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
    <h1>Table of Contents</h1>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Page</th>
                <th class="no-wrap">Day</th>
                <th>Title</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['page']); ?></a></td>
                    <td class="no-wrap"><a href="page.php?page=<?php echo urlencode($row['page']); ?>">
                        <?php 
                        if (isMobile()) {
                            $date = DateTime::createFromFormat('F j', $row['date']);
                            echo $date ? htmlspecialchars($date->format('M j')) : htmlspecialchars($row['date']);
                        } else {
                            echo htmlspecialchars($row['date']);
                        }
                        ?>
                    </a></td>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['title']); ?></a></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No entries found.</p>
    <?php endif; ?>

    <?php
    $conn->close();
    ?>
</body>
</html>
