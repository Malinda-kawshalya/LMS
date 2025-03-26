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

// Check if grading form is submitted
if (isset($_POST['submit_grades'])) {
    $assignment_id = $_POST['assignment_id'];
    $submission_id = $_POST['submission_id'];
    $letter_grade = $_POST['letter_grade'];
    $score = $_POST['score'];
    $feedback = $_POST['feedback'];
    
    // Update the submission with grade
    $stmt = $conn->prepare("UPDATE submissions SET letter_grade = ?, score = ?, feedback = ?, graded_at = NOW() WHERE id = ?");
    $stmt->bind_param("sisi", $letter_grade, $score, $feedback, $submission_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: assignments.php?view=" . $assignment_id . "&graded=true");
    exit();
}

// Get assignment details if viewing a specific assignment
$view_assignment = false;
$submissions = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $assignment_id = $_GET['view'];
    $stmt = $conn->prepare("
        SELECT a.*, c.title as course_title, c.course_code, m.title as module_title
        FROM assignments a
        JOIN modules m ON a.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE a.id = ? AND a.created_by = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $view_assignment = $result->fetch_assoc();
        
        // Get submissions for this assignment
        $stmt = $conn->prepare("
            SELECT s.*, st.full_name as student_name, st.email as student_email
            FROM submissions s
            JOIN students st ON s.student_id = st.id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Get teacher's assignments with related data
    $stmt = $conn->prepare("
        SELECT a.*, c.title as course_title, c.course_code, m.title as module_title,
               (SELECT COUNT(*) FROM assignment_recipients WHERE assignment_id = a.id) as student_count,
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count,
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND letter_grade IS NOT NULL) as graded_count
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
        .grade-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .grade-A {
            background-color: #28a745;
        }
        .grade-B {
            background-color: #17a2b8;
        }
        .grade-C {
            background-color: #ffc107;
            color: #212529;
        }
        .grade-D {
            background-color: #fd7e14;
        }
        .grade-F {
            background-color: #dc3545;
        }
        .grade-NA {
            background-color: #6c757d;
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
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="calendar.php">
                        <i class="fas fa-calendar-alt"></i> Calendar
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
            <?php if ($view_assignment): ?>
                <!-- Assignment Details View -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3">
                        <a href="assignments.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>
                        </a>
                        Assignment: <?php echo htmlspecialchars($view_assignment['title']); ?>
                    </h1>
                </div>
                
                <?php if (isset($_GET['graded']) && $_GET['graded'] === 'true'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> The submission has been graded successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title">Assignment Details</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($view_assignment['course_title']); ?> (<?php echo htmlspecialchars($view_assignment['course_code']); ?>)</p>
                                <p><strong>Module:</strong> <?php echo htmlspecialchars($view_assignment['module_title']); ?></p>
                                <p><strong>Due Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($view_assignment['due_date'])); ?></p>
                                <p><strong>Instructions:</strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title">Submission Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="h1 mb-0"><?php echo count($submissions); ?></div>
                                        <div class="small text-muted">Total Submissions</div>
                                    </div>
                                    <div class="col-md-4">
                                        <?php
                                        $graded_count = 0;
                                        foreach ($submissions as $submission) {
                                            if (!empty($submission['letter_grade'])) {
                                                $graded_count++;
                                            }
                                        }
                                        ?>
                                        <div class="h1 mb-0"><?php echo $graded_count; ?></div>
                                        <div class="small text-muted">Graded</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="h1 mb-0"><?php echo count($submissions) - $graded_count; ?></div>
                                        <div class="small text-muted">Pending</div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h6>Grade Distribution</h6>
                                    <?php
                                    $grade_counts = [
                                        'A' => 0,
                                        'B' => 0,
                                        'C' => 0,
                                        'D' => 0,
                                        'F' => 0
                                    ];
                                    
                                    foreach ($submissions as $submission) {
                                        if (!empty($submission['letter_grade']) && isset($grade_counts[$submission['letter_grade']])) {
                                            $grade_counts[$submission['letter_grade']]++;
                                        }
                                    }
                                    
                                    foreach ($grade_counts as $grade => $count) {
                                        $percentage = count($submissions) > 0 ? ($count / count($submissions)) * 100 : 0;
                                        ?>
                                        <div class="mb-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?php echo $grade; ?></span>
                                                <span><?php echo $count; ?> (<?php echo round($percentage, 1); ?>%)</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar grade-<?php echo $grade; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Student Submissions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($submissions)): ?>
                            <div class="alert alert-info">
                                No submissions have been received for this assignment yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Submitted</th>
                                            <th>Letter Grade</th>
                                            <th>Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $submission): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($submission['student_name']); ?></div>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($submission['student_email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y, g:i a', strtotime($submission['submitted_at'])); ?>
                                                    <div class="text-muted small">
                                                        <?php
                                                        $submitted_date = new DateTime($submission['submitted_at']);
                                                        $due_date = new DateTime($view_assignment['due_date']);
                                                        if ($submitted_date > $due_date) {
                                                            $interval = $submitted_date->diff($due_date);
                                                            echo '<span class="text-danger">Late by ';
                                                            if ($interval->days > 0) {
                                                                echo $interval->days . ' day(s) ';
                                                            }
                                                            if ($interval->h > 0) {
                                                                echo $interval->h . ' hour(s) ';
                                                            }
                                                            if ($interval->i > 0) {
                                                                echo $interval->i . ' minute(s)';
                                                            }
                                                            echo '</span>';
                                                        } else {
                                                            echo '<span class="text-success">On time</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($submission['letter_grade'])): ?>
                                                        <div class="grade-circle grade-<?php echo $submission['letter_grade']; ?>">
                                                            <?php echo $submission['letter_grade']; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="grade-circle grade-NA">
                                                            N/A
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($submission['score'])): ?>
                                                        <?php echo $submission['score']; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <!-- Download Button -->
                                                        <a href="../uploads/submissions/<?php echo $submission['file_path']; ?>" class="btn btn-sm btn-outline-primary me-2" download>
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $submission['id']; ?>">
                                                            <i class="fas fa-edit"></i> Grade
                                                        </button>
                                                    </div>
                                                </td>
                                                
                                                <!-- Rest of the code remains the same as in the original snippet -->
                                                <!-- Grade Modal code follows... -->
                                            </tr>
                                            
                                            <!-- Grade Modal -->
                                            <div class="modal fade" id="gradeModal<?php echo $submission['id']; ?>" tabindex="-1" aria-labelledby="gradeModalLabel<?php echo $submission['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="gradeModalLabel<?php echo $submission['id']; ?>">Grade Submission</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form action="assignments.php?view=<?php echo $view_assignment['id']; ?>" method="post">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="assignment_id" value="<?php echo $view_assignment['id']; ?>">
                                                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="letterGrade<?php echo $submission['id']; ?>" class="form-label">Letter Grade</label>
                                                                    <select class="form-select" id="letterGrade<?php echo $submission['id']; ?>" name="letter_grade" required>
                                                                        <option value="">Select a grade</option>
                                                                        <option value="A" <?php echo $submission['letter_grade'] === 'A' ? 'selected' : ''; ?>>A</option>
                                                                        <option value="B" <?php echo $submission['letter_grade'] === 'B' ? 'selected' : ''; ?>>B</option>
                                                                        <option value="C" <?php echo $submission['letter_grade'] === 'C' ? 'selected' : ''; ?>>C</option>
                                                                        <option value="D" <?php echo $submission['letter_grade'] === 'D' ? 'selected' : ''; ?>>D</option>
                                                                        <option value="F" <?php echo $submission['letter_grade'] === 'F' ? 'selected' : ''; ?>>F</option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="score<?php echo $submission['id']; ?>" class="form-label">Score (Numeric)</label>
                                                                    <input type="number" class="form-control" id="score<?php echo $submission['id']; ?>" name="score" min="0" max="100" value="<?php echo $submission['score']; ?>" required>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="feedback<?php echo $submission['id']; ?>" class="form-label">Feedback</label>
                                                                    <textarea class="form-control" id="feedback<?php echo $submission['id']; ?>" name="feedback" rows="4"><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="submit_grades" class="btn btn-primary">Save Grades</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Assignments List View -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3">Assignments</h1>
                    <a href="create_assignment.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
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
                                    <div class="card-footer bg-transparent border-top-0">
                                        <div class="d-grid">
                                            <a href="assignments.php?view=<?php echo $assignment['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Submissions
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>