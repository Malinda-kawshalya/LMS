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

// Get teacher's assignments with related data
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_title, c.course_code, m.title as module_title,
           (SELECT COUNT(*) FROM assignment_recipients WHERE assignment_id = a.id) as student_count,
           (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND score IS NOT NULL) as graded_count
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE a.created_by = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Filter assignments by status if requested
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filtered_assignments = [];

foreach ($assignments as $assignment) {
    $due_date = strtotime($assignment['due_date']);
    $current_time = time();
    
    // Add status field to each assignment
    if ($due_date > $current_time) {
        $assignment['status'] = 'active';
        $assignment['status_text'] = 'Active';
        $assignment['status_class'] = 'success';
    } else {
        $assignment['status'] = 'past';
        $assignment['status_text'] = 'Past Due';
        $assignment['status_class'] = 'secondary';
    }
    
    // Apply filter
    if ($filter === 'all' || $filter === $assignment['status']) {
        $filtered_assignments[] = $assignment;
    }
}

// Count assignments by status
$active_count = 0;
$past_count = 0;
$filtered_assignments = [];

// Process each assignment
foreach ($assignments as $assignment) {
    // Convert dates to timestamps for comparison
    $due_date = strtotime($assignment['due_date']);
    $current_time = time();
    
    // Initialize assignment with status
    $assignment = array_merge($assignment, [
        'status' => $due_date > $current_time ? 'active' : 'past',
        'status_text' => $due_date > $current_time ? 'Active' : 'Past Due',
        'status_class' => $due_date > $current_time ? 'success' : 'secondary'
    ]);
    
    // Update counters
    if ($assignment['status'] === 'active') {
        $active_count++;
    } else {
        $past_count++;
    }
    
    // Apply filter
    if ($filter === 'all' || $filter === $assignment['status']) {
        $filtered_assignments[] = $assignment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Teacher Dashboard - LMS</title>
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
        .assignment-card {
            transition: transform 0.2s;
        }
        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .badge-corner {
            position: absolute;
            top: -8px;
            right: -8px;
            padding: 5px 10px;
            border-radius: 50%;
            font-size: 0.8rem;
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
                    <a class="nav-link active" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3">Assignments</h1>
                <a href="dashboard.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Create New Assignment
                </a>
            </div>
            
            <!-- Status Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="assignments.php?filter=all">
                        All Assignments <span class="badge bg-primary"><?php echo count($assignments); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'active' ? 'active' : ''; ?>" href="assignments.php?filter=active">
                        Active <span class="badge bg-success"><?php echo $active_count; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'past' ? 'active' : ''; ?>" href="assignments.php?filter=past">
                        Past Due <span class="badge bg-secondary"><?php echo $past_count; ?></span>
                    </a>
                </li>
            </ul>
            
            <!-- Assignments -->
            <div class="row">
                <?php if (empty($filtered_assignments)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> No assignments found</h5>
                            <p>
                                <?php if ($filter !== 'all'): ?>
                                    There are no <?php echo $filter; ?> assignments. <a href="assignments.php">View all assignments</a> or create a new one.
                                <?php else: ?>
                                    You haven't created any assignments yet. Use the "Create New Assignment" button to get started.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($filtered_assignments as $assignment): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card assignment-card h-100 position-relative">
                                <div class="badge bg-<?php echo $assignment['status_class']; ?> badge-corner">
                                    <?php echo $assignment['status_text']; ?>
                                </div>
                                <div class="card-header">
                                    <h5 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                    <h6 class="card-subtitle text-muted"><?php echo htmlspecialchars($assignment['course_title']); ?> (<?php echo htmlspecialchars($assignment['course_code']); ?>)</h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        <strong>Module:</strong> <?php echo htmlspecialchars($assignment['module_title']); ?>
                                    </p>
                                    <p class="card-text">
                                        <strong>Due Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?>
                                    </p>
                                    <div class="d-flex justify-content-between mt-3">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-users"></i> <?php echo $assignment['student_count']; ?> Students
                                        </span>
                                        <span class="badge bg-info">
                                            <i class="fas fa-file-alt"></i> <?php echo $assignment['submission_count']; ?> Submissions
                                        </span>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> <?php echo $assignment['graded_count']; ?> Graded
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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