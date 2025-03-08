<?php
// Start the session
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    // Return empty JSON array
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get course_id from query parameter
if (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
    $course_id = (int)$_GET['course_id'];
    
    // Verify that the teacher is assigned to this course
    $teacher_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM teacher_courses 
        WHERE teacher_id = ? AND course_id = ?
    ");
        $stmt->bind_param("ii", $teacher_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_assigned = $result->fetch_assoc()['count'] > 0;
        $stmt->close();
        
        if ($is_assigned) {
            // Query for modules
            $stmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY module_order");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Return modules as JSON
            header('Content-Type: application/json');
            echo json_encode($modules);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You are not authorized to view this course']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid course ID']);
    }
    
    $conn->close();
    ?>