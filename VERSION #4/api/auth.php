<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin($conn);
        } elseif ($action === 'register') {
            handleRegister($conn);
        } elseif ($action === 'logout') {
            handleLogout();
        }
        break;
    case 'GET':
        if ($action === 'check') {
            checkAuthStatus();
        }
        break;
}

function handleLogin($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }
    
    try {
        $stmt = executeQuery($conn, "CALL sp_authenticate_user(?, ?)", [$input['email'], $input['password']]);
        $user = fetchAssoc($stmt);
        
        if ($user && password_verify($input['password'], $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Handle remember me
            if (isset($input['remember']) && $input['remember']) {
                $token = bin2hex(random_bytes(32));
                $updateStmt = executeQuery($conn, "UPDATE users SET remember_token = ? WHERE user_id = ?", [$token, $user['user_id']]);
                
                setcookie('remember_token', $token, time() + (3 * 7 * 24 * 60 * 60), '/'); // 3 weeks
            }
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['user_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
    }
}

function handleRegister($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['student_id', 'name', 'email', 'password'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => ucfirst($field) . ' is required']);
            return;
        }
    }
    
    // Validate email domain
    if (!str_ends_with($input['email'], '@dlsu.edu.ph')) {
        http_response_code(400);
        echo json_encode(['error' => 'Please use your DLSU email address']);
        return;
    }
    
    try {
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
        
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
            'user_id' => $result['user_id']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['error' => 'Email or Student ID already exists']);
        } else {
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
}

function handleLogout() {
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_destroy();
    echo json_encode(['success' => true]);
}

function checkAuthStatus() {
    if (isLoggedIn()) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'];
$password = $data['password'];
$role = $data['role']; // 'student' or 'admin'

$conn = new mysqli("localhost", "root", "", "ITISDEV_MP");

// Assume roles are stored in a 'role' column in your users table
$stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email=? AND password=?");
$stmt->bind_param("ss", $email, $password);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $user['role'] === $role) {
    echo json_encode(["success" => true, "role" => $user['role'], "name" => $user['name']]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid credentials or role."]);
}

?>
