<?php
session_start(); // Start session to track logged-in user

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy(); // End the session
    header("Location: page.php"); // Redirect to page.php with no page defined
    exit();
}

// Check if user is logged in and an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    header("Location: login.php?redirect=manage_users.php"); // Explicit redirect with parameter
    exit();
}

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $new_username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $password = trim($_POST['password']);
        $active = isset($_POST['active']) ? 1 : 0;
        $admin = isset($_POST['admin']) ? 1 : 0;

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO fa_users (username, full_name, password_hash, active, admin) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $new_username, $full_name, $password_hash, $active, $admin);
        
        if ($stmt->execute()) {
            echo "<p>User added successfully.</p>";
        } else {
            echo "<p>Error adding user: " . $conn->error . "</p>";
        }
        $stmt->close();
    } elseif (isset($_POST['edit_user'])) {
        // Edit existing user
        $id = $_POST['id'];
        $new_username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $password = trim($_POST['password']);
        $active = isset($_POST['active']) ? 1 : 0;
        $admin = isset($_POST['admin']) ? 1 : 0;

        if (!empty($password)) {
            // Update with new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE fa_users SET username = ?, full_name = ?, password_hash = ?, active = ?, admin = ? WHERE id = ?");
            $stmt->bind_param("sssiii", $new_username, $full_name, $password_hash, $active, $admin, $id);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE fa_users SET username = ?, full_name = ?, active = ?, admin = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $new_username, $full_name, $active, $admin, $id);
        }

        if ($stmt->execute()) {
            echo "<p>User updated successfully.</p>";
        } else {
            echo "<p>Error updating user: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}

// Fetch all users
$users_query = "SELECT id, username, full_name, active, admin FROM fa_users ORDER BY username";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Families Anonymous Readings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: inline-block;
            width: 100px;
            margin: 5px 0;
        }
        input[type="text"], input[type="password"] {
            width: 200px;
            padding: 5px;
        }
        input[type="checkbox"] {
            margin-left: 10px;
        }
        button {
            padding: 5px 10px;
            margin-top: 10px;
        }
        .logout-form {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Logout Button -->
    <form method="post" class="logout-form">
        <button type="submit" name="logout">Logout</button>
    </form>

    <h1>Manage Users</h1>

    <!-- Add User Form -->
    <h2>Add New User</h2>
    <form method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
        </div>
        <div>
            <label for="full_name">Full Name:</label>
            <input type="text" name="full_name" id="full_name" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
        </div>
        <div>
            <label for="active">Active:</label>
            <input type="checkbox" name="active" id="active" checked>
        </div>
        <div>
            <label for="admin">Admin:</label>
            <input type="checkbox" name="admin" id="admin">
        </div>
        <button type="submit" name="add_user">Add User</button>
    </form>

    <!-- Existing Users Table -->
    <h2>Existing Users</h2>
    <?php if ($users_result->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Active</th>
                <th>Admin</th>
                <th>Edit</th>
            </tr>
            <?php while ($row = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo $row['active'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo $row['admin'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <div>
                                <label for="username_<?php echo $row['id']; ?>">Username:</label>
                                <input type="text" name="username" id="username_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['username']); ?>" required>
                            </div>
                            <div>
                                <label for="full_name_<?php echo $row['id']; ?>">Full Name:</label>
                                <input type="text" name="full_name" id="full_name_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['full_name']); ?>" required>
                            </div>
                            <div>
                                <label for="password_<?php echo $row['id']; ?>">Password:</label>
                                <input type="password" name="password" id="password_<?php echo $row['id']; ?>" placeholder="Leave blank to keep unchanged">
                            </div>
                            <div>
                                <label for="active_<?php echo $row['id']; ?>">Active:</label>
                                <input type="checkbox" name="active" id="active_<?php echo $row['id']; ?>" <?php echo $row['active'] ? 'checked' : ''; ?>>
                            </div>
                            <div>
                                <label for="admin_<?php echo $row['id']; ?>">Admin:</label>
                                <input type="checkbox" name="admin" id="admin_<?php echo $row['id']; ?>" <?php echo $row['admin'] ? 'checked' : ''; ?>>
                            </div>
                            <button type="submit" name="edit_user">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No users found.</p>
    <?php endif; ?>

    <?php
    $conn->close();
    ?>
</body>
</html>
