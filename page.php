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

// Get the current date
$currentDate = new DateTime();
$today = $currentDate->format('Y-m-d'); // For database comparison (e.g., "2025-03-13")
$monthDay = $currentDate->format('F j'); // For page lookup (e.g., "March 13")

// Handle "Read" button submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read']) && isset($_SESSION['user_id'])) {
    $page = $_POST['page'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO fa_user_readings (user_id, page, read_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $page, $today);
    $stmt->execute();
    $stmt->close();

    // Redirect to the same page to refresh the display
    header("Location: page.php?page=" . urlencode($page));
    exit();
}

// Get the page number from the URL query string, if provided
$page = isset($_GET['page']) ? $_GET['page'] : null;

// Determine the page to display if not provided
if (!$page) {
    $dateQuery = "SELECT page FROM fa_readings WHERE date = ?";
    $stmt = $conn->prepare($dateQuery);
    $stmt->bind_param("s", $monthDay);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $page = $row['page'];
    } else {
        $page = 1;
    }
    $stmt->close();
}

// Query to fetch all pages for the dropdown and navigation, sorted by sort_key
$pages_query = "SELECT page, title FROM fa_readings ORDER BY sort_key";
$pages_result = $conn->query($pages_query);

// Fetch all pages to determine previous and next
$pages = [];
while ($row = $pages_result->fetch_assoc()) {
    $pages[] = $row['page'];
}
$current_index = $page ? array_search($page, $pages) : -1;
$prev_page = ($current_index > 0) ? $pages[$current_index - 1] : null;
$next_page = ($current_index < count($pages) - 1) ? $pages[$current_index + 1] : null;

// Check reading history and today's status if logged in
$read_dates = [];
$read_today = false;
if ($page && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Fetch all read dates for this user and page
    $read_query = "SELECT read_date FROM fa_user_readings WHERE user_id = ? AND page = ? ORDER BY read_date DESC";
    $stmt = $conn->prepare($read_query);
    $stmt->bind_param("is", $user_id, $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['read_date']);
        $read_dates[] = $date->format('n/j/y'); // Format as m/d/yy (e.g., "3/13/25")
        if ($row['read_date'] === $today) {
            $read_today = true;
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.5;
        }
        select {
            margin: 10px 0;
            padding: 5px;
        }
        .page-date {
            font-size: 1.2em;
            font-weight: bold;
        }
        .page-title {
            font-size: 1.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .page-reading p {
            margin: 0 0 0;
            text-indent: 1em;
        }
        .today-i-will {
            margin: 15px 0;
        }
        .page-number {
            margin-top: 20px;
            font-size: 0.9em;
        }
        .read-dates {
            font-size: 0.9em;
            color: #555;
            margin-top: 5px;
        }
        .read-dates a {
            text-decoration: none;
            color: #0066cc;
        }
        .read-dates a:hover {
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
        .navigation .middle {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .navigation .right {
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
        .navigation .copy-button {
            margin: 0 10px;
            cursor: pointer;
            color: blue;
        }
        .navigation .copy-button:hover {
            text-decoration: underline;
        }
        #copyFeedback {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .read-button {
            margin-top: 10px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <?php
    if ($page) {
        // Display the specific page
        $page_query = "SELECT page, date, title, reading, today_i_will FROM fa_readings WHERE page = ?";
        $stmt = $conn->prepare($page_query);
        $stmt->bind_param("s", $page);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo '<div class="navigation">';
            echo '<div class="left">';
            echo '<a href="toc.php">Contents</a>';
            if (isset($_SESSION['user_id'])) {
                echo ' | <a href="user_readings.php">History</a>';
            } else {
                $redirect = $page ? "login.php?redirect=page.php?page=" . urlencode($page) : "login.php?redirect=page.php";
                echo ' | <a href="' . $redirect . '">Login</a>';
            }
            echo '</div>';
            echo '<div class="middle">';
            if ($prev_page !== null) {
                echo '<a href="page.php?page=' . htmlspecialchars($prev_page) . '"><</a>';
            }
            if ($next_page !== null) {
                echo '<a href="page.php?page=' . htmlspecialchars($next_page) . '">></a>';
            }
            echo '</div>';
            echo '<div class="right"><span class="copy-button" onclick="copyToClipboard()">Copy</span></div>';
            echo '</div>';
            echo '<div class="page-date">' . htmlspecialchars($row['date']) . '</div>';
            echo '<div class="page-title">' . htmlspecialchars($row['title']) . '</div>';
            echo '<div class="page-reading">';
            echo $row['reading'];
            echo '</div>';
            echo '<div class="today-i-will">' . $row['today_i_will'] . '</div>';
            echo '<div class="page-number">Page ' . htmlspecialchars($row['page']) . '</div>';

            // Display read dates as clickable links if logged in and has history
            if (!empty($read_dates)) {
                $date_links = array_map(function($date) {
                    return '<a href="user_readings.php?date=' . urlencode($date) . '">' . $date . '</a>';
                }, $read_dates);
                echo '<div class="read-dates">' . implode(', ', $date_links) . '</div>';
            }

            // Show "Read" button if logged in and not read today
            if (isset($_SESSION['user_id']) && !$read_today) {
                echo '<form method="post" class="read-form">';
                echo '<input type="hidden" name="page" value="' . htmlspecialchars($page) . '">';
                echo '<button type="submit" name="mark_read" class="read-button">Read</button>';
                echo '</form>';
            }
        } else {
            echo '<p>Page not found: ' . htmlspecialchars($page) . '</p>';
        }
        $stmt->close();
    } else {
        // Display dropdown of all pages
        echo '<h2>Select a Page</h2>';
        echo '<form method="get" action="page.php">';
        echo '<select name="page" onchange="this.form.submit()">';
        echo '<option value="">-- Select a Page --</option>';
        $pages_result->data_seek(0);
        while ($row = $pages_result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['page']) . '">' . htmlspecialchars($row['page']) . ' - ' . htmlspecialchars($row['title']) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    }

    $conn->close();
    ?>
    <div id="copyFeedback">Copied!</div>
    <script>
        function copyToClipboard() {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = `
                <div class="page-date">${document.querySelector('.page-date').textContent}</div>
                <div class="page-title">${document.querySelector('.page-title').textContent}</div>
                <div class="page-reading">${document.querySelector('.page-reading').innerHTML}</div>
                ${document.querySelector('.today-i-will') ? `<div class="today-i-will">${document.querySelector('.today-i-will').innerHTML}</div>` : ''}
            `;
            tempDiv.style.cssText = `
                font-family: Arial, sans-serif;
                line-height: 1.5;
                white-space: pre-wrap;
            `;
            tempDiv.querySelector('.page-date').style.cssText = 'font-size: 1.2em; font-weight: bold;';
            tempDiv.querySelector('.page-title').style.cssText = 'font-size: 1.5em; font-weight: bold; margin: 10px 0;';
            tempDiv.querySelector('.page-reading p').style.cssText = 'margin: 0 0 0; text-indent: 1em;';
            if (tempDiv.querySelector('.today-i-will')) {
                tempDiv.querySelector('.today-i-will').style.cssText = 'margin: 15px 0;';
            }
            document.body.appendChild(tempDiv);
            const range = document.createRange();
            range.selectNode(tempDiv);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            try {
                document.execCommand('copy');
                const feedback = document.getElementById('copyFeedback');
                feedback.style.display = 'block';
                setTimeout(() => feedback.style.display = 'none', 1000);
            } catch (err) {
                alert('Failed to copy content. Please try manually.');
            }
            window.getSelection().removeAllRanges();
            document.body.removeChild(tempDiv);
        }
    </script>
</body>
</html>
