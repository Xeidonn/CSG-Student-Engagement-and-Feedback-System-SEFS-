<?php
class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '12345*';
    private $db_name = 'sefs_db';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Create connection
            $this->conn = mysqli_connect($this->host, $this->username, $this->password, $this->db_name);
            
            // Check connection
            if (!$this->conn) {
                die("Connection failed: " . mysqli_connect_error());
            }
            
            // Set charset to utf8
            mysqli_set_charset($this->conn, "utf8");
            
        } catch(Exception $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
}

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'csg_officer');
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirectTo($url) {
    header("Location: $url");
    exit();
}

// Improved MySQLi helper functions
function executeQuery($conn, $query, $params = []) {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    
    if ($params) {
        $types = '';
        $values = [];
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_null($param)) {
                $types .= 's';
                $param = null;
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }
    
    return $stmt;
}

function fetchAssoc($stmt) {
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        return null;
    }
    return mysqli_fetch_assoc($result);
}

function fetchAllAssoc($stmt) {
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        return [];
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Special function for stored procedures that handles multiple result sets
function executeStoredProcedure($conn, $procedureCall, $params = []) {
    $stmt = mysqli_prepare($conn, $procedureCall);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    
    if ($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    
    // Clean up any remaining results
    mysqli_stmt_close($stmt);
    while (mysqli_next_result($conn)) {
        if ($res = mysqli_store_result($conn)) {
            mysqli_free_result($res);
        }
    }
    
    return $data;
}
?>
