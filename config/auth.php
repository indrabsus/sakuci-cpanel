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

    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    return $user ?: null;
}

/** Admin melihat dan mengelola milik semua orang; siswa hanya miliknya. */
function is_admin(?array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

/** Pengguna role barcode atau username barcode: domain *.barcode.sakuci.id & bisa koding dari 0 */
function is_barcode(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return ($user['role'] ?? '') === 'barcode' || ($user['username'] ?? '') === 'barcode';
}

/** Mengembalikan akhiran domain berdasarkan pengguna/role ('barcode.sakuci.id' vs SITE_DOMAIN) */
function get_domain_suffix($userOrRole = null): string
{
    $isBarcode = false;
    if (is_array($userOrRole)) {
        $isBarcode = is_barcode($userOrRole);
    } elseif (is_string($userOrRole)) {
        $isBarcode = ($userOrRole === 'barcode');
    }

    if ($isBarcode) {
        return 'barcode.sakuci.id';
    }

    return defined('SITE_DOMAIN') && SITE_DOMAIN !== '' ? SITE_DOMAIN : 'ukk.sakuci.id';
}

/** Menghasilkan URL lengkap web project berdasarkan owner/domain */
function get_project_web_url(array $project, $ownerOrRole = null): string
{
    $domainPart = !empty($project['local_path']) ? basename($project['local_path']) : ($project['domain'] ?? '');
    if (empty($domainPart)) {
        return '';
    }
    $roleOrName = $ownerOrRole ?? ($project['owner_role'] ?? ($project['owner'] ?? ''));
    $suffix = get_domain_suffix($roleOrName);
    return 'https://' . $domainPart . '.' . $suffix;
}

/** Menghentikan halaman yang hanya boleh dibuka admin. */
function require_admin($conn): array
{
    $user = require_login($conn);

    if (!is_admin($user)) {
        http_response_code(403);
        exit('Halaman ini hanya untuk admin.');
    }

    return $user;
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
