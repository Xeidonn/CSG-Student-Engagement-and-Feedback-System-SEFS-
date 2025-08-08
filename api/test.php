<?php
// Simple test API to check if basic functionality works

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    // Test database connection
    $conn = mysqli_connect("localhost", "root", "sqlroot", "sefs_db");
    
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    // Test basic query
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM categories");
    $categoryCount = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM feedback_posts");
    $postCount = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
    $userCount = mysqli_fetch_assoc($result)['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working correctly',
        'data' => [
            'categories' => $categoryCount,
            'posts' => $postCount,
            'users' => $userCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
