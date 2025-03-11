<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
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

// Handle delete requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_course'])) {
        $courseId = (int)$_POST['delete_course'];
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $courseId);
        
        if ($stmt->execute()) {
            $success_message = "Course has been deleted successfully.";
        } else {
            $error_message = "Failed to delete course: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_module'])) {
        $moduleId = (int)$_POST['delete_module'];
        $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->bind_param("i", $moduleId);
        
        if ($stmt->execute()) {
            $success_message = "Module has been deleted successfully.";
        } else {
            $error_message = "Failed to delete module: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get all courses with assigned teachers
$courseQuery = "
    SELECT c.id, c.title, c.course_code, c.description, c.credit_hours, 
           t.id as teacher_id, t.full_name as teacher_name
    FROM courses c
    LEFT JOIN teacher_courses tc ON c.id = tc.course_id
    LEFT JOIN teachers t ON tc.teacher_id = t.id
    ORDER BY c.title
";
$coursesResult = $conn->query($courseQuery);

// Function to get modules for a specific course
function getModulesForCourse($conn, $courseId) {
    $moduleQuery = "
        SELECT m.id, m.title, m.description, 
               t.id as teacher_id, t.full_name as teacher_name
        FROM modules m
        LEFT JOIN teacher_modules tm ON m.id = tm.module_id
        LEFT JOIN teachers t ON tm.teacher_id = t.id
        WHERE m.course_id = ?
        ORDER BY m.title
    ";
    
    $stmt = $conn->prepare($moduleQuery);
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $modules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $modules;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses and Modules - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
            padding-top: 70px;
            background-color: #4e73df;
            color: white;
        }
        .content {
            margin-left: 220px;
            padding: 20px;
            padding-top: 80px;
        }
        .card {
            margin-bottom: 20px;
        }
        .module-row {
            background-color: #f8f9fc;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e8f0fe;
            color: #4e73df;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">LMS Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">

                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_courses.php">
                    <i class="fas fa-book"></i> Manage Courses
                </a>
                <li class="nav-item">
                <a class="nav-link text-white active" href="manage_students.php">
                    <i class="fas fa-user-graduate"></i> Manage Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white active" href="manage_teachers.php">
                    <i class="fas fa-user-graduate"></i> Manage Teachers
                </a>
            </li>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Manage Courses and Modules</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Courses and Their Modules</h6>
                    <div>
                        <a href="dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add New Course
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="accordion" id="courseAccordion">
                        <?php 
                        if ($coursesResult->num_rows > 0): 
                            $courseCounter = 0;
                            while($course = $coursesResult->fetch_assoc()): 
                                $courseCounter++;
                                $modules = getModulesForCourse($conn, $course['id']);
                        ?>
                            <div class="accordion-item mb-3 border">
                                <h2 class="accordion-header" id="heading<?php echo $course['id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $course['id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $course['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong><?php echo htmlspecialchars($course['title']); ?></strong> 
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                            </div>
                                            <div>
                                                <span class="badge bg-info me-2">
                                                    <i class="fas fa-chalkboard-teacher me-1"></i>
                                                    <?php echo htmlspecialchars($course['teacher_name'] ?? 'No Teacher Assigned'); ?>
                                                </span>
                                                <span class="badge bg-primary me-2">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $course['credit_hours']; ?> Credits
                                                </span>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-cubes me-1"></i>
                                                    <?php echo count($modules); ?> Modules
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $course['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $course['id']; ?>" data-bs-parent="#courseAccordion">
                                    <div class="accordion-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <div>
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description']); ?></p>
                                            </div>
                                            <div>
                                                <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-warning btn-sm me-2">
                                                    <i class="fas fa-edit"></i> Edit Course
                                                </a>
    
                                            </div>
                                        </div>
                                        
                                        <h6 class="font-weight-bold">Modules</h6>
                                        <div class="d-flex justify-content-end mb-2">
                                            <a href="dashboard.php#module" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Add Module
                                            </a>
                                        </div>
                                        
                                        <?php if (count($modules) > 0): ?>
                                            <table class="table table-bordered table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Module Title</th>
                                                        <th>Description</th>
                                                        <th>Assigned Teacher</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($modules as $module): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($module['title']); ?></td>
                                                            <td><?php echo htmlspecialchars($module['description']); ?></td>
                                                            <td><?php echo htmlspecialchars($module['teacher_name'] ?? 'No Teacher Assigned'); ?></td>
                                                            <td>
                                                                <a href="edit_module.php?id=<?php echo $module['id']; ?>" class="btn btn-warning btn-sm me-1">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>
                                                                
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                No modules have been created for this course yet.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                            <div class="alert alert-info">
                                No courses have been created yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>