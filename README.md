# CSG Student Engagement and Feedback System (SEFS)

A comprehensive web-based platform designed to improve student representation and communication at De La Salle University through transparent feedback collection, anonymous surveys, and real-time analytics.

## Features

### Core Functionality
- **User Authentication**: Secure login/signup with DLSU email validation
- **Feedback Posts**: Students can share feedback and suggestions with categorization
- **Anonymous Surveys**: Create and respond to surveys anonymously
- **Voting System**: Upvote/downvote posts to gauge community sentiment
- **Commenting System**: Nested comments with edit/delete functionality
- **Search & Filter**: Find posts by keywords, categories, or tags
- **Admin Dashboard**: Real-time analytics and system management

### User Roles
- **Students**: Post feedback, vote, comment, participate in surveys
- **CSG Officers/Admins**: All student features plus analytics dashboard and survey creation

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+ with stored procedures and triggers
- **Charts**: Chart.js for analytics visualization
- **Icons**: Font Awesome 6

## Installation

### Prerequisites
- Web server (Apache/Nginx)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Modern web browser

### Setup Instructions

1. **Clone/Download the project files**
   \`\`\`bash
   git clone [repository-url]
   cd sefs-system
   \`\`\`

2. **Configure Database Connection**
   - Edit `config/database.php`
   - Update database credentials:
     \`\`\`php
     private $host = 'localhost';
     private $db_name = 'sefs_db';
     private $username = 'your_username';
     private $password = 'your_password';
     \`\`\`

3. **Run Installation**
   - Navigate to `http://your-domain/install.php`
   - Follow the installation wizard
   - Delete `install.php` after successful installation

4. **Access the Application**
   - Open `http://your-domain/index.html`
   - Default admin login: `admin@dlsu.edu.ph` / `admin123`

## Database Architecture

### Key Tables
- `users`: User accounts and authentication
- `feedback_posts`: Student feedback and suggestions
- `comments`: Nested comment system
- `surveys`: Survey management
- `survey_questions`: Survey question definitions
- `survey_responses`: Anonymous survey responses
- `votes`: Post voting system
- `categories`: Post categorization
- `activity_logs`: System activity tracking

### Stored Procedures
- `sp_authenticate_user()`: User authentication
- `sp_register_user()`: User registration
- `sp_create_feedback_post()`: Post creation
- `sp_get_feedback_posts()`: Post retrieval with filtering
- `sp_vote_post()`: Voting mechanism
- `sp_add_comment()`: Comment creation
- `sp_get_comments()`: Comment retrieval
- `sp_create_survey()`: Survey creation
- `sp_get_active_surveys()`: Active survey listing
- `sp_get_dashboard_analytics()`: Analytics data

### Triggers
- Vote count updates on post voting
- Comment count maintenance
- Survey response tracking
- Activity logging
- Data integrity enforcement

## API Endpoints

### Authentication (`api/auth.php`)
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=register` - User registration
- `POST /api/auth.php?action=logout` - User logout
- `GET /api/auth.php?action=check` - Check auth status

### Posts (`api/posts.php`)
- `GET /api/posts.php?action=list` - Get posts with filtering
- `GET /api/posts.php?action=categories` - Get categories
- `POST /api/posts.php?action=create` - Create new post
- `POST /api/posts.php?action=vote` - Vote on post
- `PUT /api/posts.php?action=update` - Update post
- `DELETE /api/posts.php?action=delete` - Delete post

### Comments (`api/comments.php`)
- `GET /api/comments.php?action=list` - Get post comments
- `POST /api/comments.php?action=create` - Add comment
- `PUT /api/comments.php?action=update` - Update comment
- `DELETE /api/comments.php?action=delete` - Delete comment

### Surveys (`api/surveys.php`)
- `GET /api/surveys.php?action=list` - Get active surveys
- `GET /api/surveys.php?action=questions` - Get survey questions
- `POST /api/surveys.php?action=create` - Create survey (admin only)
- `POST /api/surveys.php?action=respond` - Submit survey response

### Analytics (`api/analytics.php`)
- `GET /api/analytics.php?action=dashboard` - Dashboard analytics (admin only)
- `GET /api/analytics.php?action=trends` - Trend analysis (admin only)

## Security Features

- Password hashing with PHP's `password_hash()`
- SQL injection prevention via prepared statements
- XSS protection through input sanitization
- CSRF protection via session validation
- Role-based access control
- Secure session management
- Remember me functionality with secure tokens

## Usage Guide

### For Students
1. **Registration**: Sign up with DLSU email address
2. **Login**: Access your account with email/password
3. **Post Feedback**: Share feedback or suggestions with categories
4. **Vote & Comment**: Engage with community posts
5. **Take Surveys**: Participate in anonymous surveys
6. **Search**: Find relevant posts using search and filters

### For CSG Officers/Admins
1. **Dashboard Access**: View comprehensive analytics
2. **Survey Creation**: Create surveys with multiple question types
3. **Content Moderation**: Monitor and manage posts
4. **Analytics Review**: Track engagement and trends
5. **User Management**: Oversee user activities

## Customization

### Adding New Categories
\`\`\`sql
INSERT INTO categories (name, description, color) 
VALUES ('New Category', 'Description', '#hexcolor');
\`\`\`

### Modifying User Roles
Edit the `role` enum in the `users` table:
\`\`\`sql
ALTER TABLE users MODIFY COLUMN role ENUM('student', 'admin', 'csg_officer', 'new_role');
\`\`\`

### Custom Styling
Modify `css/style.css` to customize the appearance:
- Update CSS variables in `:root`
- Modify component styles
- Add custom animations

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Permission Denied Errors**
   - Check file permissions (755 for directories, 644 for files)
   - Ensure web server has read access

3. **JavaScript Errors**
   - Check browser console for errors
   - Ensure all script files are loaded
   - Verify API endpoints are accessible

4. **Login Issues**
   - Clear browser cache and cookies
   - Check session configuration
   - Verify user exists in database

### Debug Mode
Enable error reporting in PHP:
\`\`\`php
error_reporting(E_ALL);
ini_set('display_errors', 1);
\`\`\`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is developed for educational purposes as part of ITISDEV: Business Applications Development at De La Salle University.

## Support

For technical support or questions:
- Check the troubleshooting section
- Review the API documentation
- Contact the development team

---

**SEFS - Empowering Student Voices Through Technology**
