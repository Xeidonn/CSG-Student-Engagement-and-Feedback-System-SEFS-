<?php
// Individual Post View - Pure PHP Version
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();

$post_id = intval($_GET['id'] ?? 0);

// --- POST HANDLERS ---

// Edit post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_post') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to edit posts';
    } else {
        $new_title = trim($_POST['edit_title'] ?? '');
        $new_content = trim($_POST['edit_content'] ?? '');
        if ($new_title === '' || $new_content === '') {
            $_SESSION['error'] = 'Title and content are required';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM feedback_posts WHERE post_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $post_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row && getCurrentUserId() == $row['user_id']) {
                $update = mysqli_prepare($conn, "UPDATE feedback_posts SET title = ?, content = ?, updated_at = NOW() WHERE post_id = ?");
                mysqli_stmt_bind_param($update, "ssi", $new_title, $new_content, $post_id);
                mysqli_stmt_execute($update);
                $_SESSION['success'] = 'Post updated successfully!';
                header("Location: post.php?id=$post_id");
                exit;
            } else {
                $_SESSION['error'] = 'You are not allowed to edit this post';
            }
        }
    }
}

// Edit comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_comment') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to edit comments';
    } else {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $new_content = trim($_POST['edit_content'] ?? '');
        if ($new_content === '') {
            $_SESSION['error'] = 'Comment content is required';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM comments WHERE comment_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $comment_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row && getCurrentUserId() == $row['user_id']) {
                $update = mysqli_prepare($conn, "UPDATE comments SET content = ?, is_edited = 1, updated_at = NOW() WHERE comment_id = ?");
                mysqli_stmt_bind_param($update, "si", $new_content, $comment_id);
                mysqli_stmt_execute($update);
                $_SESSION['success'] = 'Comment updated successfully!';
            } else {
                $_SESSION['error'] = 'You are not allowed to edit this comment';
            }
        }
    }
    header("Location: post.php?id=$post_id");
    exit;
}

// Delete post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to delete posts';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM feedback_posts WHERE post_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row && getCurrentUserId() == $row['user_id']) {
            $delComments = mysqli_prepare($conn, "DELETE FROM comments WHERE post_id = ?");
            mysqli_stmt_bind_param($delComments, "i", $post_id);
            mysqli_stmt_execute($delComments);
            $delPost = mysqli_prepare($conn, "DELETE FROM feedback_posts WHERE post_id = ?");
            mysqli_stmt_bind_param($delPost, "i", $post_id);
            mysqli_stmt_execute($delPost);
            $_SESSION['success'] = 'Post deleted successfully!';
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['error'] = 'You are not allowed to delete this post';
        }
    }
}

// Delete comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to delete comments';
    } else {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM comments WHERE comment_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $comment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row && getCurrentUserId() == $row['user_id']) {
            $delReplies = mysqli_prepare($conn, "DELETE FROM comments WHERE parent_comment_id = ?");
            mysqli_stmt_bind_param($delReplies, "i", $comment_id);
            mysqli_stmt_execute($delReplies);
            $delComment = mysqli_prepare($conn, "DELETE FROM comments WHERE comment_id = ?");
            mysqli_stmt_bind_param($delComment, "i", $comment_id);
            mysqli_stmt_execute($delComment);
            $_SESSION['success'] = 'Comment deleted successfully!';
        } else {
            $_SESSION['error'] = 'You are not allowed to delete this comment';
        }
    }
    header("Location: post.php?id=$post_id");
    exit;
}

if (!$post_id) {
    header('Location: index.php');
    exit;
}

// Add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_comment') {
        if (!isLoggedIn()) {
            $_SESSION['error'] = 'Please login to comment';
        } else {
            $content = $_POST['content'] ?? '';
            $parent_comment_id = $_POST['parent_comment_id'] ?? null;
            if (!empty($content)) {
                try {
                    $stmt = mysqli_prepare($conn, "INSERT INTO comments (post_id, user_id, parent_comment_id, content) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiis", $post_id, getCurrentUserId(), $parent_comment_id, $content);
                    if (mysqli_stmt_execute($stmt)) {
                        $comment_id = mysqli_insert_id($conn);
                        $logStmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'COMMENT_ADDED', 'comments', ?)");
                        mysqli_stmt_bind_param($logStmt, "ii", getCurrentUserId(), $comment_id);
                        mysqli_stmt_execute($logStmt);
                        $_SESSION['success'] = 'Comment added successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to add comment';
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Failed to add comment: ' . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = 'Comment content is required';
            }
        }
        header("Location: post.php?id=$post_id");
        exit;
    }
}

// --- LOAD POST DATA ---

try {
    $stmt = mysqli_prepare($conn, "SELECT 
        fp.post_id,
        fp.title,
        fp.content,
        fp.post_type,
        fp.status,
        fp.upvotes,
        fp.downvotes,
        fp.comment_count,
        fp.is_anonymous,
        fp.created_at,
        fp.updated_at,
        fp.user_id,
        CASE 
            WHEN fp.is_anonymous = 1 THEN 'Anonymous'
            ELSE u.name
        END as author_name,
        c.name as category_name,
        c.color as category_color
    FROM feedback_posts fp
    LEFT JOIN users u ON fp.user_id = u.user_id
    LEFT JOIN categories c ON fp.category_id = c.category_id
    WHERE fp.post_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($result);

    if (!$post) {
        header('Location: index.php');
        exit;
    }

    // Load comments
    $commentStmt = mysqli_prepare($conn, "SELECT 
        c.comment_id,
        c.post_id,
        c.parent_comment_id,
        c.content,
        c.is_edited,
        c.created_at,
        c.updated_at,
        u.name as author_name,
        u.user_id
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC");
    mysqli_stmt_bind_param($commentStmt, "i", $post_id);
    mysqli_stmt_execute($commentStmt);
    $commentResult = mysqli_stmt_get_result($commentStmt);
    $comments = mysqli_fetch_all($commentResult, MYSQLI_ASSOC);

    // Organize comments in tree structure
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
} catch (Exception $e) {
    $error_message = "Error loading post: " . $e->getMessage();
}

// --- HELPERS ---

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// --- COMMENT RENDER FUNCTION (WITH EDIT FEATURE) ---

function renderComment($comment, $post_id, $level = 0) {
    $indent = $level > 0 ? 'style="margin-left: ' . ($level * 30) . 'px;"' : '';
    $isOwner = isLoggedIn() && getCurrentUserId() == $comment['user_id'];
    $editMode = (isset($_POST['action'], $_POST['comment_id']) && $_POST['action'] === 'show_edit_comment_form' && $_POST['comment_id'] == $comment['comment_id']);
    echo "<div class='comment mb-3' $indent>";
    echo "<div class='comment-header d-flex justify-content-between align-items-center mb-2'>";
    echo "<div>";
    echo "<span class='comment-author fw-bold'>" . htmlspecialchars($comment['author_name']) . "</span>";
    echo "<span class='comment-date text-muted ms-2 post-time' data-created-at='" . htmlspecialchars($comment['created_at']) . "'>" . timeAgo($comment['created_at']) . "</span>";
    if ($comment['is_edited']) {
        echo "<span class='badge bg-secondary ms-2'>Edited</span>";
    }
    echo "</div>";
    echo "</div>";
    // If editing, show the form instead of content
    if ($isOwner && $editMode) {
        echo "<form method='POST' class='mb-2'>";
        echo "<input type='hidden' name='action' value='edit_comment'>";
        echo "<input type='hidden' name='comment_id' value='{$comment['comment_id']}'>";
        echo "<textarea class='form-control mb-2' name='edit_content' rows='2' required>" . htmlspecialchars($comment['content']) . "</textarea>";
        echo "<button type='submit' class='btn btn-primary btn-sm'>Save</button> ";
        echo "<a href='post.php?id={$post_id}' class='btn btn-secondary btn-sm ms-1'>Cancel</a>";
        echo "</form>";
    } else {
        echo "<div class='comment-content'>" . nl2br(htmlspecialchars($comment['content'])) . "</div>";
        if (isLoggedIn()) {
            echo "<div class='comment-actions mt-2'>";
            echo "<button class='btn btn-sm btn-outline-primary' onclick='showReplyForm({$comment['comment_id']})'>Reply</button>";
            if ($isOwner) {
                // Edit button (submits to same page with a special action)
                echo "<form method='POST' class='d-inline ms-2'>";
                echo "<input type='hidden' name='action' value='show_edit_comment_form'>";
                echo "<input type='hidden' name='comment_id' value='{$comment['comment_id']}'>";
                echo "<button type='submit' class='btn btn-sm btn-outline-warning'>Edit</button>";
                echo "</form>";
                // Delete button
                echo "<form method='POST' class='d-inline ms-2' onsubmit=\"return confirm('Are you sure you want to delete this comment?');\">";
                echo "<input type='hidden' name='action' value='delete_comment'>";
                echo "<input type='hidden' name='comment_id' value='{$comment['comment_id']}'>";
                echo "<button type='submit' class='btn btn-sm btn-outline-danger'>Delete</button>";
                echo "</form>";
            }
            echo "</div>";
            // Reply form (hidden by default)
            echo "<div id='reply-form-{$comment['comment_id']}' class='reply-form mt-3' style='display: none;'>";
            echo "<form method='POST'>";
            echo "<input type='hidden' name='action' value='add_comment'>";
            echo "<input type='hidden' name='parent_comment_id' value='{$comment['comment_id']}'>";
            echo "<div class='mb-3'>";
            echo "<textarea class='form-control' name='content' rows='3' placeholder='Write a reply...' required></textarea>";
            echo "</div>";
            echo "<button type='submit' class='btn btn-primary btn-sm'>Post Reply</button>";
            echo "<button type='button' class='btn btn-secondary btn-sm ms-2' onclick='hideReplyForm({$comment['comment_id']})'>Cancel</button>";
            echo "</form>";
            echo "</div>";
        }
    }
    echo "</div>";
    foreach ($comment['replies'] as $reply) {
        renderComment($reply, $post_id, $level + 1);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - SEFS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-comments me-2"></i>SEFS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Posts
                </a>
                <?php if (isLoggedIn()): ?>
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
                <?php else: ?>
                <a class="nav-link" href="index.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Post Content -->
                <div class="post-card">
                    <div class="post-header">
                        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
                        <?php if (isLoggedIn() && getCurrentUserId() == $post['user_id']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                <input type="hidden" name="action" value="delete_post">
                                <button type="submit" class="btn btn-sm btn-danger ms-2"><i class="fas fa-trash"></i> Delete Post</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#editPostModal">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        <?php endif; ?>
                    <div class="post-meta">
                        <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($post['author_name']) ?></span>
                        <span class="post-time" data-created-at="<?= htmlspecialchars($post['created_at']) ?>">
                            <i class="fas fa-clock me-1"></i><?= timeAgo($post['created_at']) ?>
                        </span>
                        <?php if ($post['updated_at'] && $post['updated_at'] != $post['created_at']): ?>
                            <span class="badge bg-secondary ms-2">Edited</span>
                        <?php endif; ?>
                        <?php if ($post['category_name']): ?>
                        <span class="post-category" style="background-color: <?= $post['category_color'] ?>">
                            <?= htmlspecialchars($post['category_name']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="status-badge status-<?= $post['status'] ?>">
                            <?= ucfirst($post['status']) ?>
                        </span>
                    </div>
                    </div>
                    <div class="post-content">
                        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                    </div>
                    <div class="post-actions">
                        <div class="vote-buttons">
                            <?php if (isLoggedIn()): ?>
                            <form method="POST" action="index.php" class="d-inline">
                                <input type="hidden" name="action" value="vote">
                                <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                <input type="hidden" name="vote_type" value="upvote">
                                <button type="submit" class="vote-btn upvote">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span><?= $post['upvotes'] ?></span>
                                </button>
                            </form>
                            <form method="POST" action="index.php" class="d-inline">
                                <input type="hidden" name="action" value="vote">
                                <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                <input type="hidden" name="vote_type" value="downvote">
                                <button type="submit" class="vote-btn downvote">
                                    <i class="fas fa-thumbs-down"></i>
                                    <span><?= $post['downvotes'] ?></span>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="vote-btn upvote disabled">
                                <i class="fas fa-thumbs-up"></i>
                                <span><?= $post['upvotes'] ?></span>
                            </span>
                            <span class="vote-btn downvote disabled">
                                <i class="fas fa-thumbs-down"></i>
                                <span><?= $post['downvotes'] ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="comment-btn">
                            <i class="fas fa-comment"></i>
                            <span><?= $post['comment_count'] ?> Comments</span>
                        </span>
                    </div>
                </div>

                <!-- Edit Post Modal -->
                <div class="modal fade" id="editPostModal" tabindex="-1" aria-labelledby="editPostModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="POST">
                        <div class="modal-header">
                          <h5 class="modal-title" id="editPostModalLabel">Edit Post</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_post">
                            <div class="mb-3">
                                <label for="edit_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="edit_title" name="edit_title" value="<?= htmlspecialchars($post['title']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_content" class="form-label">Content</label>
                                <textarea class="form-control" id="edit_content" name="edit_content" rows="5" required><?= htmlspecialchars($post['content']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Comments Section -->
                <div class="mt-4">
                    <h4>Comments</h4>
                    <?php if (isLoggedIn()): ?>
                    <!-- Add Comment Form -->
                    <div class="mb-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-3">
                                <textarea class="form-control" name="content" rows="3" placeholder="Add a comment..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <!-- Comments List -->
                    <?php if (empty($commentTree)): ?>
                    <p class="text-muted">No comments yet. 
                        <?php if (isLoggedIn()): ?>
                            Be the first to comment!
                        <?php else: ?>
                            <a href="index.php">Login</a> to add a comment.
                        <?php endif; ?>
                    </p>
                    <?php else: ?>
                    <?php foreach ($commentTree as $comment): ?>
                        <?php renderComment($comment, $post_id); ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'block';
        }
        function hideReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'none';
        }
        // Real-time time-ago update
        function getTimeAgo(dateString) {
            const now = new Date();
            const then = new Date(dateString.replace(' ', 'T'));
            const diff = Math.floor((now - then) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff/60) + 'm ago';
            if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
            if (diff < 2592000) return Math.floor(diff/86400) + 'd ago';
            return then.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function updatePostTimes() {
            document.querySelectorAll('.post-time').forEach(function(el) {
                const createdAt = el.getAttribute('data-created-at');
                if (createdAt) {
                    let icon = '';
                    if (el.classList.contains('comment-date')) {
                        icon = '';
                    } else {
                        icon = '<i class="fas fa-clock me-1"></i>';
                    }
                    el.innerHTML = icon + getTimeAgo(createdAt);
                }
            });
        }
        setInterval(updatePostTimes, 30000);
        document.addEventListener('DOMContentLoaded', updatePostTimes);
    </script>
</body>
</html>
