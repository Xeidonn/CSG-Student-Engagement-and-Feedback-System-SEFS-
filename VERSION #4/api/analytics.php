<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'dashboard':
        getDashboardAnalytics($db);
        break;
    case 'trends':
        getTrends($db);
        break;
}

function getDashboardAnalytics($db) {
    try {
        $stmt = $db->prepare("CALL sp_get_dashboard_analytics()");
        $stmt->execute();
        
        // Get total counts
        $totals = $stmt->fetch();
        $stmt->nextRowset();
        
        // Get posts by category
        $categories = $stmt->fetchAll();
        $stmt->nextRowset();
        
        // Get recent activity
        $activity = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'totals' => $totals,
                'categories' => $categories,
                'recent_activity' => $activity
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch analytics: ' . $e->getMessage()]);
    }
}

function getTrends($db) {
    try {
        // Posts over time (last 30 days)
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM feedback_posts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $postsOverTime = $stmt->fetchAll();
        
        // Top categories by engagement
        $stmt = $db->query("
            SELECT 
                c.name,
                COUNT(fp.post_id) as post_count,
                SUM(fp.upvotes + fp.downvotes) as total_votes,
                SUM(fp.comment_count) as total_comments
            FROM categories c
            LEFT JOIN feedback_posts fp ON c.category_id = fp.category_id
            GROUP BY c.category_id, c.name
            ORDER BY total_votes DESC
            LIMIT 10
        ");
        $topCategories = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'posts_over_time' => $postsOverTime,
                'top_categories' => $topCategories
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch trends: ' . $e->getMessage()]);
    }
}
?>
