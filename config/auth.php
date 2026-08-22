<?php
// Resolves the logged-in user, confirming the id still exists in this database.
// A session can outlive the row it points at (user deleted, database rebuilt,
// or an id carried over from another app), and inserting a dangling user_id
// fails the foreign key with a fatal error instead of a clean login prompt.
function current_user($conn)
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    return $user ?: null;
}

function clear_session()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// For pages under app/. Redirects to the login page when the session is
// missing or stale, and returns the verified user otherwise.
function require_login($conn)
{
    $user = current_user($conn);
    if ($user) {
        $_SESSION['username'] = $user['username'];
        return $user;
    }

    clear_session();
    header("Location: ../index.php?expired=1");
    exit;
}

// Same check for JSON endpoints, which answer with 401 instead of redirecting.
function require_api_login($conn)
{
    $user = current_user($conn);
    if ($user) {
        return $user;
    }

    clear_session();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
