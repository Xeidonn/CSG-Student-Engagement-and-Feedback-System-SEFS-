<?php
require_once 'config/database.php';

if (!isAdmin()) {
    header('Location: index.php'); // or 'login.php'
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Load analytics data
try {
    // Get total counts
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM feedback_posts");
    $total_posts = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM feedback_posts WHERE post_type = 'suggestion'");
    $total_suggestions = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM comments");
    $total_comments = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM surveys WHERE is_active = 1");
    $active_surveys = mysqli_fetch_assoc($result)['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $total_students = mysqli_fetch_assoc($result)['count'];
    
    // Posts by category
    $result = mysqli_query($conn, "SELECT 
        c.name as category_name,
        c.color as category_color,
        COUNT(fp.post_id) as post_count
    FROM categories c
    LEFT JOIN feedback_posts fp ON c.category_id = fp.category_id
    GROUP BY c.category_id, c.name, c.color
    ORDER BY post_count DESC");
    $categories_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Recent activity
    $result = mysqli_query($conn, "SELECT 
        al.action,
        al.created_at,
        u.name as user_name,
        al.table_name,
        al.record_id
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10");
    $recent_activity = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading analytics: " . $e->getMessage();
}

function getActivityText($action) {
    $texts = [
        'USER_REGISTERED' => 'registered',
        'POST_CREATED' => 'created a post',
        'COMMENT_ADDED' => 'added a comment',
        'SURVEY_CREATED' => 'created a survey',
        'POST_STATUS_CHANGED' => 'updated post status',
    ];
    return $texts[$action] ?? 'performed an action';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SEFS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">            
           <div class="navbar-nav ms-auto">
    <div class="dropdown">
        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['name']) ?>
        </a>
        <ul class="dropdown-menu">
            <li>
                <form method="POST" action="index.php" class="d-inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="dropdown-item">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>

    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-chart-bar me-2"></i>Analytics Dashboard</h2>
                <p class="text-muted">Overview of system activity and engagement metrics.</p>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number text-primary"><?= $total_posts ?></div>
                    <div class="stats-label">Total Posts</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number text-success"><?= $total_suggestions ?></div>
                    <div class="stats-label">Suggestions</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number text-info"><?= $total_comments ?></div>
                    <div class="stats-label">Comments</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number text-warning"><?= $active_surveys ?></div>
                    <div class="stats-label"> Surveys</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number text-secondary"><?= $total_students ?></div>
                    <div class="stats-label">Students</div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Data -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Posts by Category</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories_data)): ?>
                        <p class="text-muted">No data available</p>
                        <?php else: ?>
                        <?php foreach ($categories_data as $category): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge me-2" style="background-color: <?= $category['category_color'] ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </span>
                            </div>
                            <span class="fw-bold"><?= $category['post_count'] ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" 
                                 style="width: <?= $total_posts > 0 ? ($category['post_count'] / $total_posts * 100) : 0 ?>%; background-color: <?= $category['category_color'] ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                        <p class="text-muted">No recent activity</p>
                        <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-light">
                                <i class="fas fa-circle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($activity['user_name'] ?: 'System') ?> 
                                    <?= getActivityText($activity['action']) ?>
                                </div>
                                <div class="activity-time"><?= timeAgo($activity['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
