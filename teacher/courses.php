<?php
session_start();
require_once '../includes/config.php';

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Fetch courses created by the teacher
$query = "
    SELECT 
        c.id,
        c.title,
        c.description,
        c.course_code,
        (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
        (SELECT COUNT(*) FROM assignments a 
         JOIN modules m ON a.module_id = m.id 
         WHERE m.course_id = c.id) as assignment_count
    FROM courses c
    WHERE c.created_by = ?
    ORDER BY c.title ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
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
    <title>My Courses - Teacher Dashboard</title>
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
    <?php include '../includes/teacher_navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Courses</h2>
            <a href="create_course.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Course
            </a>
        </div>

        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-books fa-3x text-muted mb-3"></i>
                <h4>No Courses Found</h4>
                <p class="text-muted">You haven't created any courses yet.</p>
                <a href="create_course.php" class="btn btn-primary">Create Your First Course</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($courses as $course): ?>
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
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Course
                                    </a>
                                    <a href="edit_course.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        Edit Course
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