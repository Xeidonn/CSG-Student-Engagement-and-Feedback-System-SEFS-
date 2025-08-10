<?php
session_start();
require_once 'config/database.php';

if (!function_exists('isAdmin') || !isAdmin()) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Helper to escape HTML
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;

// -------- Load surveys list (for header / back nav) --------
$surveys = [];
$survey_title = null;

try {
    $sql = "SELECT s.survey_id AS id, s.title, s.created_at
            FROM surveys s
            ORDER BY s.created_at DESC";
    $res = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        $surveys[] = $row;
        if ($survey_id && (int)$row['id'] === $survey_id) {
            $survey_title = $row['title'];
        }
    }
} catch (Throwable $e) {
    // ignore listing errors for now
}

// -------- If a particular survey is requested, fetch responses with question text --------
$rows = [];
if ($survey_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                r.user_id,
                r.question_id,
                r.answer_text,
                r.answer_rating,
                r.answer_choice,
                r.submitted_at AS submitted_at,
                q.order_index,
                q.question_text,
                q.question_type,
                u.name AS user_name
            FROM survey_responses r
            JOIN survey_questions q ON q.question_id = r.question_id
            LEFT JOIN users u ON u.user_id = r.user_id
            WHERE r.survey_id = ?
            ORDER BY r.submitted_at DESC, q.order_index ASC
        ");
        $stmt->bind_param('i', $survey_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    } catch (Throwable $e) {
        $error = "Failed to load responses: " . $e->getMessage();
    }
} else {
    // -------- Default view: list surveys with response counts --------
    try {
        $sql = "
            SELECT 
                s.survey_id AS id,
                s.title,
                s.created_at,
                COALESCE(COUNT(r.survey_id), 0) AS responses
            FROM surveys s
            LEFT JOIN survey_responses r ON r.survey_id = s.survey_id
            GROUP BY s.survey_id, s.title, s.created_at
            ORDER BY s.created_at DESC
        ";
        $res = $db->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    } catch (Throwable $e) {
        $error = "Failed to load surveys: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Survey Responses</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-light">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">
          <i class="fas fa-list-ul me-2"></i>
          <?= $survey_id ? 'Responses: ' . h($survey_title ?: ('Survey #' . $survey_id)) : 'Survey Responses' ?>
        </h3>
        <div class="text-muted small">
          <?= $survey_id ? 'Showing individual answers for this survey.' : 'Overview of all surveys and their response counts.' ?>
        </div>
      </div>
      <div>
        <?php if ($survey_id): ?>
          <a href="survey_responses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Surveys
          </a>
        <?php else: ?>
          <a href="admin.php" class="btn btn-outline-secondary">
            <i class="fas fa-gauge me-1"></i> Back to Dashboard
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$survey_id): ?>
      <div class="card">
        <div class="card-header"><strong>Surveys</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Created</th>
                  <th class="text-center">Responses</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No surveys yet.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= h($r['title']) ?></td>
                  <td><?= h($r['created_at']) ?></td>
                  <td class="text-center"><?= (int)$r['responses'] ?></td>
                  <td class="text-end">
                    <a href="survey_responses.php?survey_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">
                      <i class="fas fa-eye me-1"></i> View Responses
                    </a>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <strong>Responses for: <?= h($survey_title ?: ('Survey #' . $survey_id)) ?></strong>
          <div>
            <a href="survey_responses.php" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-list me-1"></i> All Surveys
            </a>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Submitted</th>
                  <th>User</th>
                  <th>Question</th>
                  <th>Answer</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No responses yet.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= isset($r['submitted_at']) && $r['submitted_at'] !== null ? h($r['submitted_at']) : '—' ?></td>
                  <td><?= $r['user_name'] ? h($r['user_name']) : ($r['user_id'] ? ('User #' . (int)$r['user_id']) : 'Anonymous') ?></td>
                  <td>
                    <div class="fw-semibold"><?= h($r['question_text']) ?></div>
                    <div class="text-muted small"><?= h($r['question_type']) ?></div>
                  </td>
                  <td>
                    <?php
                      $ans = $r['answer_text'] ?? $r['answer_choice'] ?? $r['answer_rating'];
                      echo h((string)$ans);
                    ?>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
