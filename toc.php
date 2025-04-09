<?php
session_start(); // Add session start

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to detect mobile device
function isMobile() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $mobile_keywords = [
        'mobile', 'android', 'iphone', 'ipad', 'ipod', 'blackberry', 
        'windows phone', 'opera mini', 'silk', 'kindle', 'webos'
    ];
    foreach ($mobile_keywords as $keyword) {
        if (strpos($user_agent, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// Handle "Toggle TOC Activity" AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_toc_activity' && isset($_SESSION['user_id'])) {
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
            $stmt = $conn->prepare("UPDATE fa_users SET toc_activity = ? WHERE id = ?");
            $stmt->bind_param("ii", $state, $_user_id);
            $success = $stmt->execute();
            $stmt->close();
            $conn->close();
            $response = ['success' => $success];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Stop execution after AJAX response
}

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

// Fetch user toc_activity preference
$toc_activity = 1; // Default to on
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt1 = $conn->prepare("SELECT toc_activity FROM fa_users WHERE id = ?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $result = $stmt1->get_result();
    if ($row = $result->fetch_assoc()) {
        $toc_activity = (int)$row['toc_activity'];
    }
    $stmt1->close();
}

// Adjust query based on login status and toc_activity
if (isset($_SESSION['user_id']) && $toc_activity) {
    $user_id = $_SESSION['user_id'];
    $stmt2 = $conn->prepare("
        SELECT r.page, r.date, r.title,
               COUNT(DISTINCT ur.id) AS read_count,
               COUNT(DISTINCT n.id) AS note_count,
               COUNT(DISTINCT a.id) AS annotation_count
        FROM fa_readings r
        LEFT JOIN fa_user_readings ur ON r.page = ur.page AND ur.user_id = ?
        LEFT JOIN fa_notes n ON r.page = n.reading_id AND n.user_id = ?
        LEFT JOIN fa_annotations a ON r.page = a.page AND a.user_id = ?
        WHERE r.sort_key >= -4
        GROUP BY r.page, r.date, r.title
        ORDER BY r.sort_key
    ");
    $stmt2->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $stmt2->close();
} else {
    $query = "SELECT page, date, title FROM fa_readings WHERE sort_key >= -4 ORDER BY sort_key";
    $result = $conn->query($query);
}
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
            margin-bottom: 10px;
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
        .no-wrap {
            white-space: nowrap;
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
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
        .navigation a {
            margin: 0 10px;
            text-decoration: none;
            color: #0066cc;
        }
        .navigation a:hover {
            text-decoration: underline;
        }
        .notes-button {
            margin-top: 10px;
            padding: 5px 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            background-color: #fff;
            color: #000;
            border-radius: 3px;
            min-width: 60px;
            text-align: center;
        }
        .notes-button:hover {
            background-color: #f0f0f0;
        }
        #toggleFeedback {
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        .tooltip:hover::after {
            content: '# of times Read\a# of Notes\a# of Annotations';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            white-space: pre;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="navigation">
        <div class="left">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user_readings.php">History</a> | <a href="page.php">Today</a>
            <?php else: ?>
                <a href="page.php">Today</a> | <a href="login.php?redirect=toc.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
    <h1>Table of Contents</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <button id="toggleActivity" class="notes-button"><?php echo $toc_activity ? '<s>Act</s>' : 'Act'; ?></button>
        <br><br>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Page</th>
                <th class="no-wrap">Day</th>
                <?php if (isset($_SESSION['user_id']) && $toc_activity): ?>
                    <th class="no-wrap"><span class="tooltip">Act</span></th>
                <?php endif; ?>
                <th>Title</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['page']); ?></a></td>
                    <td class="no-wrap"><a href="page.php?page=<?php echo urlencode($row['page']); ?>">
                        <?php 
                        if (isMobile()) {
                            $date = DateTime::createFromFormat('F j', $row['date']);
                            echo $date ? htmlspecialchars($date->format('M j')) : htmlspecialchars($row['date']);
                        } else {
                            echo htmlspecialchars($row['date']);
                        }
                        ?>
                    </a></td>
                    <?php if (isset($_SESSION['user_id']) && $toc_activity): ?>
                        <td class="no-wrap"><a href="page.php?page=<?php echo urlencode($row['page']); ?>">
                            <?php echo htmlspecialchars($row['read_count'] . ' ' . $row['note_count'] . ' ' . $row['annotation_count']); ?>
                        </a></td>
                    <?php endif; ?>
                    <td><a href="page.php?page=<?php echo urlencode($row['page']); ?>"><?php echo htmlspecialchars($row['title']); ?></a></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No entries found.</p>
    <?php endif; ?>

    <div id="toggleFeedback">Preference Saved</div>

    <?php
    $conn->close();
    ?>

    <script>
        let tocActivityOn = <?php echo $toc_activity; ?>;
        const justToggled = <?php echo isset($_GET['toggled']) && $_GET['toggled'] == 1 ? 1 : 0; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Show popup if just toggled
            if (justToggled) {
                const feedback = document.getElementById('toggleFeedback');
                feedback.style.display = 'block';
                setTimeout(() => feedback.style.display = 'none', 2000); // Your 2000ms change
            }

            const toggleButton = document.getElementById('toggleActivity');
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    tocActivityOn = !tocActivityOn;
                    toggleButton.innerHTML = tocActivityOn ? '<s>Act</s>' : 'Act';

                    fetch('toc.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: 'action=toggle_toc_activity&state=' + (tocActivityOn ? 1 : 0)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'toc.php?toggled=1';
                        } else {
                            console.error('Toggle failed:', data.error);
                        }
                    })
                    .catch(err => console.error('Error toggling TOC activity:', err));
                });
            }
        });
    </script>
</body>
</html>
