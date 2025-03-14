<?php
session_start();

date_default_timezone_set('America/Chicago');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: page.php");
    exit();
}

// Handle "Read" button submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read']) && isset($_SESSION['user_id'])) {
    $page = $_POST['page'] ?? '';
    $user_id = $_SESSION['user_id'];
    $today = (new DateTime())->format('Y-m-d');

    $cred_file = '/home/tdawggcat/.mysql_user';
    if (!file_exists($cred_file)) {
        $response = ['success' => false, 'error' => 'Credentials file not found'];
    } else {
        $credentials = trim(file_get_contents($cred_file));
        list($username, $password) = explode(':', $credentials, 2);
        $conn = new mysqli('localhost', trim($username), trim($password), 'tdawggcat_fa');
        
        if ($conn->connect_error) {
            $response = ['success' => false, 'error' => 'Database connection failed'];
        } else {
            $stmt = $conn->prepare("INSERT INTO fa_user_readings (user_id, page, read_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $page, $today);
            $success = $stmt->execute();
            $response = ['success' => $success, 'page' => $page];
            $stmt->close();
            $conn->close();
        }
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        ob_end_flush();
        exit();
    } else {
        header("Location: page.php?page=" . urlencode($page));
        exit();
    }
}

// Database connection
$cred_file = '/home/tdawggcat/.mysql_user';
if (!file_exists($cred_file)) die("Error: Credentials file not found");
$credentials = trim(file_get_contents($cred_file));
list($username, $password) = explode(':', $credentials, 2);
$conn = new mysqli('localhost', trim($username), trim($password), 'tdawggcat_fa');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get current date
$currentDate = new DateTime();
$today = $currentDate->format('Y-m-d');
$monthDay = $currentDate->format('F j');

// Get page from URL or default to today’s page
$page = isset($_GET['page']) ? $_GET['page'] : null;
if (!$page) {
    $stmt = $conn->prepare("SELECT page FROM fa_readings WHERE date = ?");
    $stmt->bind_param("s", $monthDay);
    $stmt->execute();
    $result = $stmt->get_result();
    $page = ($row = $result->fetch_assoc()) ? $row['page'] : 1;
    $stmt->close();
}

// Fetch pages for navigation and dropdown
$pages_result = $conn->query("SELECT page, title FROM fa_readings ORDER BY sort_key");
$pages = [];
while ($row = $pages_result->fetch_assoc()) $pages[] = $row['page'];
$current_index = $page ? array_search($page, $pages) : -1;
$prev_page = ($current_index > 0) ? $pages[$current_index - 1] : null;
$next_page = ($current_index < count($pages) - 1) ? $pages[$current_index + 1] : null;

// Check reading history
$read_dates = [];
$read_today = false;
if ($page && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT read_date FROM fa_user_readings WHERE user_id = ? AND page = ? ORDER BY read_date DESC");
    $stmt->bind_param("is", $user_id, $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['read_date']);
        $read_dates[] = $date->format('n/j/y');
        if ($row['read_date'] === $today) $read_today = true;
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
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
        select { margin: 10px 0; padding: 5px; }
        .page-date { font-size: 1.2em; font-weight: bold; }
        .page-title { font-size: 1.5em; font-weight: bold; margin: 10px 0; }
        .page-reading p { margin: 0 0 0; text-indent: 1em; }
        .today-i-will { margin: 15px 0; }
        .page-number { margin-top: 20px; font-size: 0.9em; }
        .read-dates { font-size: 0.9em; color: #555; margin-top: 5px; }
        .read-dates a { text-decoration: none; color: #0066cc; }
        .read-dates a:hover { text-decoration: underline; }
        .navigation { margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navigation .left { flex: 0 0 auto; }
        .navigation .middle { flex: 1; display: flex; justify-content: center; align-items: center; }
        .navigation .right { flex: 0 0 auto; }
        .navigation a { margin: 0 10px; text-decoration: none; color: #0066cc; }
        .navigation a:hover { text-decoration: underline; }
        .navigation .copy-button { margin: 0 10px; cursor: pointer; color: blue; }
        .navigation .copy-button:hover { text-decoration: underline; }
        #copyFeedback { position: fixed; top: 20px; right: 20px; background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; display: none; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .read-button, .logout-button { margin-top: 10px; padding: 5px 10px; }
        .button-row { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .left-buttons, .right-buttons { flex: 0 0 auto; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); text-align: center; }
        .modal-buttons { margin-top: 15px; }
        .modal-buttons button { padding: 5px 15px; margin: 0 10px; cursor: pointer; }
    </style>
</head>
<body>
    <?php
    if ($page) {
        $stmt = $conn->prepare("SELECT page, date, title, reading, today_i_will FROM fa_readings WHERE page = ?");
        $stmt->bind_param("s", $page);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo '<div class="navigation">';
            echo '<div class="left">';
            echo '<a href="toc.php">Contents</a>';
            if (isset($_SESSION['user_id'])) echo ' | <a href="user_readings.php">History</a>';
            else echo ' | <a href="' . ($page ? "login.php?redirect=page.php?page=" . urlencode($page) : "login.php?redirect=page.php") . '">Login</a>';
            echo '</div>';
            echo '<div class="middle">';
            if ($prev_page !== null) echo '<a href="page.php?page=' . htmlspecialchars($prev_page) . '"><</a>';
            if ($next_page !== null) echo '<a href="page.php?page=' . htmlspecialchars($next_page) . '">></a>';
            echo '</div>';
            echo '<div class="right"><span class="copy-button" onclick="copyToClipboard()">Copy</span></div>';
            echo '</div>';
            echo '<div class="page-date">' . htmlspecialchars($row['date']) . '</div>';
            echo '<div class="page-title">' . htmlspecialchars($row['title']) . '</div>';
            echo '<div class="page-reading">' . $row['reading'] . '</div>';
            echo '<div class="today-i-will">' . $row['today_i_will'] . '</div>';
            echo '<div class="page-number">Page ' . htmlspecialchars($row['page']) . '</div>';

            if (!empty($read_dates)) {
                $date_links = array_map(fn($date) => '<a href="user_readings.php?date=' . urlencode($date) . '">' . $date . '</a>', $read_dates);
                echo '<div class="read-dates">' . implode(', ', $date_links) . '</div>';
            }

            if (isset($_SESSION['user_id'])) {
                echo '<div class="button-row">';
                echo '<div class="left-buttons">';
                if (!$read_today) {
                    echo '<form method="post" id="readForm" class="read-form">';
                    echo '<input type="hidden" name="page" value="' . htmlspecialchars($page) . '">';
                    echo '<button type="submit" name="mark_read" class="read-button" id="readButton">Read</button>';
                    echo '</form>';
                }
                echo '</div>';
                echo '<div class="right-buttons">';
                echo '<form method="post">';
                echo '<button type="submit" name="logout" class="logout-button">Logout</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>Page not found: ' . htmlspecialchars($page) . '</p>';
        }
        $stmt->close();
    } else {
        echo '<div class="navigation">';
        echo '<div class="left">';
        echo '<a href="toc.php">Contents</a>';
        if (isset($_SESSION['user_id'])) echo ' | <a href="user_readings.php">History</a>';
        else echo ' | <a href="login.php?redirect=page.php">Login</a>';
        echo '</div>';
        echo '</div>';
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

        if (isset($_SESSION['user_id'])) {
            echo '<div class="button-row">';
            echo '<div class="left-buttons"></div>';
            echo '<div class="right-buttons">';
            echo '<form method="post">';
            echo '<button type="submit" name="logout" class="logout-button">Logout</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
    }
    $conn->close();
    ob_end_flush();
    ?>

    <!-- Custom Modal -->
    <div id="meetingModal" class="modal">
        <div class="modal-content">
            <p>No meeting exists for today. Add one?</p>
            <div class="modal-buttons">
                <button id="modalYes">Yes</button>
                <button id="modalNo">No</button>
            </div>
        </div>
    </div>

    <div id="copyFeedback">Copied!</div>
    <script>
        const readButton = document.getElementById('readButton');
        if (readButton) {
            readButton.addEventListener('click', function(e) {
                e.preventDefault();
                const form = document.getElementById('readForm');
                const page = form.querySelector('input[name="page"]').value;

                fetch('check_meeting.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'date=<?php echo $today; ?>'
                })
                .then(response => {
                    if (!response.ok) throw new Error('Check meeting failed: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.hasMeeting || data.noMeeting) {
                        markRead(page);
                    } else {
                        showMeetingModal(page);
                    }
                })
                .catch(err => console.error('Error checking meeting:', err));
            });
        }

        function showMeetingModal(page) {
            const modal = document.getElementById('meetingModal');
            const yesButton = document.getElementById('modalYes');
            const noButton = document.getElementById('modalNo');

            modal.style.display = 'block';

            yesButton.onclick = function() {
                modal.style.display = 'none';
                const title = prompt('Enter Meeting Title');
                if (title && title.trim() !== '') {
                    fetch('add_meeting.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'date=<?php echo $today; ?>&title=' + encodeURIComponent(title) + '&no_meeting=0'
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Add meeting failed: ' + response.status);
                        return response.text();
                    })
                    .then(() => markRead(page))
                    .catch(err => console.error('Error adding meeting:', err));
                }
            };

            noButton.onclick = function() {
                modal.style.display = 'none';
                fetch('add_meeting.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'date=<?php echo $today; ?>&no_meeting=1'
                })
                .then(response => {
                    if (!response.ok) throw new Error('Add meeting failed: ' + response.status);
                    return response.text();
                })
                .then(text => {
                    if (text === 'Meeting added') {
                        markRead(page);
                    }
                })
                .catch(err => console.error('Error adding meeting:', err));
            };
        }

        function markRead(page) {
            fetch('page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'mark_read=1&page=' + encodeURIComponent(page)
            })
            .then(response => {
                if (!response.ok) throw new Error('Mark read failed: ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = 'page.php?page=' + encodeURIComponent(page);
                } else {
                    console.error('Read mark failed on server:', data.error);
                }
            })
            .catch(err => console.error('Error marking read:', err));
        }

        function copyToClipboard() {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = `
                <div class="page-date">${document.querySelector('.page-date').textContent}</div>
                <div class="page-title">${document.querySelector('.page-title').textContent}</div>
                <div class="page-reading">${document.querySelector('.page-reading').innerHTML}</div>
                ${document.querySelector('.today-i-will') ? `<div class="today-i-will">${document.querySelector('.today-i-will').innerHTML}</div>` : ''}
            `;
            tempDiv.style.cssText = 'font-family: Arial, sans-serif; line-height: 1.5; white-space: pre-wrap;';
            tempDiv.querySelector('.page-date').style.cssText = 'font-size: 1.2em; font-weight: bold;';
            tempDiv.querySelector('.page-title').style.cssText = 'font-size: 1.5em; font-weight: bold; margin: 10px 0;';
            tempDiv.querySelector('.page-reading p').style.cssText = 'margin: 0 0 0; text-indent: 1em;';
            if (tempDiv.querySelector('.today-i-will')) tempDiv.querySelector('.today-i-will').style.cssText = 'margin: 15px 0;';
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
