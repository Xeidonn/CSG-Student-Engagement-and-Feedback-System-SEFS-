<?php
// api/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin($conn);
            exit;
        } elseif ($action === 'register') {
            handleRegister($conn);
            exit;
        } elseif ($action === 'logout') {
            handleLogout();
            exit;
        }
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported POST action']);
        exit;

    case 'GET':
        if ($action === 'check') {
            checkAuthStatus();
            exit;
        }
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported GET action']);
        exit;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
}

/**
 * POST /api/auth.php?action=login
 * Body: { email, password, role? }
 */
function handleLogin($conn): void {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    $email    = trim($input['email']);
    $password = (string)$input['password'];
    $requestedRole = isset($input['role']) ? trim((string)$input['role']) : null; // "admin" | "student" | "csg_officer"

    try {
        // Stored proc returns the user row (password check is done in PHP via password_verify)
        $stmt = executeQuery($conn, "CALL sp_authenticate_user(?, ?)", [$email, $password]);
        $user = fetchAssoc($stmt);

        if (!$user || !isset($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        // If the client explicitly chose "admin", enforce it here
        if ($requestedRole === 'admin' && $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'This account is not authorized for admin access']);
            return;
        }

        // Set session
        $_SESSION['user_id']    = (int)$user['user_id'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['name']       = $user['name'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['role']       = $user['role'];

        // Remember-me (optional)
        if (!empty($input['remember'])) {
            $token = bin2hex(random_bytes(32));
            executeQuery($conn, "UPDATE users SET remember_token = ? WHERE user_id = ?", [$token, $user['user_id']]);

            // 3 weeks
            setcookie('remember_token', $token, time() + (3 * 7 * 24 * 60 * 60), '/');
        }

        echo json_encode([
            'success' => true,
            'user' => [
                'id'    => (int)$user['user_id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
    }
}

/**
 * POST /api/auth.php?action=register
 */
function handleRegister($conn): void {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $required = ['student_id', 'name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => ucfirst($field) . ' is required']);
            return;
        }
    }

    if (!str_ends_with($input['email'], '@dlsu.edu.ph')) {
        http_response_code(400);
        echo json_encode(['error' => 'Please use your DLSU email address']);
        return;
    }

    try {
        $password_hash = password_hash((string)$input['password'], PASSWORD_DEFAULT);

        $stmt = executeQuery($conn, "CALL sp_register_user(?, ?, ?, ?)", [
            $input['student_id'],
            $input['name'],
            $input['email'],
            $password_hash
        ]);

        $result = fetchAssoc($stmt);

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => isset($result['user_id']) ? (int)$result['user_id'] : null
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['error' => 'Email or Student ID already exists']);
        } else {
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
}

/**
 * POST /api/auth.php?action=logout
 */
function handleLogout(): void {
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Wipe session data safely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    echo json_encode(['success' => true]);
}

/**
 * GET /api/auth.php?action=check
 */
function checkAuthStatus(): void {
    if (!empty($_SESSION['user_id'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id'    => (int)$_SESSION['user_id'],
                'name'  => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'role'  => $_SESSION['role'],
            ]
        ]);
        return;
    }
    echo json_encode(['authenticated' => false]);
}
