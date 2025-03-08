<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch enrolled courses with progress
$query = "
    SELECT 
        c.id,
        c.title,
        c.description,
        c.course_code,
        (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
        (SELECT COUNT(*) FROM assignments a 
         JOIN modules m ON a.module_id = m.id 
         WHERE m.course_id = c.id) as assignment_count,
        (SELECT COUNT(*) FROM submissions s 
         JOIN assignments a ON s.assignment_id = a.id 
         JOIN modules m ON a.module_id = m.id 
         WHERE m.course_id = c.id AND s.student_id = ?) as completed_assignments
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY c.title ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Learning Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .course-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 8px;
            margin: 10px 0;
        }
        .course-stats {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Courses</h2>
            <a href="available_courses.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Browse More Courses
            </a>
        </div>

        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-books fa-3x text-muted mb-3"></i>
                <h4>No Courses Found</h4>
                <p class="text-muted">You haven't enrolled in any courses yet.</p>
                <a href="available_courses.php" class="btn btn-primary">Browse Available Courses</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($courses as $course): ?>
                    <?php 
                    $progress = $course['assignment_count'] > 0 
                        ? round(($course['completed_assignments'] / $course['assignment_count']) * 100) 
                        : 0;
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card course-card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </a>
                                </h5>
                                <p class="card-text text-muted small mb-2">
                                    <?php echo htmlspecialchars($course['course_code']); ?>
                                </p>
                                <div class="course-stats mb-3">
                                    <i class="fas fa-book-reader"></i> <?php echo $course['module_count']; ?> Modules
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-tasks"></i> <?php echo $course['assignment_count']; ?> Assignments
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" 
                                         role="progressbar" 
                                         style="width: <?php echo $progress; ?>%" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <?php echo $progress; ?>% Complete
                                    </small>
                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Continue Learning
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>