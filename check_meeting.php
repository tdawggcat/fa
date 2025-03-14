<?php
session_start();
date_default_timezone_set('America/Chicago');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['hasMeeting' => false, 'noMeeting' => false, 'error' => 'Not logged in']);
    exit();
}

$cred_file = '/home/tdawggcat/.mysql_user';
$credentials = trim(file_get_contents($cred_file));
list($username, $password) = explode(':', $credentials, 2);
$conn = new mysqli('localhost', trim($username), trim($password), 'tdawggcat_fa');
if ($conn->connect_error) {
    echo json_encode(['hasMeeting' => false, 'noMeeting' => false, 'error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'] ?? (new DateTime())->format('Y-m-d');

$stmt = $conn->prepare("SELECT title, no_meeting FROM fa_user_meetings WHERE user_id = ? AND meeting_date = ?");
$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$hasMeeting = $row && !is_null($row['title']);
$noMeeting = $row && $row['no_meeting'] == 1;

$stmt->close();
$conn->close();

echo json_encode(['hasMeeting' => $hasMeeting, 'noMeeting' => $noMeeting]);
?>
