<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
           e.enrollment_date
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE c.id = ? AND e.student_id = ?
");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect if not enrolled
if (!$course) {
    $_SESSION['error'] = "You are not enrolled in this course.";
    header("Location: available_courses.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($course['title']); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($course['description']); ?></p>
        
        <div class="mb-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Course content will go here -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>