-- Triggers for SEFS

DELIMITER //

-- Update vote counts when votes are inserted/updated/deleted
CREATE TRIGGER tr_votes_after_insert
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    UPDATE feedback_posts 
    SET 
        upvotes = (SELECT COUNT(*) FROM votes WHERE post_id = NEW.post_id AND vote_type = 'upvote'),
        downvotes = (SELECT COUNT(*) FROM votes WHERE post_id = NEW.post_id AND vote_type = 'downvote')
    WHERE post_id = NEW.post_id;
END //

CREATE TRIGGER tr_votes_after_update
AFTER UPDATE ON votes
FOR EACH ROW
BEGIN
    UPDATE feedback_posts 
    SET 
        upvotes = (SELECT COUNT(*) FROM votes WHERE post_id = NEW.post_id AND vote_type = 'upvote'),
        downvotes = (SELECT COUNT(*) FROM votes WHERE post_id = NEW.post_id AND vote_type = 'downvote')
    WHERE post_id = NEW.post_id;
END //

CREATE TRIGGER tr_votes_after_delete
AFTER DELETE ON votes
FOR EACH ROW
BEGIN
    UPDATE feedback_posts 
    SET 
        upvotes = (SELECT COUNT(*) FROM votes WHERE post_id = OLD.post_id AND vote_type = 'upvote'),
        downvotes = (SELECT COUNT(*) FROM votes WHERE post_id = OLD.post_id AND vote_type = 'downvote')
    WHERE post_id = OLD.post_id;
END //

-- Update comment count when comments are added/deleted
CREATE TRIGGER tr_comments_after_insert
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    UPDATE feedback_posts 
    SET comment_count = (SELECT COUNT(*) FROM comments WHERE post_id = NEW.post_id)
    WHERE post_id = NEW.post_id;
END //

CREATE TRIGGER tr_comments_after_delete
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    UPDATE feedback_posts 
    SET comment_count = (SELECT COUNT(*) FROM comments WHERE post_id = OLD.post_id)
    WHERE post_id = OLD.post_id;
END //

-- Update survey response count
CREATE TRIGGER tr_survey_responses_after_insert
AFTER INSERT ON survey_responses
FOR EACH ROW
BEGIN
    UPDATE surveys 
    SET response_count = (
        SELECT COUNT(DISTINCT COALESCE(user_id, CONCAT('anon_', response_id))) 
        FROM survey_responses 
        WHERE survey_id = NEW.survey_id
    )
    WHERE survey_id = NEW.survey_id;
END //

-- Mark comments as edited when updated
CREATE TRIGGER tr_comments_before_update
BEFORE UPDATE ON comments
FOR EACH ROW
BEGIN
    IF OLD.content != NEW.content THEN
        SET NEW.is_edited = TRUE;
    END IF;
END //

-- Log activity for important actions
CREATE TRIGGER tr_feedback_posts_after_update
AFTER UPDATE ON feedback_posts
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_logs (user_id, action, table_name, record_id, details)
        VALUES (NEW.user_id, 'POST_STATUS_CHANGED', 'feedback_posts', NEW.post_id, 
                JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status));
    END IF;
END //

DELIMITER ;
