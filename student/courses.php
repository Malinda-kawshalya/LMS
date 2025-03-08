<?php
session_start();

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
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

$student_id = $_SESSION['user_id'];

// Fetch enrolled courses
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
           (SELECT COUNT(*) FROM assignments a 
            JOIN modules m ON a.module_id = m.id 
            WHERE m.course_id = c.id) as assignment_count
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY c.title ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result();
$stmt->close();

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === 'true';

// If it's an AJAX request, only show the main content
if ($is_ajax) {
    include('courses_content.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content { margin-left: 220px; padding: 20px; padding-top: 80px; }
        .course-card { transition: transform 0.3s; }
        .course-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <?php include 'courses_content.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>