<?php
session_start();
date_default_timezone_set('America/Chicago');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Not logged in";
    exit();
}

$cred_file = '/home/tdawggcat/.mysql_user';
$credentials = trim(file_get_contents($cred_file));
list($username, $password) = explode(':', $credentials, 2);
$conn = new mysqli('localhost', trim($username), trim($password), 'tdawggcat_fa');
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed";
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'] ?? (new DateTime())->format('Y-m-d');
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$no_meeting = isset($_POST['no_meeting']) && $_POST['no_meeting'] == 1 ? 1 : 0;

$stmt = $conn->prepare("INSERT INTO fa_user_meetings (user_id, meeting_date, title, no_meeting) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $user_id, $date, $title, $no_meeting);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if (!$success) {
    http_response_code(500);
    echo "Failed to add meeting";
} else {
    echo "Meeting added";
}
?>
