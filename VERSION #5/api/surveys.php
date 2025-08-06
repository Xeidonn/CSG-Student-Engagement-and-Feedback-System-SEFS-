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
        $stmt = $db->prepare("CALL sp_get_active_surveys()");
        $stmt->execute();
        $surveys = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'surveys' => $surveys]);
    } catch (Exception $e) {
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
        $stmt->execute([$survey_id]);
        $questions = $stmt->fetchAll();
        
        // Parse JSON options
        foreach ($questions as &$question) {
            if ($question['options']) {
                $question['options'] = json_decode($question['options'], true);
            }
        }
        
        echo json_encode(['success' => true, 'questions' => $questions]);
    } catch (Exception $e) {
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
    
    try {
        $db->beginTransaction();
        
        // Create survey
        $stmt = $db->prepare("CALL sp_create_survey(?, ?, ?, ?, ?)");
        $stmt->execute([
            getCurrentUserId(),
            $input['title'],
            $input['description'] ?? '',
            $input['is_anonymous'] ?? true,
            $input['end_date'] ?? null
        ]);
        
        $result = $stmt->fetch();
        $survey_id = $result['survey_id'];
        
        // Add questions
        $stmt = $db->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, options, is_required, order_index) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($input['questions'] as $index => $question) {
            $options = isset($question['options']) ? json_encode($question['options']) : null;
            $stmt->execute([
                $survey_id,
                $question['text'],
                $question['type'],
                $options,
                $question['required'] ?? false,
                $index
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Survey created successfully',
            'survey_id' => $survey_id
        ]);
    } catch (Exception $e) {
        $db->rollBack();
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
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO survey_responses (survey_id, user_id, question_id, answer_text, answer_rating, answer_choice) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($input['responses'] as $response) {
            $user_id = isLoggedIn() ? getCurrentUserId() : null;
            
            $stmt->execute([
                $input['survey_id'],
                $user_id,
                $response['question_id'],
                $response['answer_text'] ?? null,
                $response['answer_rating'] ?? null,
                $response['answer_choice'] ?? null
            ]);
        }
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Survey response submitted successfully']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit response: ' . $e->getMessage()]);
    }
}
?>
