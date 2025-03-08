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

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get courses assigned to the teacher
$stmt = $conn->prepare("
    SELECT c.* 
    FROM courses c
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// AJAX handler for fetching modules, batches, and programs
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_modules' && isset($_GET['course_id'])) {
        $course_id = intval($_GET['course_id']);
        $stmt = $conn->prepare("SELECT id, title FROM modules WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $modules = [];
        while ($row = $result->fetch_assoc()) {
            $modules[] = $row;
        }
        
        echo json_encode($modules);
        $stmt->close();
        $conn->close();
        exit();
    }
    
    if ($_GET['action'] === 'get_batches' && isset($_GET['course_id'])) {
        $course_id = intval($_GET['course_id']);
        
        // Get distinct batches from students enrolled in this course
        $stmt = $conn->prepare("
            SELECT DISTINCT s.batch AS id, s.batch AS name
            FROM students s
            JOIN enrollments e ON s.id = e.student_id
            WHERE e.course_id = ?
            ORDER BY s.batch
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $batches = [];
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }
        
        echo json_encode($batches);
        $stmt->close();
        $conn->close();
        exit();
    }
    
    if ($_GET['action'] === 'get_programs' && isset($_GET['batch_id'])) {
        $batch_id = $_GET['batch_id']; // Note: This might be a string now, not an int
        
        // Get distinct programs from students in this batch
        $stmt = $conn->prepare("
            SELECT DISTINCT s.program AS id, s.program AS name
            FROM students s
            WHERE s.batch = ?
            ORDER BY s.program
        ");
        $stmt->bind_param("s", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $programs = [];
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
        
        echo json_encode($programs);
        $stmt->close();
        $conn->close();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - LMS</title>
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
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
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
                    <h1 class="h3 mb-4">Teacher Dashboard</h1>
                    
                    <!-- Overview Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="h5 mb-0 font-weight-bold">
                                                <?php echo count($courses); ?> Courses
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="h5 mb-0 font-weight-bold">
                                                <?php 
                                                    // Get assignment count
                                                    $stmt = $conn->prepare("
                                                        SELECT COUNT(*) as count
                                                        FROM assignments
                                                        WHERE created_by = ?
                                                    ");
                                                    $stmt->bind_param("i", $teacher_id);
                                                    $stmt->execute();
                                                    $assignment_count = $stmt->get_result()->fetch_assoc()['count'];
                                                    $stmt->close();
                                                    echo $assignment_count;
                                                ?> Assignments
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-tasks fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="h5 mb-0 font-weight-bold">
                                                <?php 
                                                    // Get student count
                                                    $stmt = $conn->prepare("
                                                        SELECT COUNT(DISTINCT s.id) as count
                                                        FROM students s
                                                        JOIN enrollments e ON s.id = e.student_id
                                                        JOIN teacher_courses tc ON e.course_id = tc.course_id
                                                        WHERE tc.teacher_id = ?
                                                    ");
                                                    $stmt->bind_param("i", $teacher_id);
                                                    $stmt->execute();
                                                    $student_count = $stmt->get_result()->fetch_assoc()['count'];
                                                    $stmt->close();
                                                    echo $student_count;
                                                ?> Students
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-graduate fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="h5 mb-0 font-weight-bold">
                                                <?php 
                                                    // Get pending submissions count
                                                    $stmt = $conn->prepare("
                                                        SELECT COUNT(*) as count
                                                        FROM submissions s
                                                        JOIN assignments a ON s.assignment_id = a.id
                                                        WHERE a.created_by = ? AND s.score IS NULL
                                                    ");
                                                    $stmt->bind_param("i", $teacher_id);
                                                    $stmt->execute();
                                                    $pending_count = $stmt->get_result()->fetch_assoc()['count'];
                                                    $stmt->close();
                                                    echo $pending_count;
                                                ?> Pending
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <!-- Courses Section -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">My Courses</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($courses) > 0): ?>
                                        <div class="list-group">
                                            <?php foreach ($courses as $course): ?>
                                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h5 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                        <small><?php echo htmlspecialchars($course['course_code']); ?></small>
                                                    </div>
                                                    <p class="mb-1"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No courses assigned yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Create Assignment Section -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Create New Assignment</h5>
                                </div>
                                <div class="card-body">
                                    <form action="create_assignment.php" method="post">
                                        <div class="mb-3">
                                            <label for="course" class="form-label">Select Course</label>
                                            <select class="form-select" id="course" name="course_id" required>
                                                <option value="">-- Select Course --</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="module" class="form-label">Select Module</label>
                                            <select class="form-select" id="module" name="module_id" required>
                                                <option value="">-- First Select a Course --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="batch" class="form-label">Select Batch</label>
                                            <select class="form-select" id="batch" name="batch_id" required>
                                                <option value="">-- First Select a Course --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="program" class="form-label">Select Program</label>
                                            <select class="form-select" id="program" name="program_id" required>
                                                <option value="">-- First Select a Batch --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Assignment Title</label>
                                            <input type="text" class="form-control" id="title" name="title" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Assignment Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="max_score" class="form-label">Maximum Score</label>
                                            <input type="number" class="form-control" id="max_score" name="max_score" value="100" required>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Create Assignment</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script to load modules based on course selection
        document.getElementById('course').addEventListener('change', function() {
            const courseId = this.value;
            const moduleSelect = document.getElementById('module');
            const batchSelect = document.getElementById('batch');
            
            // Clear existing options
            moduleSelect.innerHTML = '<option value="">-- Loading Modules --</option>';
            batchSelect.innerHTML = '<option value="">-- First Select a Course --</option>';
            document.getElementById('program').innerHTML = '<option value="">-- First Select a Batch --</option>';
            
            if (courseId !== '') {
                // Fetch modules for the selected course using AJAX
                fetch(`dashboard.php?action=get_modules&course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        moduleSelect.innerHTML = '';
                        
                        if (data.length === 0) {
                            moduleSelect.innerHTML = '<option value="">No modules available</option>';
                        } else {
                            moduleSelect.innerHTML = '<option value="">-- Select Module --</option>';
                            data.forEach(module => {
                                const option = document.createElement('option');
                                option.value = module.id;
                                option.textContent = module.title;
                                moduleSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching modules:', error);
                        moduleSelect.innerHTML = '<option value="">Error loading modules</option>';
                    });
                
                // Fetch batches for the selected course using AJAX
                fetch(`dashboard.php?action=get_batches&course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        batchSelect.innerHTML = '';
                        
                        if (data.length === 0) {
                            batchSelect.innerHTML = '<option value="">No batches available</option>';
                        } else {
                            batchSelect.innerHTML = '<option value="">-- Select Batch --</option>';
                            data.forEach(batch => {
                                const option = document.createElement('option');
                                option.value = batch.id;
                                option.textContent = batch.name;
                                batchSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching batches:', error);
                        batchSelect.innerHTML = '<option value="">Error loading batches</option>';
                    });
            } else {
                moduleSelect.innerHTML = '<option value="">-- First Select a Course --</option>';
                batchSelect.innerHTML = '<option value="">-- First Select a Course --</option>';
                document.getElementById('program').innerHTML = '<option value="">-- First Select a Batch --</option>';
            }
        });
        
        // Script to load programs based on batch selection
        document.getElementById('batch').addEventListener('change', function() {
            const batchId = this.value;
            const programSelect = document.getElementById('program');
            
            // Clear existing options
            programSelect.innerHTML = '<option value="">-- Loading Programs --</option>';
            
            if (batchId !== '') {
                // Fetch programs for the selected batch using AJAX
                fetch(`dashboard.php?action=get_programs&batch_id=${batchId}`)
                    .then(response => response.json())
                    .then(data => {
                        programSelect.innerHTML = '';
                        
                        if (data.length === 0) {
                            programSelect.innerHTML = '<option value="">No programs available</option>';
                        } else {
                            programSelect.innerHTML = '<option value="">-- Select Program --</option>';
                            data.forEach(program => {
                                const option = document.createElement('option');
                                option.value = program.id;
                                option.textContent = program.name;
                                programSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching programs:', error);
                        programSelect.innerHTML = '<option value="">Error loading programs</option>';
                    });
            } else {
                programSelect.innerHTML = '<option value="">-- First Select a Batch --</option>';
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>