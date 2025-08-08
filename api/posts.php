<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getPosts($conn);
        } elseif ($action === 'categories') {
            getCategories($conn);
        }
        break;
    case 'POST':
        if ($action === 'create') {
            createPost($conn);
        } elseif ($action === 'vote') {
            votePost($conn);
        }
        break;
    case 'PUT':
        if ($action === 'update') {
            updatePost($conn);
        }
        break;
    case 'DELETE':
        if ($action === 'delete') {
            deletePost($conn);
        }
        break;
}

function getPosts($conn) {
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    $category_id = $_GET['category_id'] ?? null;
    $search = $_GET['search'] ?? null;
    
    try {
        $stmt = executeQuery($conn, "CALL sp_get_feedback_posts(?, ?, ?, ?)", [$limit, $offset, $category_id, $search]);
        $posts = fetchAllAssoc($stmt);
        
        echo json_encode(['success' => true, 'posts' => $posts]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch posts: ' . $e->getMessage()]);
    }
}

function getCategories($conn) {
    try {
        $result = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
        $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'categories' => $categories]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch categories: ' . $e->getMessage()]);
    }
}

function createPost($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'content', 'post_type'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => ucfirst($field) . ' is required']);
            return;
        }
    }
    
    try {
        $stmt = executeQuery($conn, "CALL sp_create_feedback_post(?, ?, ?, ?, ?, ?)", [
            getCurrentUserId(),
            $input['category_id'] ?? null,
            $input['title'],
            $input['content'],
            $input['post_type'],
            $input['is_anonymous'] ?? false
        ]);
        
        $result = fetchAssoc($stmt);
        
        echo json_encode([
            'success' => true,
            'message' => 'Post created successfully',
            'post_id' => $result['post_id']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create post: ' . $e->getMessage()]);
    }
}

function votePost($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id']) || !isset($input['vote_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Post ID and vote type are required']);
        return;
    }
    
    try {
        $stmt = executeQuery($conn, "CALL sp_vote_post(?, ?, ?)", [
            getCurrentUserId(),
            $input['post_id'],
            $input['vote_type']
        ]);
        
        $result = fetchAssoc($stmt);
        
        echo json_encode([
            'success' => true,
            'upvotes' => $result['upvotes'],
            'downvotes' => $result['downvotes']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to vote: ' . $e->getMessage()]);
    }
}

function updatePost($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Post ID is required']);
        return;
    }
    
    try {
        // Check if user owns the post
        $stmt = executeQuery($conn, "SELECT user_id FROM feedback_posts WHERE post_id = ?", [$input['post_id']]);
        $post = fetchAssoc($stmt);
        
        if (!$post || $post['user_id'] != getCurrentUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }
        
        $updateStmt = executeQuery($conn, "UPDATE feedback_posts SET title = ?, content = ?, category_id = ? WHERE post_id = ?", [
            $input['title'],
            $input['content'],
            $input['category_id'],
            $input['post_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Post updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update post: ' . $e->getMessage()]);
    }
}

function deletePost($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $post_id = $_GET['post_id'] ?? null;
    
    if (!$post_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Post ID is required']);
        return;
    }
    
    try {
        // Check if user owns the post or is admin
        $stmt = executeQuery($conn, "SELECT user_id FROM feedback_posts WHERE post_id = ?", [$post_id]);
        $post = fetchAssoc($stmt);
        
        if (!$post || ($post['user_id'] != getCurrentUserId() && !isAdmin())) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }
        
        $deleteStmt = executeQuery($conn, "DELETE FROM feedback_posts WHERE post_id = ?", [$post_id]);
        
        echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete post: ' . $e->getMessage()]);
    }
}
?>
