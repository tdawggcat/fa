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

// Handle "Add Note" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note' && isset($_SESSION['user_id'])) {
    $page = $_POST['page'] === '' ? null : $_POST['page'];
    $meeting_id = $_POST['meeting_id'] === '' ? null : $_POST['meeting_id'];
    $note = $_POST['note'] ?? '';
    $user_id = $_SESSION['user_id'];

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
            $stmt = $conn->prepare("INSERT INTO fa_notes (user_id, reading_id, meeting_id, note) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $page, $meeting_id, $note);
            $success = $stmt->execute();
            
            if ($page) {
                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM fa_notes WHERE user_id = ? AND reading_id = ?");
                $count_stmt->bind_param("is", $user_id, $page);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $note_count = $count_result->fetch_assoc()['count'];
                $count_stmt->close();
            } else {
                $note_count = null;
            }
            
            $response = ['success' => $success, 'note_count' => $note_count];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Handle "Delete Note" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_note' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $note_id = (int)$_POST['note_id'];

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
            $stmt = $conn->prepare("DELETE FROM fa_notes WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $note_id, $user_id);
            $success = $stmt->execute();
            $response = ['success' => $success];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Handle "Get Page Notes" AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_page_notes' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $page = $_POST['page'];

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
            $stmt = $conn->prepare("
                SELECT n.id, n.meeting_id, n.note, n.created_at, m.meeting_date, m.title AS meeting_title
                FROM fa_notes n
                LEFT JOIN fa_user_meetings m ON n.meeting_id = m.id
                WHERE n.user_id = ? AND n.reading_id = ?
                ORDER BY n.created_at DESC
            ");
            $stmt->bind_param("is", $user_id, $page);
            $stmt->execute();
            $result = $stmt->get_result();
            $notes = [];
            while ($row = $result->fetch_assoc()) {
                $notes[] = $row;
            }
            $response = ['success' => true, 'notes' => $notes];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Stop further execution
}

// Handle "Book Notes" AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_book_notes' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

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
            $stmt = $conn->prepare("
                SELECT n.id, n.note, n.created_at, n.reading_id, m.meeting_date, m.title AS meeting_title, 
                       r.page, r.date AS reading_date, r.title AS reading_title
                FROM fa_notes n
                LEFT JOIN fa_user_meetings m ON n.meeting_id = m.id
                LEFT JOIN fa_readings r ON n.reading_id = r.page
                WHERE n.user_id = ?
                ORDER BY CASE WHEN n.reading_id IS NULL THEN 0 ELSE 1 END, n.reading_id, n.id
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $notes = [];
            while ($row = $result->fetch_assoc()) {
                $notes[] = $row;
            }
            $response = ['success' => true, 'notes' => $notes];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Handle "Add Annotation" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_annotation' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $page = $_POST['page'];
    $part = $_POST['part'];
    $start_pos = (int)$_POST['start_pos'];
    $end_pos = (int)$_POST['end_pos'];
    $style = $_POST['style'];

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
            $stmt = $conn->prepare("INSERT INTO fa_annotations (user_id, page, part, start_pos, end_pos, style) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississ", $user_id, $page, $part, $start_pos, $end_pos, $style);
            $success = $stmt->execute();
            $response = ['success' => $success];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Handle "Delete Annotation" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_annotation' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $annotation_id = (int)$_POST['annotation_id'];

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
            $stmt = $conn->prepare("DELETE FROM fa_annotations WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $annotation_id, $user_id);
            $success = $stmt->execute();
            $response = ['success' => $success];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Handle "Toggle Annotations" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_annotations' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $state = (int)$_POST['state'];

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
            $stmt = $conn->prepare("UPDATE fa_users SET annotations = ? WHERE id = ?");
            $stmt->bind_param("ii", $state, $user_id);
            $success = $stmt->execute();
            $response = ['success' => $success];
            $stmt->close();
            $conn->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
    exit();
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

// Fetch notes with meeting data and created_at for the current page and user
$notes = [];
$note_count = 0;
if ($page && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT n.id, n.meeting_id, n.note, n.created_at, m.meeting_date, m.title 
        FROM fa_notes n
        LEFT JOIN fa_user_meetings m ON n.meeting_id = m.id
        WHERE n.user_id = ? AND n.reading_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->bind_param("is", $user_id, $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    $note_count = count($notes);
    $stmt->close();
}

// Fetch meetings for the user
$meetings = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, meeting_date, title FROM fa_user_meetings WHERE user_id = ? ORDER BY meeting_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $meetings[] = $row;
    }
    $stmt->close();
}

// Fetch user annotations preference
$annotations_on = 1; // Default to on
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT annotations FROM fa_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $annotations_on = (int)$row['annotations'];
    }
    $stmt->close();
}

// Fetch annotations for the current page
$annotations = [];
$annotation_count = 0;
$has_failed_annotations = false;
if ($page && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, part, start_pos, end_pos, style FROM fa_annotations WHERE user_id = ? AND page = ?");
    $stmt->bind_param("is", $user_id, $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $annotations[] = $row;
        $annotation_count++;
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
        select { margin: 10px 0; padding: 5px; width: 100%; }
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
        #toggleFeedback { position: fixed; top: 20px; left: 20px; background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; display: none; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .read-button, .logout-button, .notes-button, .manage-annotations-button { margin-top: 10px; padding: 5px 10px; cursor: pointer; border: 1px solid #ccc; background-color: #fff; color: #000; border-radius: 3px; min-width: 60px; text-align: center; }
        .read-button:hover, .logout-button:hover, .notes-button:hover, .manage-annotations-button:hover { background-color: #f0f0f0; }
        .button-row { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .left-buttons, .middle-buttons, .right-buttons { flex: 0 0 auto; }
        .middle-buttons { display: flex; gap: 10px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); text-align: center; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .notes-modal-content { text-align: left; font-size: 14px; }
        .notes-modal-content h2 { font-size: 1.2em; }
        .notes-modal-content hr { margin: 10px 0; }
        .note-timestamp { font-size: 12px; color: #666; margin-top: 5px; }
        .modal-buttons { margin-top: 15px; display: flex; justify-content: space-between; }
        .modal-buttons button { padding: 5px 15px; cursor: pointer; }
        .styling-buttons { margin: 10px 0; display: flex; gap: 5px; justify-content: center; }
        .styling-buttons button { padding: 5px 10px; border: 1px solid #ccc; background-color: #f0f0f0; cursor: pointer; }
        .styling-buttons button:hover { background-color: #e0e0e0; }
        #noteText { width: 100%; min-height: 100px; padding: 5px; border: 1px solid #ccc; margin-bottom: 10px; outline: none; }
        #noteText:empty:before { content: attr(placeholder); color: #999; }
        #annotationPopup { position: absolute; background: white; border: 1px solid #ccc; padding: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 1000; display: none; }
        #annotationPopup button { margin: 0 5px; padding: 3px 8px; cursor: pointer; }
        .highlight { background-color: #ffff99; }
        .underline { border-bottom: 2px solid #000; }
        .annotation-failed { opacity: 0.5; }
        .manage-annotations-list { text-align: left; max-height: 300px; overflow-y: auto; }
        .manage-annotations-list div { margin: 10px 0; }
        .manage-annotations-list .failed { color: red; }
        .delete-note, .delete-annotation { margin-left: 10px; cursor: pointer; color: #666; font-size: 16px; }
        .delete-note:hover, .delete-annotation:hover { color: #ff0000; }
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
            $page_date = $row['date'];
            $page_title = $row['title'];
            $reading_text = $row['reading'];
            $today_i_will_text = $row['today_i_will'];
            $reading_plain = strip_tags($reading_text);
            $today_i_will_plain = strip_tags($today_i_will_text);
            echo '<div class="navigation">';
            echo '<div class="left">';
            echo '<a href="toc.php">Contents</a> | <a href="book_index.php">Index</a>';
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
            echo '<div class="page-reading" id="readingText">' . $reading_text . '</div>';
            echo '<div class="today-i-will" id="todayIWillText">' . $today_i_will_text . '</div>';
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
                echo '<div class="middle-buttons">';
                echo '<button id="notesButton" class="notes-button">Notes' . ($note_count > 0 ? " ($note_count)" : "") . '</button>';
                echo '<button id="toggleAnnotations" class="notes-button">' . ($annotations_on ? '<s>Anno</s>' : 'Anno') . '</button>';
                if ($annotations_on) {
                    echo '<button id="manageAnnotationsButton" class="manage-annotations-button">Man Anno (' . ($has_failed_annotations ? '!' : $annotation_count) . ')</button>';
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
        echo '<a href="toc.php">Contents</a> | <a href="book_index.php">Index</a>';
        if (isset($_SESSION['user_id'])) echo ' | <a href="user_readings.php">History</a>';
        else echo ' | <a href="login.php?redirect=page.php">Login</a>';
        echo '</div>';
        echo '<div class="middle"></div>';
        echo '<div class="right"></div>';
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
            echo '<div class="middle-buttons">';
            echo '<button id="notesButton" class="notes-button">Notes</button>';
            echo '<button id="toggleAnnotations" class="notes-button">' . ($annotations_on ? '<s>Anno</s>' : 'Anno') . '</button>';
            if ($annotations_on) {
                echo '<button id="manageAnnotationsButton" class="manage-annotations-button">Man Anno (' . ($has_failed_annotations ? '!' : $annotation_count) . ')</button>';
            }
            echo '</div>';
            echo '<div class="right-buttons">';
            echo '<form method="post">';
            echo '<button type="submit" name="logout" class="logout-button">Logout</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
    }
    ?>

    <!-- Meeting Modal -->
    <div id="meetingModal" class="modal">
        <div class="modal-content">
            <p>No meeting exists for today. Add one?</p>
            <div class="modal-buttons">
                <button id="modalYes">Yes</button>
                <button id="modalNo">No</button>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div id="notesModal" class="modal">
        <div class="modal-content notes-modal-content">
            <h2>Notes - Page <?php echo htmlspecialchars($page); ?> - <?php echo htmlspecialchars($page_date); ?> - <?php echo htmlspecialchars($page_title); ?></h2>
            <div id="notesContent">
                <?php
                if (!empty($notes)) {
                    foreach ($notes as $index => $note) {
                        if ($note['meeting_id'] && $note['meeting_date'] && $note['title']) {
                            echo '<b><i>' . htmlspecialchars($note['meeting_date'] . ' - ' . $note['title']) . '</i></b><br>';
                        }
                        echo $note['note'];
                        $created_at = new DateTime($note['created_at']);
                        echo '<div class="note-timestamp">' . $created_at->format('n/j/Y g:i A') . 
                             ' <span class="delete-note" onclick="deleteNote(' . $note['id'] . ')">🗑️</span></div>';
                        if ($index < count($notes) - 1) {
                            echo '<hr>';
                        }
                    }
                } else {
                    echo '<p>No notes available for this page.</p>';
                }
                ?>
            </div>
            <div class="modal-buttons">
                <button onclick="hideNotesModal()">Close</button>
                <button onclick="showAddNoteModal()">Add</button>
                <button id="toggleNotesButton" onclick="toggleNotes()">Book Notes</button>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="addNoteModal" class="modal">
        <div class="modal-content">
            <h2>Add a Note</h2>
            <form id="addNoteForm">
                <label for="notePage">Page:</label>
                <select name="page" id="notePage">
                    <option value="">No Page</option>
                    <?php
                    $pages_result->data_seek(0);
                    while ($row = $pages_result->fetch_assoc()) {
                        $selected = $row['page'] === $page ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($row['page']) . '"' . $selected . '>' . htmlspecialchars($row['page']) . ' - ' . htmlspecialchars($row['title']) . '</option>';
                    }
                    ?>
                </select>

                <label for="noteMeeting">Meeting:</label>
                <select name="meeting_id" id="noteMeeting">
                    <option value="">No Meeting</option>
                    <?php
                    foreach ($meetings as $meeting) {
                        $selected = $meeting['meeting_date'] === $today ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($meeting['id']) . '"' . $selected . '>' . htmlspecialchars($meeting['meeting_date'] . ' - ' . $meeting['title']) . '</option>';
                    }
                    ?>
                </select>

                <label for="noteText">Note:</label>
                <div class="styling-buttons">
                    <button type="button" onclick="applyStyle('bold')">B</button>
                    <button type="button" onclick="applyStyle('italic')">I</button>
                    <button type="button" onclick="applyStyle('underline')">U</button>
                    <button type="button" onclick="applyStyle('highlight')">H</button>
                </div>
                <div id="noteText" contenteditable="true" placeholder="Type your note here..."></div>

                <div class="modal-buttons">
                    <button type="submit">Save</button>
                    <button type="button" onclick="hideAddNoteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Annotations Modal -->
    <div id="manageAnnotationsModal" class="modal">
        <div class="modal-content">
            <h2>Manage Annotations</h2>
            <div id="manageAnnotationsContent" class="manage-annotations-list"></div>
            <div class="modal-buttons">
                <button onclick="hideManageAnnotationsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Annotation Popup -->
    <div id="annotationPopup">
        <button onclick="addAnnotation('highlight')">Highlight</button>
        <button onclick="addAnnotation('underline')">Underline</button>
        <button onclick="hideAnnotationPopup()">Cancel</button>
    </div>

    <div id="copyFeedback">Copied!</div>
    <div id="toggleFeedback">Preference Saved</div>
    <script>
        let isBookNotes = false;
        let originalNotesContent = document.getElementById('notesContent') ? document.getElementById('notesContent').innerHTML : '';
        let annotationsOn = <?php echo $annotations_on; ?>;
        let currentSelection = null;
        let hasDeletedAnnotations = false;

        const annotations = <?php echo json_encode($annotations); ?>;
        const readingPlain = <?php echo json_encode($reading_plain); ?>;
        const todayIWillPlain = <?php echo json_encode($today_i_will_plain); ?>;
        const readingText = <?php echo json_encode($reading_text); ?>;
        const todayIWillText = <?php echo json_encode($today_i_will_text); ?>;

        function applyAnnotations() {
            if (!annotationsOn) return;

            const readingEl = document.getElementById('readingText');
            const todayEl = document.getElementById('todayIWillText');
            let failed = false;

            let readingHtml = readingText;
            let todayHtml = todayIWillText;

            const sortedAnnotations = [...annotations].sort((a, b) => b.start_pos - a.start_pos);

            sortedAnnotations.forEach(annotation => {
                const plainText = annotation.part === 'reading' ? readingPlain : todayIWillPlain;
                let workingHtml = annotation.part === 'reading' ? readingHtml : todayHtml;

                if (plainText && annotation.start_pos >= 0 && annotation.end_pos <= plainText.length && annotation.start_pos < annotation.end_pos) {
                    let plainIndex = 0;
                    let htmlIndex = 0;
                    let htmlStartPos = -1;
                    let htmlEndPos = -1;

                    while (htmlIndex < workingHtml.length && plainIndex < plainText.length) {
                        if (workingHtml[htmlIndex] === '<') {
                            while (htmlIndex < workingHtml.length && workingHtml[htmlIndex] !== '>') {
                                htmlIndex++;
                            }
                            htmlIndex++;
                        } else {
                            if (plainIndex === annotation.start_pos) htmlStartPos = htmlIndex;
                            if (plainIndex === annotation.end_pos - 1) htmlEndPos = htmlIndex + 1;
                            plainIndex++;
                            htmlIndex++;
                        }
                        if (htmlStartPos !== -1 && htmlEndPos !== -1) break;
                    }

                    if (htmlStartPos === -1 || htmlEndPos === -1) {
                        failed = true;
                    } else {
                        const before = workingHtml.substring(0, htmlStartPos);
                        const annotated = workingHtml.substring(htmlStartPos, htmlEndPos);
                        const after = workingHtml.substring(htmlEndPos);
                        workingHtml = `${before}<span class="${annotation.style}" data-annotation-id="${annotation.id}">${annotated}</span>${after}`;

                        if (annotation.part === 'reading') {
                            readingHtml = workingHtml;
                        } else {
                            todayHtml = workingHtml;
                        }
                    }
                } else {
                    failed = true;
                }
            });

            readingEl.innerHTML = readingHtml;
            todayEl.innerHTML = todayHtml;

            if (failed) {
                document.getElementById('manageAnnotationsButton').textContent = 'Man Anno (!)';
            } else {
                document.getElementById('manageAnnotationsButton').textContent = `Man Anno (${annotations.length})`;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            applyAnnotations();

            // Add selectionchange listener
            document.addEventListener('selectionchange', function() {
                if (!currentSelection || !currentSelection.part) return;
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    currentSelection.range = selection.getRangeAt(0);
                }
            });

            const toggleButton = document.getElementById('toggleAnnotations');
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    annotationsOn = !annotationsOn;
                    toggleButton.innerHTML = annotationsOn ? '<s>Anno</s>' : 'Anno';
                    const feedback = document.getElementById('toggleFeedback');
                    feedback.style.display = 'block';
                    setTimeout(() => feedback.style.display = 'none', 1000);

                    fetch('page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: 'action=toggle_annotations&state=' + (annotationsOn ? 1 : 0)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        }
                    })
                    .catch(err => console.error('Error toggling annotations:', err));
                });
            }

            const readingEl = document.getElementById('readingText');
            const todayEl = document.getElementById('todayIWillText');
            [readingEl, todayEl].forEach(el => {
                if (el) {
                    el.addEventListener('mouseup', showAnnotationPopup);
                    el.addEventListener('touchend', showAnnotationPopup);
                }
            });

            const notesButton = document.getElementById('notesButton');
            if (notesButton) {
                notesButton.addEventListener('click', function() {
                    const modal = document.getElementById('notesModal');
                    if (modal) {
                        modal.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                        originalNotesContent = document.getElementById('notesContent').innerHTML;
                        isBookNotes = false;
                        document.getElementById('toggleNotesButton').textContent = 'Book Notes';
                        document.querySelector('#notesModal h2').textContent = 'Notes - Page <?php echo htmlspecialchars($page); ?> - <?php echo htmlspecialchars($page_date); ?> - <?php echo htmlspecialchars($page_title); ?>';
                        fetchPageNotes();
                    }
                });
            }

            const manageButton = document.getElementById('manageAnnotationsButton');
            if (manageButton) {
                manageButton.addEventListener('click', showManageAnnotationsModal);
            }

            const addNoteForm = document.getElementById('addNoteForm');
            if (addNoteForm) {
                addNoteForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(addNoteForm);
                    formData.append('action', 'add_note');
                    formData.set('note', document.getElementById('noteText').innerHTML);

                    fetch('page.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Add note failed: ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            hideAddNoteModal();
                            const notesButton = document.getElementById('notesButton');
                            if (data.note_count !== null) {
                                notesButton.textContent = `Notes (${data.note_count})`;
                            }
                            window.location.reload();
                        } else {
                            console.error('Add note failed:', data.error);
                        }
                    })
                    .catch(err => console.error('Error adding note:', err));
                });
            }
        });

        function showAnnotationPopup(e) {
            if (!annotationsOn || !window.getSelection().toString()) return;
            const selection = window.getSelection();
            const range = selection.getRangeAt(0);
            const part = e.target.closest('#readingText') ? 'reading' : 'today_i_will';
            currentSelection = { part, range };
            const popup = document.getElementById('annotationPopup');
            const rect = range.getBoundingClientRect();
            popup.style.top = `${rect.bottom + window.scrollY}px`;
            popup.style.left = `${rect.left + window.scrollX}px`;
            popup.style.display = 'block';
        }

        function hideAnnotationPopup() {
            document.getElementById('annotationPopup').style.display = 'none';
            currentSelection = null;
        }

        function addAnnotation(style) {
            if (!currentSelection || !currentSelection.range) {
                console.error('No stored range available');
                hideAnnotationPopup();
                return;
            }
            const { part, range } = currentSelection;
            const plainText = part === 'reading' ? readingPlain : todayIWillPlain;
            const container = part === 'reading' ? document.getElementById('readingText') : document.getElementById('todayIWillText');

            let startPos = -1;
            let endPos = -1;
            let plainIndex = 0;
            const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
            let node;

            while ((node = walker.nextNode())) {
                const nodeText = node.nodeValue;
                const nodeLength = nodeText.length;
                const rangeStartNode = range.startContainer;
                const rangeEndNode = range.endContainer;

                if (startPos === -1 && (node === rangeStartNode || (rangeStartNode.nodeType === Node.ELEMENT_NODE && rangeStartNode.contains(node)))) {
                    startPos = plainIndex + (node === rangeStartNode ? range.startOffset : 0);
                }

                if (endPos === -1 && (node === rangeEndNode || (rangeEndNode.nodeType === Node.ELEMENT_NODE && rangeEndNode.contains(node)))) {
                    endPos = plainIndex + (node === rangeEndNode ? range.endOffset : nodeLength);
                }

                if (startPos !== -1 && endPos !== -1) break;

                plainIndex += nodeLength;
            }

            if (startPos !== -1 && endPos === -1) {
                endPos = plainIndex;
            }

            if (startPos === -1 || endPos === -1 || startPos >= endPos || startPos < 0 || endPos > plainText.length) {
                console.error('Invalid selection range:', { startPos, endPos, plainTextLength: plainText.length });
                hideAnnotationPopup();
                return;
            }

            fetch('page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=add_annotation&page=<?php echo urlencode($page); ?>&part=${part}&start_pos=${startPos}&end_pos=${endPos}&style=${style}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    console.error('Annotation failed:', data.error);
                }
            })
            .catch(err => console.error('Error adding annotation:', err));

            hideAnnotationPopup();
        }

        function showManageAnnotationsModal() {
            const modal = document.getElementById('manageAnnotationsModal');
            const content = document.getElementById('manageAnnotationsContent');
            let html = '';
            let failedCount = 0;

            annotations.forEach(annotation => {
                const plainText = annotation.part === 'reading' ? readingPlain : todayIWillPlain;
                const isFailed = !plainText || annotation.start_pos < 0 || annotation.end_pos > plainText.length || annotation.start_pos >= annotation.end_pos;
                if (isFailed) failedCount++;
                const snippet = plainText && !isFailed ? plainText.substring(annotation.start_pos, annotation.end_pos) : 'Misplaced';
                html += `
                    <div class="${isFailed ? 'failed' : ''}">
                        <strong>${annotation.part === 'reading' ? 'Reading' : 'Today I Will'}:</strong> 
                        <span class="${annotation.style}">${snippet}</span>
                        <span class="delete-annotation" onclick="deleteAnnotation(${annotation.id})">🗑️</span>
                    </div>`;
            });

            content.innerHTML = html;
            modal.style.display = 'block';
            document.getElementById('manageAnnotationsButton').textContent = `Man Anno (${failedCount > 0 ? '!' : annotations.length})`;
        }

        function hideManageAnnotationsModal() {
            const modal = document.getElementById('manageAnnotationsModal');
            if (hasDeletedAnnotations) {
                window.location.reload();
                hasDeletedAnnotations = false;
            } else {
                modal.style.display = 'none';
            }
        }

        function deleteAnnotation(id) {
            fetch('page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=delete_annotation&annotation_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const index = annotations.findIndex(a => a.id === id);
                    if (index !== -1) {
                        annotations.splice(index, 1);
                    }
                    hasDeletedAnnotations = true;
                    showManageAnnotationsModal(); // Refresh modal content
                } else {
                    console.error('Deletion failed:', data.error);
                }
            })
            .catch(err => console.error('Error deleting annotation:', err));
        }

        function deleteNote(id) {
            fetch('page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=delete_note&note_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isBookNotes) {
                        toggleNotes(); // Refresh book notes
                    } else {
                        fetchPageNotes(); // Refresh page notes
                    }
                    const notesButton = document.getElementById('notesButton');
                    const currentCount = parseInt(notesButton.textContent.match(/\d+/) || 0);
                    if (currentCount > 0) {
                        notesButton.textContent = `Notes (${currentCount - 1})`;
                    }
                } else {
                    console.error('Note deletion failed:', data.error);
                }
            })
            .catch(err => console.error('Error deleting note:', err));
        }

        function fetchPageNotes() {
            fetch('page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=get_page_notes&page=<?php echo urlencode($page); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notesContent = document.getElementById('notesContent');
                    let html = '';
                    if (data.notes.length > 0) {
                        data.notes.forEach((note, index) => {
                            if (note.meeting_id && note.meeting_date && note.meeting_title) {
                                html += `<b><i>${note.meeting_date} - ${note.meeting_title}</i></b><br>`;
                            }
                            html += `<div>${note.note}</div>`;
                            const created_at = new Date(note.created_at);
                            html += `<div class="note-timestamp">${created_at.getMonth() + 1}/${created_at.getDate()}/${created_at.getFullYear()} ${created_at.getHours() % 12 || 12}:${created_at.getMinutes().toString().padStart(2, '0')} ${created_at.getHours() >= 12 ? 'PM' : 'AM'} 
                                     <span class="delete-note" onclick="deleteNote(${note.id})">🗑️</span></div>`;
                            if (index < data.notes.length - 1) html += '<hr>';
                        });
                    } else {
                        html = '<p>No notes available for this page.</p>';
                    }
                    notesContent.innerHTML = html;
                } else {
                    console.error('Fetch page notes failed:', data.error);
                }
            })
            .catch(err => console.error('Error fetching page notes:', err));
        }

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
                setTimeout(() => feedback.style.display = 'none', 2000);
            } catch (err) {
                alert('Failed to copy content. Please try manually.');
            } finally {
                window.getSelection().removeAllRanges();
                document.body.removeChild(tempDiv);
            }
        }

        function hideNotesModal() {
            const modal = document.getElementById('notesModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                isBookNotes = false; // Reset to page notes
            }
        }

        function hideAddNoteModal() {
            const modal = document.getElementById('addNoteModal');
            if (modal) modal.style.display = 'none';
        }

        function showAddNoteModal() {
            const modal = document.getElementById('addNoteModal');
            if (modal) {
                modal.style.display = 'block';
                const pageSelect = document.getElementById('notePage');
                pageSelect.value = isBookNotes ? '' : '<?php echo htmlspecialchars($page); ?>';
                document.getElementById('noteText').innerHTML = '';
            }
        }

        function applyStyle(style) {
            const editor = document.getElementById('noteText');
            editor.focus();
            if (style === 'highlight') {
                document.execCommand('hiliteColor', false, '#ffff99');
            } else {
                document.execCommand(style, false, null);
            }
        }

        function toggleNotes() {
            const notesContent = document.getElementById('notesContent');
            const toggleButton = document.getElementById('toggleNotesButton');
            const modalTitle = document.querySelector('#notesModal h2');

            if (!isBookNotes) {
                fetch('page.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded', 
                        'X-Requested-With': 'XMLHttpRequest' 
                    },
                    body: 'action=get_book_notes'
                })
                .then(response => {
                    if (!response.ok) throw new Error('Fetch notes failed: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = 'All Notes';
                        let html = '';
                        if (data.notes.length > 0) {
                            let currentReadingId = null;
                            data.notes.forEach((note, index) => {
                                if (note.reading_id !== currentReadingId) {
                                    if (index > 0) html += '<hr>';
                                    if (note.reading_id === null) {
                                        html += '<h2>Book Notes</h2>';
                                    } else {
                                        html += `<h2>Notes - Page ${note.page} - ${note.reading_date} - ${note.reading_title}</h2>`;
                                    }
                                    currentReadingId = note.reading_id;
                                } else if (index > 0) {
                                    html += '<hr>';
                                }
                                if (note.meeting_id && note.meeting_date && note.meeting_title) {
                                    html += `<b><i>${note.meeting_date} - ${note.meeting_title}</i></b><br>`;
                                }
                                html += `<div>${note.note}</div>`;
                                const created_at = new Date(note.created_at);
                                html += `<div class="note-timestamp">${created_at.getMonth() + 1}/${created_at.getDate()}/${created_at.getFullYear()} ${created_at.getHours() % 12 || 12}:${created_at.getMinutes().toString().padStart(2, '0')} ${created_at.getHours() >= 12 ? 'PM' : 'AM'} 
                                         <span class="delete-note" onclick="deleteNote(${note.id})">🗑️</span></div>`;
                            });
                        } else {
                            html = '<p>No notes available.</p>';
                        }
                        notesContent.innerHTML = html;
                        toggleButton.textContent = 'Page Notes';
                        isBookNotes = true;
                    } else {
                        console.error('Fetch notes failed:', data.error);
                    }
                })
                .catch(err => console.error('Error fetching notes:', err));
            } else {
                modalTitle.textContent = 'Notes - Page <?php echo htmlspecialchars($page); ?> - <?php echo htmlspecialchars($page_date); ?> - <?php echo htmlspecialchars($page_title); ?>';
                notesContent.innerHTML = originalNotesContent;
                toggleButton.textContent = 'Book Notes';
                isBookNotes = false;
            }
        }

        <?php $conn->close(); ob_end_flush(); ?>
    </script>
</body>
</html>
