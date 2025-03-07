<?php
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

// Query to fetch all pages, sorted by sort_key
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
            line-height: 1.3;
        }
        h1 {
            text-align: center;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        td {
            padding: 2px 10px;
            border: none; /* No border lines */
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Table of Contents</h1>
    <table border="0">
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $link = "page.php?page=" . urlencode($row['page']);
                // Only include dash if date is present
                $display_text = !empty(trim($row['date'])) ? htmlspecialchars($row['date']) . " - " . htmlspecialchars($row['title']) : htmlspecialchars($row['title']);
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['page']) . '</td>';
                echo '<td><a href="' . $link . '">' . $display_text . '</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="2">No entries found.</td></tr>';
        }
        ?>
    </table>

    <?php
    $conn->close();
    ?>
</body>
</html>
