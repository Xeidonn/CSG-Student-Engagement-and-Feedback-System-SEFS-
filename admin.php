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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold"><i class="fas fa-comments me-2"></i>SEFS</a>
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
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Analytics Dashboard</h2>

  <div class="d-flex gap-2">
    <!-- View Responses -->
    <a href="survey_responses.php" class="btn btn-outline-secondary">
      <i class="fas fa-poll me-1"></i> View Responses
    </a>
    <!-- Create Survey -->
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSurveyModal">
      <i class="fas fa-plus me-1"></i> Create Survey
    </button>
  </div>
</div>

<p class="text-muted">Overview of system activity and engagement metrics.</p>

        </div>
        
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

<div class="modal fade" id="createSurveyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="surveyForm">
        <div class="modal-header">
          <h5 class="modal-title">Create Survey</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="title" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">End Date</label>
              <input type="date" class="form-control" name="end_date">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="2"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_anonymous" id="isAnon" checked>
                <label class="form-check-label" for="isAnon">Anonymous responses</label>
              </div>
            </div>
          </div>

          <hr class="my-3">

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Questions <span class="text-danger">*</span></h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="addQuestionBtn">
              <i class="fas fa-plus"></i> Add Question
            </button>
          </div>

          <div id="questionsWrap" class="vstack gap-3"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  const questionsWrap = document.getElementById('questionsWrap');
  const addBtn = document.getElementById('addQuestionBtn');
  const form = document.getElementById('surveyForm');

  function questionBlock() {
    const el = document.createElement('div');
    el.className = 'card p-3';
    el.innerHTML = `
      <div class="row g-2 align-items-start">
        <div class="col-md-7">
          <label class="form-label">Question text</label>
          <input type="text" class="form-control q-text" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Type</label>
          <select class="form-select q-type">
            <option value="open_ended">Text</option>
            <option value="rating">Rating (1-5)</option>
            <option value="multiple_choice">Multiple choice</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">Required</label>
          <input type="checkbox" class="form-check-input q-required">
        </div>
        <div class="col-12 q-options d-none">
          <label class="form-label">Options (one per line)</label>
          <textarea class="form-control q-opts" rows="2" placeholder="Option A&#10;Option B"></textarea>
        </div>
        <div class="col-12 text-end">
          <button type="button" class="btn btn-sm btn-outline-danger remove-q">Remove</button>
        </div>
      </div>`;
    const typeSel = el.querySelector('.q-type');
    const optsBox = el.querySelector('.q-options');
    typeSel.addEventListener('change', () => {
      optsBox.classList.toggle('d-none', typeSel.value !== 'multiple_choice');
    });
    el.querySelector('.remove-q').addEventListener('click', () => el.remove());
    return el;
  }

  addBtn.addEventListener('click', () => questionsWrap.appendChild(questionBlock()));
  questionsWrap.appendChild(questionBlock()); // start with one

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const title = form.title.value.trim();
    if (!title) { alert('Title is required'); return; }

    const questions = [...questionsWrap.children].map((card) => {
      const text = card.querySelector('.q-text').value.trim();
      const type = card.querySelector('.q-type').value;   // <-- now matches DB enum
      const required = card.querySelector('.q-required').checked;
      let options;
      if (type === 'multiple_choice') {
        options = card.querySelector('.q-opts').value
          .split('\n').map(s => s.trim()).filter(Boolean);
      }
      return { text, type, required, options };
    }).filter(q => q.text);

    if (questions.length === 0) { alert('Add at least one question'); return; }

    const payload = {
      title,
      description: form.description.value.trim(),
      is_anonymous: form.is_anonymous.checked,
      end_date: form.end_date.value || null,
      questions
    };

    try {
      // If surveys.php is beside admin.php, change to 'surveys.php?action=create'
      const res = await fetch('api/surveys.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      // Read raw text first to surface PHP errors (HTML) if any
      const raw = await res.text();
      let data;
      try { data = JSON.parse(raw); }
      catch { throw new Error('Server did not return JSON. Response:\n' + raw.slice(0, 400)); }

      if (!res.ok || data.success !== true) {
        throw new Error(data.error || 'Request failed');
      }

      alert('Survey created! ID: ' + data.survey_id);
      location.reload();
    } catch (err) {
      console.error(err);
      alert('Failed to create survey: ' + err.message);
    }
  });
})();
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
