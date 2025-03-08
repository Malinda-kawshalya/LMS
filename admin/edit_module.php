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

// Initialize variables
$moduleId = 0;
$moduleTitle = "";
$moduleDescription = "";
$courseName = "";
$courseId = "";
$assigned_teacher = "";
$error_message = "";
$success_message = "";

// Get all courses for dropdown
$coursesQuery = "SELECT id, title FROM courses ORDER BY title";
$coursesResult = $conn->query($coursesQuery);
$courses = [];
if ($coursesResult->num_rows > 0) {
    while ($course = $coursesResult->fetch_assoc()) {
        $courses[] = $course;
    }
}

// Get all teachers for dropdown
$teachersQuery = "SELECT id, full_name FROM teachers ORDER BY full_name";
$teachersResult = $conn->query($teachersQuery);
$teachers = [];
if ($teachersResult->num_rows > 0) {
    while ($teacher = $teachersResult->fetch_assoc()) {
        $teachers[] = $teacher;
    }
}

// Check if module ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $moduleId = (int)$_GET['id'];
    
    // Get module details
    $stmt = $conn->prepare("
        SELECT m.id, m.title, m.description, m.course_id, c.title as course_name, tm.teacher_id
        FROM modules m
        LEFT JOIN courses c ON m.course_id = c.id
        LEFT JOIN teacher_modules tm ON m.id = tm.module_id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $module = $result->fetch_assoc();
        $moduleTitle = $module['title'];
        $moduleDescription = $module['description'];
        $courseId = $module['course_id'];
        $courseName = $module['course_name'];
        $assigned_teacher = $module['teacher_id'];
    } else {
        $error_message = "Module not found.";
    }
    $stmt->close();
} else {
    header("Location: manage_modules.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $moduleTitle = trim($_POST['title']);
    $moduleDescription = trim($_POST['description']);
    $courseId = (int)$_POST['course_id'];
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    
    // Validation
    if (empty($moduleTitle) || $courseId <= 0) {
        $error_message = "Module title and course selection are required.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update module data
            $updateModule = $conn->prepare("
                UPDATE modules 
                SET title = ?, description = ?, course_id = ? 
                WHERE id = ?
            ");
            // Fixed binding parameters - changed from "ssi" to "ssii"
            $updateModule->bind_param("ssii", $moduleTitle, $moduleDescription, $courseId, $moduleId);
            $updateModule->execute();
            
            // Update teacher assignment
            // First delete existing assignment
            $deleteTeacher = $conn->prepare("DELETE FROM teacher_modules WHERE module_id = ?");
            $deleteTeacher->bind_param("i", $moduleId);
            $deleteTeacher->execute();
            
            // Then insert new assignment if a teacher is selected
            if ($teacher_id) {
                $assignTeacher = $conn->prepare("INSERT INTO teacher_modules (teacher_id, module_id) VALUES (?, ?)");
                $assignTeacher->bind_param("ii", $teacher_id, $moduleId);
                $assignTeacher->execute();
            }
            
            // Commit transaction
            $conn->commit();
            $success_message = "Module updated successfully.";
            
            // Update course name for display
            $courseStmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
            $courseStmt->bind_param("i", $courseId);
            $courseStmt->execute();
            $courseResult = $courseStmt->get_result();
            if ($courseResult->num_rows > 0) {
                $courseData = $courseResult->fetch_assoc();
                $courseName = $courseData['title'];
            }
            $courseStmt->close();
            
            // Refresh teacher assignment
            $assigned_teacher = $teacher_id;
            
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            $error_message = "Error updating module: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Module - LMS</title>
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
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
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
            </li>
            <li class="nav-item">
                <a class="nav-link text-white active" href="manage_modules.php">
                    <i class="fas fa-cubes"></i> Manage Modules
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_students.php">
                    <i class="fas fa-user-graduate"></i> Manage Students
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Edit Module</h1>
                <a href="manage_courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to courses
                </a>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Module Details</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Module Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($moduleTitle); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo ($course['id'] == $courseId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($moduleDescription); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Assigned Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher['id'] == $assigned_teacher) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Module
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<?php $conn->close(); ?>