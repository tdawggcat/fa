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

// Get the current date
$currentDate = new DateTime('2025-03-07'); // Set to March 07, 2025 as per context

// Get the page number from the URL query string, if provided
$page = isset($_GET['page']) ? $_GET['page'] : null;

// Determine the page to display if not provided
if (!$page) {
    // Use Month Day format without leading zeros (e.g., "March 7")
    $monthDay = $currentDate->format('F j'); // e.g., "March 7"
    $dateQuery = "SELECT page FROM fa_readings WHERE date = ?";
    $stmt = $conn->prepare($dateQuery);
    $stmt->bind_param("s", $monthDay);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $page = $row['page'];
    } else {
        // Fallback to the first page (e.g., 1) if no match
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
            margin: 0 0 0; /* No blank lines between paragraphs */
            text-indent: 1em; /* First line indent */
        }
        .today-i-will {
            margin: 15px 0;
        }
        .page-number {
            margin-top: 20px;
            font-size: 0.9em; /* Slightly smaller font size */
        }
        .navigation {
            margin-bottom: 10px; /* Space below navigation, above date */
            display: flex;
            justify-content: space-between; /* Distributes space between sections */
            align-items: center;
        }
        .navigation .left {
            flex: 0 0 auto; /* Fixed width for ToC */
        }
        .navigation .middle {
            flex: 1; /* Takes up remaining space */
            display: flex;
            justify-content: center; /* Centers the < and > links */
            align-items: center;
        }
        .navigation .right {
            flex: 0 0 auto; /* Fixed width for Copy */
        }
        .navigation a, .navigation .copy-button {
            margin: 0 10px; /* Maintains spacing between < and > */
            text-decoration: none;
            color: #0066cc;
        }
        .navigation a:hover, .navigation .copy-button:hover {
            text-decoration: underline;
        }
        .copy-button {
            cursor: pointer;
            color: blue;
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
            // Navigation links: ToC left, < and > middle, Copy right
            echo '<div class="navigation">';
            echo '<div class="left"><a href="toc.php">ToC</a></div>';
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
            // Output reading as stored HTML
            echo '<div class="page-reading">';
            echo $row['reading'];
            echo '</div>';
            // Output today_i_will as stored HTML
            echo '<div class="today-i-will">' . $row['today_i_will'] . '</div>';
            echo '<div class="page-number">Page ' . htmlspecialchars($row['page']) . '</div>';
        } else {
            echo '<p>Page not found: ' . htmlspecialchars($page) . '</p>';
        }
        $stmt->close();
    } else {
        // Display dropdown of all pages, sorted by sort_key
        echo '<h2>Select a Page</h2>';
        echo '<form method="get" action="page.php">';
        echo '<select name="page" onchange="this.form.submit()">';
        echo '<option value="">-- Select a Page --</option>';
        $pages_result->data_seek(0); // Reset result pointer
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
            // Create a temporary div to hold the formatted content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = `
                <div class="page-date">${document.querySelector('.page-date').textContent}</div>
                <div class="page-title">${document.querySelector('.page-title').textContent}</div>
                <div class="page-reading">${document.querySelector('.page-reading').innerHTML}</div>
                ${document.querySelector('.today-i-will') ? `<div class="today-i-will">${document.querySelector('.today-i-will').innerHTML}</div>` : ''}
            `;

            // Apply styles to preserve formatting
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

            // Add to document (hidden) and select the content
            document.body.appendChild(tempDiv);
            const range = document.createRange();
            range.selectNode(tempDiv);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);

            try {
                document.execCommand('copy');
                // Show "Copied!" feedback for 1 second
                const feedback = document.getElementById('copyFeedback');
                feedback.style.display = 'block';
                setTimeout(() => {
                    feedback.style.display = 'none';
                }, 1000); // Hide after 1 second (1000ms)
            } catch (err) {
                alert('Failed to copy content. Please try manually.');
            }

            // Clean up
            window.getSelection().removeAllRanges();
            document.body.removeChild(tempDiv);
        }
    </script>
</body>
</html>
