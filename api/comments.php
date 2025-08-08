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
            getComments($conn);
        }
        break;
    case 'POST':
        if ($action === 'create') {
            createComment($conn);
        }
        break;
    case 'PUT':
        if ($action === 'update') {
            updateComment($conn);
        }
        break;
    case 'DELETE':
        if ($action === 'delete') {
            deleteComment($conn);
        }
        break;
}

function getComments($conn) {
    $post_id = $_GET['post_id'] ?? null;
    
    if (!$post_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Post ID is required']);
        return;
    }
    
    try {
        $stmt = executeQuery($conn, "CALL sp_get_comments(?)", [$post_id]);
        $comments = fetchAllAssoc($stmt);
        
        // Organize comments in a tree structure
        $commentTree = [];
        $commentMap = [];
        
        foreach ($comments as $comment) {
            $comment['replies'] = [];
            $commentMap[$comment['comment_id']] = $comment;
            
            if ($comment['parent_comment_id'] === null) {
                $commentTree[] = &$commentMap[$comment['comment_id']];
            } else {
                if (isset($commentMap[$comment['parent_comment_id']])) {
                    $commentMap[$comment['parent_comment_id']]['replies'][] = &$commentMap[$comment['comment_id']];
                }
            }
        }
        
        echo json_encode(['success' => true, 'comments' => $commentTree]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch comments: ' . $e->getMessage()]);
    }
}

function createComment($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Post ID and content are required']);
        return;
    }
    
    try {
        $stmt = executeQuery($conn, "CALL sp_add_comment(?, ?, ?, ?)", [
            $input['post_id'],
            getCurrentUserId(),
            $input['parent_comment_id'] ?? null,
            $input['content']
        ]);
        
        $result = fetchAssoc($stmt);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment_id' => $result['comment_id']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add comment: ' . $e->getMessage()]);
    }
}

function updateComment($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['comment_id']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Comment ID and content are required']);
        return;
    }
    
    try {
        // Check if user owns the comment
        $stmt = executeQuery($conn, "SELECT user_id FROM comments WHERE comment_id = ?", [$input['comment_id']]);
        $comment = fetchAssoc($stmt);
        
        if (!$comment || $comment['user_id'] != getCurrentUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }
        
        $updateStmt = executeQuery($conn, "UPDATE comments SET content = ? WHERE comment_id = ?", [$input['content'], $input['comment_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Comment updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update comment: ' . $e->getMessage()]);
    }
}

function deleteComment($conn) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }
    
    $comment_id = $_GET['comment_id'] ?? null;
    
    if (!$comment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Comment ID is required']);
        return;
    }
    
    try {
        // Check if user owns the comment or is admin
        $stmt = executeQuery($conn, "SELECT user_id FROM comments WHERE comment_id = ?", [$comment_id]);
        $comment = fetchAssoc($stmt);
        
        if (!$comment || ($comment['user_id'] != getCurrentUserId() && !isAdmin())) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }
        
        $deleteStmt = executeQuery($conn, "DELETE FROM comments WHERE comment_id = ?", [$comment_id]);
        
        echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete comment: ' . $e->getMessage()]);
    }
}
?>
