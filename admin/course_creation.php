<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$title = $_POST['title'];
$course_code = $_POST['course_code'];
$description = $_POST['description'];
$teacher_id = $_POST['teacher_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Insert course
    $stmt = $conn->prepare("INSERT INTO courses (title, course_code, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $course_code, $description);
    $stmt->execute();
    $course_id = $conn->insert_id;
    $stmt->close();

    // Assign teacher to course
    $stmt = $conn->prepare("INSERT INTO teacher_courses (teacher_id, course_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $teacher_id, $course_id);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    $_SESSION['success_message'] = "Course created successfully!";
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error creating course: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

$conn->close();
?>