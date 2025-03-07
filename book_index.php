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

// Set a higher group_concat_max_len to avoid truncation
$conn->query("SET SESSION group_concat_max_len = 10000");

// Query to fetch index entries, joined with fa_readings and sorted by sort_key
$query = "SELECT i.word, MAX(i.reference) AS reference, 
    GROUP_CONCAT(
        CASE 
            WHEN i.is_in_title = TRUE THEN 
                CONCAT(
                    COALESCE(i.suffix, ''), 
                    ' ',
                    '<a href=\"page.php?page=', i.page, '\"><b>', i.page, '</b></a>'
                )
            ELSE 
                CONCAT(
                    COALESCE(i.suffix, ''), 
                    ' ',
                    '<a href=\"page.php?page=', i.page, '\">', i.page, '</a>'
                )
        END 
        ORDER BY r.sort_key SEPARATOR ', '
    ) AS pages
FROM fa_index i
LEFT JOIN fa_readings r ON i.page = r.page
GROUP BY i.word
ORDER BY i.word";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.5;
        }
        .index-entry {
            margin-bottom: 5px;
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
    <h1>Index</h1>
    <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="index-entry">';
            echo htmlspecialchars($row['word']);
            if (!empty($row['reference'])) {
                // If there's a reference, display "See <reference>"
                echo ', See ' . htmlspecialchars($row['reference']);
            } elseif (!empty($row['pages'])) {
                // If there's no reference but there are pages, display the pages
                echo ', ' . $row['pages'];
            }
            echo '</div>';
        }
    } else {
        echo '<p>No index entries found.</p>';
    }

    $conn->close();
    ?>
</body>
</html>
