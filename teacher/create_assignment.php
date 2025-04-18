<?php
// Start the session
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    // Redirect to login page
    header("Location: ../index.php");
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
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$success = false;
$error = '';
$assignment_id = 0;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $batch_id = isset($_POST['batch_id']) ? $_POST['batch_id'] : ''; // batch might be a string
    $program_id = isset($_POST['program_id']) ? $_POST['program_id'] : ''; // program might be a string
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
    $max_score = isset($_POST['max_score']) ? intval($_POST['max_score']) : 100;
    $teacher_id = $_SESSION['user_id'];
    
    // Validate input
    if (empty($course_id) || empty($module_id) || empty($batch_id) || empty($program_id) || empty($title) || empty($due_date)) {
        $error = "All fields are required.";
    } else {
        // Insert assignment into database
        $stmt = $conn->prepare("INSERT INTO assignments (title, description, module_id, due_date, max_score, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissi", $title, $description, $module_id, $due_date, $max_score, $teacher_id);
        
        if ($stmt->execute()) {
            $assignment_id = $stmt->insert_id;
            $success = true;
            
            // Determine which students should receive the assignment
            $student_query = "
                SELECT s.id
                FROM students s
                JOIN enrollments e ON s.id = e.student_id
                WHERE e.course_id = ? AND s.batch = ? AND s.program = ?
            ";
            $student_stmt = $conn->prepare($student_query);
            $student_stmt->bind_param("iss", $course_id, $batch_id, $program_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            
            // Store assignment recipients
            $assign_stmt = $conn->prepare("
                INSERT INTO assignment_recipients (assignment_id, student_id) 
                VALUES (?, ?)
            ");
            
            while ($student = $student_result->fetch_assoc()) {
                $assign_stmt->bind_param("ii", $assignment_id, $student['id']);
                $assign_stmt->execute();
            }
            
            
            $student_stmt->close();
            
            // Set success message
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'Assignment created successfully!'
            ];
        } else {
            $error = "Error creating assignment: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get teacher information for navigation bar
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get courses for the breadcrumb menu
if ($success && $assignment_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.title as course_title, m.title as module_title 
        FROM courses c
        JOIN modules m ON c.id = m.course_id
        JOIN assignments a ON m.id = a.module_id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $course_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Assignment Created' : 'Create Assignment'; ?> - LMS</title>
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
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        .sidebar a:hover {
            color: white;
        }
        .content {
            margin-left: 220px;
            padding: 20px;
            padding-top: 80px;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand ps-3" href="#">LMS - Teacher</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user fa-fw"></i> <?php echo htmlspecialchars($teacher['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block d-none">
        <div class="position-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="courses.php">
                        <i class="fas fa-book"></i> Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="grades.php">
                        <i class="fas fa-chart-bar"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="chatpage.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="assignments.php">Assignments</a></li>
                            <?php if ($success): ?>
                                <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $course_id; ?>"><?php echo htmlspecialchars($course_info['course_title']); ?></a></li>
                                <li class="breadcrumb-item active">Assignment Created</li>
                            <?php else: ?>
                                <li class="breadcrumb-item active">Create Assignment</li>
                            <?php endif; ?>
                        </ol>
                    </nav>
                    
                    <?php if ($success): ?>
                        <!-- Success Message -->
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Assignment Created Successfully!</h4>
                            <p>Your assignment <strong><?php echo htmlspecialchars($title); ?></strong> has been created and assigned to students in the selected batch and program.</p>
                            <hr>
                            <p class="mb-0">
                                <a href="dashboard.php" class="btn btn-primary me-2">
                                    <i class="fas fa-home"></i> Return to Dashboard
                                </a>
                                <a href="create_assignment.php" class="btn btn-outline-primary">
                                    <i class="fas fa-plus"></i> Create Another Assignment
                                </a>
                            </p>
                        </div>
                        
                        <!-- Assignment Details Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Assignment Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Assignment Information</h6>
                                        <p><strong>Title:</strong> <?php echo htmlspecialchars($title); ?></p>
                                        <p><strong>Course:</strong> <?php echo htmlspecialchars($course_info['course_title']); ?></p>
                                        <p><strong>Module:</strong> <?php echo htmlspecialchars($course_info['module_title']); ?></p>
                                        <p><strong>Due Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($due_date)); ?></p>
                                        <p><strong>Maximum Score:</strong> <?php echo $max_score; ?> points</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Assigned To</h6>
                                        <p><strong>Batch:</strong> <?php echo htmlspecialchars($batch_id); ?></p>
                                        <p><strong>Program:</strong> <?php echo htmlspecialchars($program_id); ?></p>
                                        
                                        <?php
                                        // Get count of students who received the assignment
                                        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignment_recipients WHERE assignment_id = ?");
                                        $count_stmt->bind_param("i", $assignment_id);
                                        $count_stmt->execute();
                                        $student_count = $count_stmt->get_result()->fetch_assoc()['count'];
                                        $count_stmt->close();
                                        ?>
                                        <p><strong>Number of Students:</strong> <?php echo $student_count; ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h6 class="fw-bold">Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($description)); ?></p>
                                </div>
                            </div>
                        </div>
                    
                    <?php elseif (!empty($error)): ?>
                        <!-- Error Message -->
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Error</h4>
                            <p><?php echo $error; ?></p>
                            <hr>
                            <p class="mb-0">
                                <a href="dashboard.php" class="btn btn-danger me-2">
                                    <i class="fas fa-home"></i> Return to Dashboard
                                </a>
                                <button class="btn btn-outline-danger" onclick="history.back()">
                                    <i class="fas fa-arrow-left"></i> Go Back and Try Again
                                </button>
                            </p>
                        </div>
                    
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i> You've been redirected to the dashboard. Please use the Create Assignment form there.
                        </div>
                        <div class="text-center mt-4">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Return to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>