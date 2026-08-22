<?php
include 'config/config.php';

echo "<pre>";
echo "Session Status: " . session_status() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "\$_SESSION contents:\n";
print_r($_SESSION);
echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    echo "<pre>";
    echo "Testing login with: $username / $password\n";

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        echo "User found: " . print_r($user, true) . "\n";
        echo "Verifying password...\n";

        if (password_verify($password, $user['password'])) {
            echo "✅ Password verified!\n";
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            echo "Session set: " . print_r($_SESSION, true) . "\n";
            echo "Redirecting to dashboard...\n";
        } else {
            echo "❌ Password verification failed!\n";
        }
    } else {
        echo "❌ User not found!\n";
    }
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html>
<head><title>Debug Login</title></head>
<body>
    <h1>Debug Login Test</h1>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" value="admin">
        <input type="password" name="password" placeholder="Password" value="password123">
        <button type="submit">Test Login</button>
    </form>
</body>
</html>
