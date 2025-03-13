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

// Fetch user's meetings
$meetings_query = "SELECT meeting_date, title FROM fa_user_meetings WHERE user_id = ?";
$meetings_stmt = $conn->prepare($meetings_query);
$meetings_stmt->bind_param("i", $user_id);
$meetings_stmt->execute();
$meetings_result = $meetings_stmt->get_result();
$meetings = [];
while ($row = $meetings_result->fetch_assoc()) {
    $meetings[$row['meeting_date']] = $row['title'];
}
$meetings_stmt->close();

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
        
        // Fetch pages read on this date with title from fa_readings, ordered by id
        $query = "SELECT ur.read_date, ur.page, r.date, r.title 
                  FROM fa_user_readings ur 
                  LEFT JOIN fa_readings r ON ur.page = r.page 
                  WHERE ur.user_id = ? AND ur.read_date = ? 
                  ORDER BY ur.id";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $user_id, $read_date);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    // Fetch all reading history for the user, newest first, with title, ordered by read_date then id
    $query = "SELECT ur.read_date, ur.page, r.date, r.title 
              FROM fa_user_readings ur 
              LEFT JOIN fa_readings r ON ur.page = r.page 
              WHERE ur.user_id = ? 
              ORDER BY ur.read_date DESC, ur.id";
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
    <title>My Reading History - Families Anonymous Readings</title>
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
        th, td {
            padding: 2px 5px;
            border: none;
            text-align: left;
        }
        hr {
            border: 0;
            border-top: 1px solid #ccc;
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
    <?php elseif ($result->num_rows > 0): ?>
        <table>
            <tr><td colspan="4"><hr></td></tr> <!-- Top line -->
            <tr>
                <th>Reading Date</th>
                <th>Page</th>
                <th>Day</th>
                <th>Title</th>
            </tr>
            <tr><td colspan="4"><hr></td></tr> <!-- Line after header -->
            <?php 
            $previous_date = null;
            while ($row = $result->fetch_assoc()): 
                $current_date = $row['read_date'];
                $show_date = ($previous_date !== $current_date);
                if ($previous_date !== null && $show_date) {
                    echo '<tr><td colspan="4"><hr></td></tr>'; // Line between dates
                }
            ?>
                <tr>
                    <td>
                        <?php 
                        if ($show_date) {
                            $read_date = new DateTime($row['read_date']);
                            $day_of_week = $read_date->format('l');
                            $formatted_date = $read_date->format('n/j/y');
                            $meeting_title = isset($meetings[$row['read_date']]) ? ' - ' . htmlspecialchars($meetings[$row['read_date']]) : '';
                            $display_text = $formatted_date . $meeting_title;
                            if ($day_of_week === 'Tuesday') {
                                echo '<b><i>' . htmlspecialchars($display_text) . '</i></b>';
                            } else {
                                echo htmlspecialchars($display_text);
                            }
                        }
                        ?>
                    </td>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['page']); ?></a></td>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['date']); ?></a></td>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['title']); ?></a></td>
                </tr>
            <?php 
                $previous_date = $current_date;
            endwhile; 
            ?>
            <tr><td colspan="4"><hr></td></tr> <!-- Bottom line -->
        </table>
    <?php else: ?>
        <p>No reading history found.</p>
    <?php endif; ?>

    <?php
    $stmt->close();
    $conn->close();
    ?>
</body>
</html>
