-- Stored Procedures for SEFS

DELIMITER //

-- User Authentication Procedures
CREATE PROCEDURE sp_authenticate_user(
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255)
)
BEGIN
    SELECT user_id, student_id, name, email, role, password_hash
    FROM users 
    WHERE email = p_email AND is_active = TRUE;
END //

CREATE PROCEDURE sp_register_user(
    IN p_student_id VARCHAR(20),
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO users (student_id, name, email, password_hash)
    VALUES (p_student_id, p_name, p_email, p_password_hash);
    
    INSERT INTO activity_logs (user_id, action, table_name, record_id)
    VALUES (LAST_INSERT_ID(), 'USER_REGISTERED', 'users', LAST_INSERT_ID());
    
    COMMIT;
    
    SELECT LAST_INSERT_ID() as user_id;
END //

-- Feedback Post Procedures
CREATE PROCEDURE sp_create_feedback_post(
    IN p_user_id INT,
    IN p_category_id INT,
    IN p_title VARCHAR(200),
    IN p_content TEXT,
    IN p_post_type VARCHAR(20),
    IN p_is_anonymous BOOLEAN
)
BEGIN
    DECLARE v_post_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO feedback_posts (user_id, category_id, title, content, post_type, is_anonymous)
    VALUES (p_user_id, p_category_id, p_title, p_content, p_post_type, p_is_anonymous);
    
    SET v_post_id = LAST_INSERT_ID();
    
    INSERT INTO activity_logs (user_id, action, table_name, record_id)
    VALUES (p_user_id, 'POST_CREATED', 'feedback_posts', v_post_id);
    
    COMMIT;
    
    SELECT v_post_id as post_id;
END //

CREATE PROCEDURE sp_get_feedback_posts(
    IN p_limit INT,
    IN p_offset INT,
    IN p_category_id INT,
    IN p_search_term VARCHAR(200)
)
BEGIN
    SELECT 
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
            WHEN fp.is_anonymous = TRUE THEN 'Anonymous'
            ELSE u.name
        END as author_name,
        c.name as category_name,
        c.color as category_color
    FROM feedback_posts fp
    LEFT JOIN users u ON fp.user_id = u.user_id
    LEFT JOIN categories c ON fp.category_id = c.category_id
    WHERE 
        (p_category_id IS NULL OR fp.category_id = p_category_id)
        AND (p_search_term IS NULL OR 
             fp.title LIKE CONCAT('%', p_search_term, '%') OR 
             fp.content LIKE CONCAT('%', p_search_term, '%'))
    ORDER BY fp.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //

-- Voting Procedures
CREATE PROCEDURE sp_vote_post(
    IN p_user_id INT,
    IN p_post_id INT,
    IN p_vote_type VARCHAR(10)
)
BEGIN
    DECLARE v_existing_vote VARCHAR(10);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Check if user already voted
    SELECT vote_type INTO v_existing_vote
    FROM votes 
    WHERE user_id = p_user_id AND post_id = p_post_id;
    
    IF v_existing_vote IS NOT NULL THEN
        IF v_existing_vote = p_vote_type THEN
            -- Remove vote if same type
            DELETE FROM votes 
            WHERE user_id = p_user_id AND post_id = p_post_id;
        ELSE
            -- Update vote if different type
            UPDATE votes 
            SET vote_type = p_vote_type, created_at = CURRENT_TIMESTAMP
            WHERE user_id = p_user_id AND post_id = p_post_id;
        END IF;
    ELSE
        -- Insert new vote
        INSERT INTO votes (user_id, post_id, vote_type)
        VALUES (p_user_id, p_post_id, p_vote_type);
    END IF;
    
    COMMIT;
    
    -- Return updated vote counts
    SELECT upvotes, downvotes 
    FROM feedback_posts 
    WHERE post_id = p_post_id;
END //

-- Comment Procedures
CREATE PROCEDURE sp_add_comment(
    IN p_post_id INT,
    IN p_user_id INT,
    IN p_parent_comment_id INT,
    IN p_content TEXT
)
BEGIN
    DECLARE v_comment_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO comments (post_id, user_id, parent_comment_id, content)
    VALUES (p_post_id, p_user_id, p_parent_comment_id, p_content);
    
    SET v_comment_id = LAST_INSERT_ID();
    
    INSERT INTO activity_logs (user_id, action, table_name, record_id)
    VALUES (p_user_id, 'COMMENT_ADDED', 'comments', v_comment_id);
    
    COMMIT;
    
    SELECT v_comment_id as comment_id;
END //

CREATE PROCEDURE sp_get_comments(
    IN p_post_id INT
)
BEGIN
    SELECT 
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
    WHERE c.post_id = p_post_id
    ORDER BY c.created_at ASC;
END //

-- Survey Procedures
CREATE PROCEDURE sp_create_survey(
    IN p_created_by INT,
    IN p_title VARCHAR(200),
    IN p_description TEXT,
    IN p_is_anonymous BOOLEAN,
    IN p_end_date DATETIME
)
BEGIN
    DECLARE v_survey_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO surveys (created_by, title, description, is_anonymous, end_date)
    VALUES (p_created_by, p_title, p_description, p_is_anonymous, p_end_date);
    
    SET v_survey_id = LAST_INSERT_ID();
    
    INSERT INTO activity_logs (user_id, action, table_name, record_id)
    VALUES (p_created_by, 'SURVEY_CREATED', 'surveys', v_survey_id);
    
    COMMIT;
    
    SELECT v_survey_id as survey_id;
END //

CREATE PROCEDURE sp_get_active_surveys()
BEGIN
    SELECT 
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
    WHERE s.is_active = TRUE 
    AND (s.end_date IS NULL OR s.end_date > NOW())
    ORDER BY s.created_at DESC;
END //

-- Analytics Procedures
CREATE PROCEDURE sp_get_dashboard_analytics()
BEGIN
    -- Total counts
    SELECT 
        (SELECT COUNT(*) FROM feedback_posts) as total_posts,
        (SELECT COUNT(*) FROM feedback_posts WHERE post_type = 'suggestion') as total_suggestions,
        (SELECT COUNT(*) FROM comments) as total_comments,
        (SELECT COUNT(*) FROM surveys WHERE is_active = TRUE) as active_surveys,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students;
    
    -- Posts by category
    SELECT 
        c.name as category_name,
        c.color as category_color,
        COUNT(fp.post_id) as post_count
    FROM categories c
    LEFT JOIN feedback_posts fp ON c.category_id = fp.category_id
    GROUP BY c.category_id, c.name, c.color
    ORDER BY post_count DESC;
    
    -- Recent activity
    SELECT 
        al.action,
        al.created_at,
        u.name as user_name,
        al.table_name,
        al.record_id
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10;
END //

DELIMITER ;
