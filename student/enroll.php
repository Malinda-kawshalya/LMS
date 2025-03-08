<?php
session_start();
require_once '../includes/config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Redirect if no course_id provided
if ($course_id <= 0) {
    $_SESSION['message'] = ["type" => "danger", "text" => "Invalid course selected"];
    header("Location: available_courses.php");
    exit();
}

try {
    // Check if course exists and is active
    $course_query = "SELECT id, title FROM courses WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($course_query);
    
    if (!$stmt) {
        throw new Exception("Error preparing query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Course not found or not available");
    }
    
    $course = $result->fetch_assoc();
    $stmt->close();
    
    // Check if already enrolled
    $check_query = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
    $stmt = $conn->prepare($check_query);
    
    if (!$stmt) {
        throw new Exception("Error checking enrollment: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = ["type" => "warning", "text" => "You are already enrolled in this course"];
        header("Location: available_courses.php");
        exit();
    }
    
    $stmt->close();
    
    // Check the structure of your enrollments table
    // This query adapts to your table structure by removing the status field
    $enroll_query = "INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($enroll_query);
    
    if (!$stmt) {
        throw new Exception("Error preparing enrollment query: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $student_id, $course_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = ["type" => "success", "text" => "Successfully enrolled in " . htmlspecialchars($course['title'])];
    } else {
        throw new Exception("Failed to enroll in the course");
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in enroll.php: " . $e->getMessage());
    $_SESSION['message'] = ["type" => "danger", "text" => "Error: " . $e->getMessage()];
}

// Redirect back to available courses page
header("Location: available_courses.php");
exit();
?>