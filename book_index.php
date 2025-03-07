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

// Query to fetch index entries, grouped by word and sorted alphabetically
$query = "SELECT word, GROUP_CONCAT(
    CASE 
        WHEN is_in_title = TRUE THEN CONCAT('<a href=\"page.php?page=', page, '\"><b>', page, '</b></a>')
        ELSE CONCAT('<a href=\"page.php?page=', page, '\">', page, '</a>')
    END 
    ORDER BY page SEPARATOR ', '
) AS pages
FROM fa_index
GROUP BY word
ORDER BY word";

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
            echo htmlspecialchars($row['word']) . ', ' . $row['pages'];
            echo '</div>';
        }
    } else {
        echo '<p>No index entries found.</p>';
    }

    $conn->close();
    ?>
</body>
</html>
