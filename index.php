<?php
// SEFS Main Page - Pure PHP Version
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
      switch ($_POST['action']) {
          case 'login':
              handleLogin($conn);
              break;
          case 'register':
              handleRegister($conn);
              break;
          case 'logout':
              handleLogout();
              break;
          case 'create_post':
              handleCreatePost($conn);
              break;
          case 'vote':
              handleVote($conn);
              break;
          case 'add_comment':
              handleAddComment($conn);
              break;
      }
  }
}

// Get current section
$section = $_GET['section'] ?? 'home';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$posts_per_page = 10;

// Load data based on section
$posts = [];
$categories = [];
$surveys = [];

try {
  // Always load categories
  $result = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
  $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
  
  if ($section === 'home' || $section === 'suggestions') {
      $posts = loadPosts($conn, $section, $search, $category_filter, $page, $posts_per_page);
  } elseif ($section === 'surveys') {
      $surveys = loadSurveys($conn);
  }
} catch (Exception $e) {
  $error_message = "Error loading data: " . $e->getMessage();
}

// Functions
function handleLogin($conn) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email and password are required';
        return;
    }

    try {
        $stmt = mysqli_prepare($conn, "SELECT user_id, student_id, name, email, password_hash, role 
                                       FROM users 
                                       WHERE email = ? AND is_active = 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $updateStmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($updateStmt, "si", $token, $user['user_id']);
                mysqli_stmt_execute($updateStmt);
                setcookie('remember_token', $token, time() + (3 * 7 * 24 * 60 * 60), '/');
            }

            $_SESSION['success'] = 'Login successful!';

            //  Redirect based on role
            if (strtolower($user['role']) === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
            exit;

        } else {
            $_SESSION['error'] = 'Invalid credentials';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Login failed: ' . $e->getMessage();
    }

    header('Location: index.php');
    exit;
}


function handleRegister($conn) {
  $student_id = $_POST['student_id'] ?? '';
  $name = $_POST['name'] ?? '';
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  
  if (empty($student_id) || empty($name) || empty($email) || empty($password)) {
      $_SESSION['error'] = 'All fields are required';
      return;
  }
  
  if ($password !== $confirm_password) {
      $_SESSION['error'] = 'Passwords do not match';
      return;
  }
  
  if (!str_ends_with($email, '@dlsu.edu.ph')) {
      $_SESSION['error'] = 'Please use your DLSU email address';
      return;
  }
  
  try {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      
      $stmt = mysqli_prepare($conn, "INSERT INTO users (student_id, name, email, password_hash) VALUES (?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt, "ssss", $student_id, $name, $email, $password_hash);
      
      if (mysqli_stmt_execute($stmt)) {
          $user_id = mysqli_insert_id($conn);
          
          // Log activity
          $logStmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'USER_REGISTERED', 'users', ?)");
          mysqli_stmt_bind_param($logStmt, "ii", $user_id, $user_id);
          mysqli_stmt_execute($logStmt);
          
          $_SESSION['success'] = 'Registration successful! Please login.';
      } else {
          $_SESSION['error'] = 'Registration failed';
      }
  } catch (Exception $e) {
      if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
          $_SESSION['error'] = 'Email or Student ID already exists';
      } else {
          $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
      }
  }
  
  header('Location: index.php');
  exit;
}

function handleLogout() {
  if (isset($_COOKIE['remember_token'])) {
      setcookie('remember_token', '', time() - 3600, '/');
  }
  session_destroy();
  header('Location: index.php');
  exit;
}

function handleCreatePost($conn) {
  if (!isLoggedIn()) {
      $_SESSION['error'] = 'Please login to create a post';
      return;
  }
  
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $category_id = $_POST['category_id'] ?? null;
  $post_type = $_POST['post_type'] ?? 'feedback';
  $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
  
  if (empty($title) || empty($content)) {
      $_SESSION['error'] = 'Title and content are required';
      return;
  }
  
  try {
      $stmt = mysqli_prepare($conn, "INSERT INTO feedback_posts (user_id, category_id, title, content, post_type, is_anonymous) VALUES (?, ?, ?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt, "iisssi", getCurrentUserId(), $category_id, $title, $content, $post_type, $is_anonymous);
      
      if (mysqli_stmt_execute($stmt)) {
          $post_id = mysqli_insert_id($conn);
          
          // Log activity
          $logStmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'POST_CREATED', 'feedback_posts', ?)");
          mysqli_stmt_bind_param($logStmt, "ii", getCurrentUserId(), $post_id);
          mysqli_stmt_execute($logStmt);
          
          $_SESSION['success'] = 'Post created successfully!';
      } else {
          $_SESSION['error'] = 'Failed to create post';
      }
  } catch (Exception $e) {
      $_SESSION['error'] = 'Failed to create post: ' . $e->getMessage();
  }
  
  header('Location: index.php');
  exit;
}
function handleVote($conn) {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to vote';
        return;
    }

    $post_id = $_POST['post_id'] ?? 0;
    $vote_type = $_POST['vote_type'] ?? '';

    if (!$post_id || !in_array($vote_type, ['upvote', 'downvote'])) {
        $_SESSION['error'] = 'Invalid vote data';
        return;
    }

    try {
        // Check if user already voted
        $checkStmt = mysqli_prepare($conn, "SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?");
        mysqli_stmt_bind_param($checkStmt, "ii", $user_id, $post_id);
        $user_id = getCurrentUserId(); // ensure variable for bind_param
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        $existing_vote = mysqli_fetch_assoc($result);

        if ($existing_vote) {
            if ($existing_vote['vote_type'] === $vote_type) {
                // Remove vote if same type
                $deleteStmt = mysqli_prepare($conn, "DELETE FROM votes WHERE user_id = ? AND post_id = ?");
                mysqli_stmt_bind_param($deleteStmt, "ii", $user_id, $post_id);
                mysqli_stmt_execute($deleteStmt);
            } else {
                // Update vote if different type
                $updateStmt = mysqli_prepare($conn, "UPDATE votes SET vote_type = ? WHERE user_id = ? AND post_id = ?");
                mysqli_stmt_bind_param($updateStmt, "sii", $vote_type, $user_id, $post_id);
                mysqli_stmt_execute($updateStmt);
            }
        } else {
            // Insert new vote
            $insertStmt = mysqli_prepare($conn, "INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($insertStmt, "iis", $user_id, $post_id, $vote_type);
            mysqli_stmt_execute($insertStmt);
        }

        $_SESSION['success'] = 'Vote recorded!';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to vote: ' . $e->getMessage();
    }

    header('Location: index.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

function loadPosts($conn, $section, $search, $category_filter, $page, $posts_per_page) {
  $offset = ($page - 1) * $posts_per_page;
  
  $where_conditions = [];
  $params = [];
  $types = '';
  
  if ($section === 'suggestions') {
      $where_conditions[] = "fp.post_type = 'suggestion'";
  }
  
  if (!empty($search)) {
      $where_conditions[] = "(fp.title LIKE ? OR fp.content LIKE ?)";
      $search_param = "%$search%";
      $params[] = $search_param;
      $params[] = $search_param;
      $types .= 'ss';
  }
  
  if (!empty($category_filter)) {
      $where_conditions[] = "fp.category_id = ?";
      $params[] = $category_filter;
      $types .= 'i';
  }
  
  $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
  
  $query = "SELECT 
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
      CASE 
          WHEN fp.is_anonymous = 1 THEN 'Anonymous'
          ELSE u.name
      END as author_name,
      c.name as category_name,
      c.color as category_color
  FROM feedback_posts fp
  LEFT JOIN users u ON fp.user_id = u.user_id
  LEFT JOIN categories c ON fp.category_id = c.category_id
  $where_clause
 ORDER BY (fp.upvotes * 2 + fp.comment_count) DESC,
         fp.created_at DESC
  LIMIT ? OFFSET ?";
  
  $params[] = $posts_per_page;
  $params[] = $offset;
  $types .= 'ii';
  
  $stmt = mysqli_prepare($conn, $query);
  if (!empty($params)) {
      mysqli_stmt_bind_param($stmt, $types, ...$params);
  }
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function loadSurveys($conn) {
  $query = "SELECT 
      s.survey_id,
      s.title,
      s.description,
      s.is_anonymous,
      s.start_date,
      s.end_date,
      s.response_count,
      s.created_at,
      u.name as created_by_name
  FROM surveys s
  JOIN users u ON s.created_by = u.user_id
  WHERE s.is_active = 1 
  AND (s.end_date IS NULL OR s.end_date > NOW())
  ORDER BY s.created_at DESC";
  
  $result = mysqli_query($conn, $query);
  return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function timeAgo($datetime) {
  $time = time() - strtotime($datetime);
  
  if ($time < 60) return 'Just now';
  if ($time < 3600) return floor($time/60) . 'm ago';
  if ($time < 86400) return floor($time/3600) . 'h ago';
  if ($time < 2592000) return floor($time/86400) . 'd ago';
  
  return date('M j, Y', strtotime($datetime));
}

function truncateText($text, $length = 200) {
  return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SEFS - CSG Student Engagement and Feedback System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body> 
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
      <div class="container">
          <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-comments me-2"></i>SEFS</a>
          
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
              <span class="navbar-toggler-icon"></span>
          </button>
          
          <div class="collapse navbar-collapse" id="navbarNav">
              <ul class="navbar-nav me-auto">
                  <li class="nav-item">
                      <a class="nav-link <?= $section === 'home' ? 'active' : '' ?>" href="index.php?section=home">
                          <i class="fas fa-home me-1"></i>Home
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link <?= $section === 'suggestions' ? 'active' : '' ?>" href="index.php?section=suggestions">
                          <i class="fas fa-lightbulb me-1"></i>Suggestions
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link <?= $section === 'surveys' ? 'active' : '' ?>" href="index.php?section=surveys">
                          <i class="fas fa-poll me-1"></i>Surveys
                      </a>
                  </li>
                  <?php if (isAdmin()): ?>
                  <li class="nav-item">
                      <a class="nav-link <?= $section === 'admin' ? 'active' : '' ?>" href="admin.php">
                          <i class="fas fa-chart-bar me-1"></i>Dashboard
                      </a>
                  </li>
                  <?php endif; ?>
              </ul>
              
              <?php if (!isLoggedIn()): ?>
              <div class="navbar-nav">
                  <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
                  <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#registerModal">Sign Up</button>
              </div>
              <?php else: ?>
              <div class="navbar-nav">
                  <div class="dropdown">
                      <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                          <i class="fas fa-user me-1"></i><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?>
                      </a>
                      <ul class="dropdown-menu">
                          <li>
                              <form method="POST" class="d-inline">
                                  <input type="hidden" name="action" value="logout">
                                  <button type="submit" class="dropdown-item">
                                      <i class="fas fa-sign-out-alt me-2"></i>Logout
                                  </button>
                              </form>
                          </li>
                      </ul>
                  </div>
              </div>
              <?php endif; ?>
          </div>
      </div>
  </nav>

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

  <div class="container mt-4">
      <?php if ($section === 'home' || $section === 'suggestions'): ?>
      <div class="row mb-4">
          <div class="col-md-8">
              <form method="GET" class="d-flex">
                  <input type="hidden" name="section" value="<?= $section ?>">
                  <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search feedback and suggestions...">
                  <button class="btn btn-outline-secondary" type="submit">
                      <i class="fas fa-search"></i>
                  </button>
              </form>
          </div>
          <div class="col-md-4">
              <form method="GET">
                  <input type="hidden" name="section" value="<?= $section ?>">
                  <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                  <select class="form-select" name="category" onchange="this.form.submit()">
                      <option value="">All Categories</option>
                      <?php foreach ($categories as $category): ?>
                      <option value="<?= $category['category_id'] ?>" <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($category['name']) ?>
                      </option>
                      <?php endforeach; ?>
                  </select>
              </form>
          </div>
      </div>

      <?php if (isLoggedIn()): ?>
      <div class="row mb-4">
          <div class="col-12">
              <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPostModal">
                  <i class="fas fa-plus me-2"></i>Share Your Feedback
              </button>
          </div>
      </div>
      <?php endif; ?>
 
      <div class="row">
          <div class="col-12">
              <?php if ($section === 'suggestions'): ?>
              <h2><i class="fas fa-lightbulb me-2"></i>Student Suggestions</h2>
              <p class="text-muted">Community-driven suggestions for improving university services and policies.</p>
              <?php endif; ?>

              <?php if (empty($posts)): ?>
              <div class="text-center py-5">
                  <i class="fas fa-<?= $section === 'suggestions' ? 'lightbulb' : 'comments' ?> fa-3x text-muted mb-3"></i>
                  <h5 class="text-muted">No <?= $section === 'suggestions' ? 'suggestions' : 'posts' ?> found</h5>
                  <p class="text-muted">
                      <?php if (isLoggedIn()): ?>
                          Be the first to share your <?= $section === 'suggestions' ? 'suggestion' : 'feedback' ?>!
                      <?php else: ?>
                          <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a> to share your feedback.
                      <?php endif; ?>
                  </p>
              </div>
              <?php else: ?>
              <?php foreach ($posts as $post): ?>
              <div class="post-card mb-4">
                  <div class="post-header">
                      <h3 class="post-title">
                          <a href="post.php?id=<?= $post['post_id'] ?>" class="text-decoration-none">
                              <?= htmlspecialchars($post['title']) ?>
                          </a>
                      </h3>
                      <div class="post-meta">
                          <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($post['author_name']) ?></span>
                          <span class="post-time" data-created-at="<?= htmlspecialchars($post['created_at']) ?>">
                              <i class="fas fa-clock me-1"></i><?= timeAgo($post['created_at']) ?>
                          </span>
                          <?php if ($post['category_name']): ?>
                          <span class="post-category" style="background-color: <?= $post['category_color'] ?>">
                              <?= htmlspecialchars($post['category_name']) ?>
                          </span>
                          <?php endif; ?>
                      </div>
                  </div>
                  <div class="post-content">
                      <p><?= nl2br(htmlspecialchars(truncateText($post['content']))) ?></p>
                  </div>
                  <div class="post-actions">
                      <div class="vote-buttons">
                          <?php if (isLoggedIn()): ?>
                          <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="vote">
                              <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                              <input type="hidden" name="vote_type" value="upvote">
                              <button type="submit" class="vote-btn upvote">
                                  <i class="fas fa-thumbs-up"></i>
                                  <span><?= $post['upvotes'] ?></span>
                              </button>
                          </form>
                          <form method="POST" class="d-inline">
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
                      <a href="post.php?id=<?= $post['post_id'] ?>" class="comment-btn">
                          <i class="fas fa-comment"></i>
                          <span><?= $post['comment_count'] ?> Comments</span>
                      </a>
                  </div>
              </div>
              <?php endforeach; ?>

 
              <?php if (count($posts) === $posts_per_page): ?>
              <div class="text-center mt-4">
                  <a href="?section=<?= $section ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&page=<?= $page + 1 ?>" 
                     class="btn btn-outline-primary">
                      Load More Posts
                  </a>
              </div>
              <?php endif; ?>
              <?php endif; ?>
          </div>
      </div>

      <?php elseif ($section === 'surveys'): ?>
      <div class="row">
          <div class="col-12">
              <h2><i class="fas fa-poll me-2"></i>Active Surveys</h2>
              <p class="text-muted">Participate in surveys to help improve university services.</p>

              <?php if (empty($surveys)): ?>
              <div class="text-center py-5">
                  <i class="fas fa-poll fa-3x text-muted mb-3"></i>
                  <h5 class="text-muted">No active surveys</h5>
                  <p class="text-muted">Check back later for new surveys!</p>
              </div>
              <?php else: ?>
              <?php foreach ($surveys as $survey): ?>
              <div class="survey-card">
                  <h4 class="survey-title"><?= htmlspecialchars($survey['title']) ?></h4>
                  <p class="survey-description"><?= htmlspecialchars($survey['description']) ?></p>
                  <div class="mt-3">
<a href="survey.php?id=<?= $survey['survey_id'] ?>" class="btn btn-primary">
    Take Survey
                      </a>
                  </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
          </div>
      </div>
      <?php endif; ?>
  </div>

  <div class="modal fade" id="loginModal" tabindex="-1">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title">Login to SEFS</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <form method="POST">
                      <input type="hidden" name="action" value="login">
                      <div class="mb-3">
                          <label for="loginEmail" class="form-label">DLSU Email</label>
                          <input type="email" class="form-control" name="email" id="loginEmail" required>
                      </div>
                      <div class="mb-3">
                          <label for="loginPassword" class="form-label">Password</label>
                          <input type="password" class="form-control" name="password" id="loginPassword" required>
                      </div>
                      <div class="mb-3 form-check">
                          <input type="checkbox" class="form-check-input" name="remember" id="rememberMe">
                          <label class="form-check-label" for="rememberMe">Remember me for 3 weeks</label>
                      </div>
                      <button type="submit" class="btn btn-primary w-100">Login</button>
                  </form>
              </div>
          </div>
      </div>
  </div>

  <div class="modal fade" id="registerModal" tabindex="-1">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title">Sign Up for SEFS</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <form method="POST">
                      <input type="hidden" name="action" value="register">
                      <div class="mb-3">
                          <label for="registerStudentId" class="form-label">Student ID</label>
                          <input type="text" class="form-control" name="student_id" id="registerStudentId" required>
                      </div>
                      <div class="mb-3">
                          <label for="registerName" class="form-label">Full Name</label>
                          <input type="text" class="form-control" name="name" id="registerName" required>
                      </div>
                      <div class="mb-3">
                          <label for="registerEmail" class="form-label">DLSU Email</label>
                          <input type="email" class="form-control" name="email" id="registerEmail" placeholder="your.name@dlsu.edu.ph" required>
                      </div>
                      <div class="mb-3">
                          <label for="registerPassword" class="form-label">Password</label>
                          <input type="password" class="form-control" name="password" id="registerPassword" required>
                      </div>
                      <div class="mb-3">
                          <label for="confirmPassword" class="form-label">Confirm Password</label>
                          <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                      </div>
                      <button type="submit" class="btn btn-success w-100">Sign Up</button>
                  </form>
              </div>
          </div>
      </div>
  </div>

  <?php if (isLoggedIn()): ?>
  <div class="modal fade" id="createPostModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title">Share Your Feedback</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <form method="POST">
                      <input type="hidden" name="action" value="create_post">
                      <div class="mb-3">
                          <label for="postTitle" class="form-label">Title</label>
                          <input type="text" class="form-control" name="title" id="postTitle" required>
                      </div>
                      <div class="mb-3">
                          <label for="postCategory" class="form-label">
Category</label>
                          <select class="form-select" name="category_id" id="postCategory">
                              <?php foreach ($categories as $category): ?>
                              <option value="<?= $category['category_id'] ?>">
                                  <?= htmlspecialchars($category['name']) ?>
                              </option>
                              <?php endforeach; ?>
                          </select>
                      </div>
                      <div class="mb-3">
                          <label for="postType" class="form-label">Type</label>
                          <select class="form-select" name="post_type" id="postType" required>
                              <option value="feedback">Feedback</option>
                              <option value="suggestion">Suggestion</option>
                          </select>
                      </div>
                      <div class="mb-3">
                          <label for="postContent" class="form-label">Content</label>
                          <textarea class="form-control" name="content" id="postContent" rows="5" required></textarea>
                      </div>
                      <div class="mb-3 form-check">
                          <input type="checkbox" class="form-check-input" name="is_anonymous" id="postAnonymous">
                          <label class="form-check-label" for="postAnonymous">Post anonymously</label>
                      </div>
                      <button type="submit" class="btn btn-success">Submit Post</button>
                  </form>
              </div>
          </div>
      </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Helper to get time ago string
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
              el.innerHTML = '<i class="fas fa-clock me-1"></i>' + getTimeAgo(createdAt);
          }
      });
  }
  setInterval(updatePostTimes, 30000); // update every 30 seconds
  document.addEventListener('DOMContentLoaded', updatePostTimes);
  </script>
</body>
</html>
