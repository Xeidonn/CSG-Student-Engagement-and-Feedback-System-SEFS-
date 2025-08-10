<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getSurveys($db);
        } elseif ($action === 'questions') {
            getSurveyQuestions($db);
        }
        break;
    case 'POST':
        if ($action === 'create') {
            createSurvey($db);
        } elseif ($action === 'respond') {
            submitSurveyResponse($db);
        }
        break;
}
function getSurveys($db) {
    try {
        $result = $db->query("CALL sp_get_active_surveys()");
        $surveys = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        while ($db->more_results() && $db->next_result()) { /* flush */ }

        echo json_encode(['success' => true, 'surveys' => $surveys]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch surveys: ' . $e->getMessage()]);
    }
}

function getSurveyQuestions($db) {
    $survey_id = $_GET['survey_id'] ?? null;
    if (!$survey_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Survey ID is required']);
        return;
    }
    try {
        $stmt = $db->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY order_index");
        $stmt->bind_param('i', $survey_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $questions = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
        $stmt->close();

        foreach ($questions as &$q) {
            if (!empty($q['options'])) {
                $q['options'] = json_decode($q['options'], true);
            }
        }
        echo json_encode(['success' => true, 'questions' => $questions]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch questions: ' . $e->getMessage()]);
    }
}

function createSurvey($db) {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['title']) || !isset($input['questions'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and questions are required']);
        return;
    }

    $title = $input['title'];
    $description = $input['description'] ?? '';
    $is_anonymous = isset($input['is_anonymous']) ? (int)!!$input['is_anonymous'] : 1;
    $end_date = $input['end_date'] ?? null; // pass NULL if not set
    $user_id = getCurrentUserId();

    try {
        $db->begin_transaction();

        // CALL sp_create_survey(user_id, title, description, is_anonymous, end_date)
        $stmt = $db->prepare("CALL sp_create_survey(?, ?, ?, ?, ?)");
        $stmt->bind_param('issis', $user_id, $title, $description, $is_anonymous, $end_date);
        $stmt->execute();

        // get the first result set (survey_id)
        $res = $stmt->get_result();
        if (!$res) {
            throw new Exception('sp_create_survey returned no result');
        }
        $row = $res->fetch_assoc();
        $survey_id = (int)$row['survey_id'];
        $res->free();
        $stmt->close();

        // If the procedure leaves more result sets, clear them.
        while ($db->more_results() && $db->next_result()) { /* flush */ }

        // Insert questions
        $q = $db->prepare("INSERT INTO survey_questions 
            (survey_id, question_text, question_type, options, is_required, order_index)
            VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($input['questions'] as $i => $question) {
            $text = $question['text'];
            $type = $question['type']; // e.g., 'open_ended' | 'rating' | 'multiple_choice'
            $options = isset($question['options']) ? json_encode($question['options']) : null;
            $required = isset($question['required']) ? (int)!!$question['required'] : 0;

            $q->bind_param('isssii', $survey_id, $text, $type, $options, $required, $i);
            $q->execute();
        }
        $q->close();

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Survey created successfully',
            'survey_id' => $survey_id
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create survey: ' . $e->getMessage()]);
    }
}
function submitSurveyResponse($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['survey_id']) || !isset($input['responses'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Survey ID and responses are required']);
        return;
    }

    $survey_id = (int)$input['survey_id'];
    $user_id = isLoggedIn() ? getCurrentUserId() : null;

    try {
        $db->begin_transaction();

        $stmt = $db->prepare(
            "INSERT INTO survey_responses 
             (survey_id, user_id, question_id, answer_text, answer_rating, answer_choice) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        foreach ($input['responses'] as $r) {
            $qid = (int)$r['question_id'];

            // nullable fields
            $answer_text   = isset($r['answer_text'])   && $r['answer_text']   !== '' ? $r['answer_text'] : null;
            $answer_rating = isset($r['answer_rating']) && $r['answer_rating'] !== '' ? (string)(int)$r['answer_rating'] : null; // bind as string for NULL safety
            $answer_choice = isset($r['answer_choice']) && $r['answer_choice'] !== '' ? $r['answer_choice'] : null;

            // types: i = int, s = string; using 's' for nullable values is safe
            $stmt->bind_param(
                'iiisss',
                $survey_id,
                $user_id,      // can be null (anonymous)
                $qid,
                $answer_text,
                $answer_rating,
                $answer_choice
            );
            $stmt->execute();
        }
        $stmt->close();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Survey response submitted successfully']);
    } catch (Throwable $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit response: ' . $e->getMessage()]);
    }
}

?>
