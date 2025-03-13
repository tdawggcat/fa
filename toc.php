<?php
session_start(); // Add session start

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Page</th>
                <th>Day</th>
                <th>Title</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['page']); ?></a></td>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['date']); ?></a></td>
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
