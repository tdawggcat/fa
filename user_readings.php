<?php
session_start(); // Start session to track logged-in user

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=user_readings.php");
    exit();
}

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

$user_id = $_SESSION['user_id'];

// Check if a date is supplied
if (isset($_GET['date'])) {
    // Convert m/d/yy to Y-m-d for database query
    $date_input = trim($_GET['date']);
    $date = DateTime::createFromFormat('n/j/y', $date_input);
    if ($date === false) {
        $error = "Invalid date format. Use m/d/yy (e.g., 3/13/25).";
    } else {
        $read_date = $date->format('Y-m-d'); // e.g., "2025-03-13"
        $display_date = $date->format('n/j/y'); // e.g., "3/13/25"
        
        // Fetch pages read on this date with title from fa_readings
        $query = "SELECT ur.page, r.date, r.title 
                  FROM fa_user_readings ur 
                  LEFT JOIN fa_readings r ON ur.page = r.page 
                  WHERE ur.user_id = ? AND ur.read_date = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $user_id, $read_date);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    // Fetch all reading history for the user, newest first, with title
    $query = "SELECT ur.read_date, ur.page, r.date, r.title 
              FROM fa_user_readings ur 
              LEFT JOIN fa_readings r ON ur.page = r.page 
              WHERE ur.user_id = ? 
              ORDER BY ur.read_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My estudioading History - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            margin-bottom: 20px;
        }
        h1 a {
            text-decoration: none;
            color: #0066cc;
        }
        h1 a:hover {
            text-decoration: underline;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 600px;
        }
        td {
            padding: 5px 10px;
            border: none;
        }
        hr {
            border: 0;
            border-top: 1px solid #ccc;
            margin: 5px 0;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin: 5px 0;
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <h1><a href="user_readings.php">My Reading History</a></h1>

    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php elseif (isset($_GET['date'])): ?>
        <h2>Readings on <?php echo htmlspecialchars($display_date); ?></h2>
        <?php if ($result->num_rows > 0): ?>
            <ul>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <li>
                        <a href="page.php?page=<?php echo urlencode($row['page']); ?>">
                            <?php 
                            echo htmlspecialchars($row['page'] . ' - ' . $row['date'] . ' - ' . $row['title']); 
                            ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No readings found for this date.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <tr><td colspan="2"><hr></td></tr> <!-- Start with horizontal line -->
                <?php 
                $previous_date = null;
                while ($row = $result->fetch_assoc()): 
                    $current_date = $row['read_date'];
                    $show_date = ($previous_date !== $current_date);
                    if ($previous_date !== null && $show_date) {
                        echo '<tr><td colspan="2"><hr></td></tr>'; // Line between date changes
                    }
                ?>
                    <tr>
                        <td>
                            <?php 
                            if ($show_date) {
                                $read_date = new DateTime($row['read_date']);
                                echo $read_date->format('n/j/y');
                            }
                            ?>
                        </td>
                        <td>
                            <a href="page.php?page=<?php echo urlencode($row['page']); ?>">
                                <?php 
                                echo htmlspecialchars($row['page'] . ' - ' . $row['date'] . ' - ' . $row['title']); 
                                ?>
                            </a>
                        </td>
                    </tr>
                <?php 
                    $previous_date = $current_date;
                endwhile; 
                ?>
                <tr><td colspan="2"><hr></td></tr> <!-- End with horizontal line -->
            </table>
        <?php else: ?>
            <p>No reading history found.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    $stmt->close();
    $conn->close();
    ?>
</body>
</html>
