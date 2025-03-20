<?php
// Start the session
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
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

// Get student information
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get enrolled courses
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
           (SELECT COUNT(*) FROM assignments a JOIN modules m ON a.module_id = m.id WHERE m.course_id = c.id) as assignment_count,
           (SELECT COUNT(*) FROM submissions s JOIN assignments a ON s.assignment_id = a.id 
            JOIN modules m ON a.module_id = m.id WHERE m.course_id = c.id AND s.student_id = ?) as completed_assignments
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Get GPA information
$stmt = $conn->prepare("
    SELECT AVG(s.grade) as average_grade,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(s.id) as completed_assignments
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ? AND s.grade IS NOT NULL
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$gpa_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate GPA on a 4.0 scale
$average_percentage = $gpa_info['average_grade'] ?? 0;
$gpa = 0;

if ($average_percentage >= 90) {
    $gpa = 4.0;
} elseif ($average_percentage >= 80) {
    $gpa = 3.0 + ($average_percentage - 80) / 10;
} elseif ($average_percentage >= 70) {
    $gpa = 2.0 + ($average_percentage - 70) / 10;
} elseif ($average_percentage >= 60) {
    $gpa = 1.0 + ($average_percentage - 60) / 10;
} elseif ($average_percentage > 0) {
    $gpa = $average_percentage / 60;
}

// Get grade distribution
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN s.grade >= 90 THEN 'A'
            WHEN s.grade >= 80 THEN 'B'
            WHEN s.grade >= 70 THEN 'C'
            WHEN s.grade >= 60 THEN 'D'
            ELSE 'F'
        END as grade_letter,
        COUNT(*) as count
    FROM submissions s
    WHERE s.student_id = ? AND s.grade IS NOT NULL
    GROUP BY grade_letter
    ORDER BY grade_letter
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grade_distribution_result = $stmt->get_result();
$grade_distribution = [];
while ($row = $grade_distribution_result->fetch_assoc()) {
    $grade_distribution[$row['grade_letter']] = $row['count'];
}
$stmt->close();

// Handle form submission
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $bio = $_POST['bio'];
    
    // Update profile picture if one was uploaded
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $file_name = $student_id . '_' . time() . '.' . pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $target_path = '../uploads/profile_pictures/' . $file_name;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/profile_pictures/')) {
                mkdir('../uploads/profile_pictures/', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                // Update profile picture path in database
                $stmt = $conn->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $file_name, $student_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    // Update password if provided
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 6) {
            $update_message = '<div class="alert alert-danger">Password must be at least 6 characters long</div>';
        } else {
            $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $student_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Update other profile information
    $stmt = $conn->prepare("UPDATE students SET full_name = ?, email = ?, phone = ?, address = ?, bio = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $bio, $student_id);
    
    if ($stmt->execute()) {
        $update_message = '<div class="alert alert-success">Profile updated successfully!</div>';
        
        // Refresh student data
        $stmt->close();
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
    } else {
        $update_message = '<div class="alert alert-danger">Error updating profile: ' . $conn->error . '</div>';
    }
    
    $stmt->close();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === 'true';

// If it's an AJAX request, only return the main content
if ($is_ajax) {
    include('profile_content.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-color: #5a5c69;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
        }
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
            padding-top: 70px;
            background-color: var(--primary-color);
            color: white;
            width: 220px;
            transition: all 0.3s;
        }
        .sidebar a {
            padding: 10px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .active {
            color: white;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid white;
        }
        .content {
            margin-left: 220px;
            padding: 20px;
            padding-top: 80px;
            transition: all 0.3s;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .profile-header {
            background-color: var(--primary-color);
            padding: 30px 0;
            color: white;
            margin-bottom: 30px;
        }
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }
        .gpa-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .grade-badge {
            font-size: 1.2rem;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                padding-top: 60px;
            }
            .content {
                margin-left: 0;
                padding-top: 60px;
            }
            .sidebar.show {
                width: 220px;
            }
            .content.shift {
                margin-left: 220px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Indicator -->
    <div id="loading-indicator" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Loading content...</div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button class="navbar-toggler border-0 d-md-none" id="sidebar-toggle" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand ps-3" href="dashboard.php">
                <i class="fas fa-graduation-cap"></i> LMS - Student
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user fa-fw"></i> <?php echo htmlspecialchars($student['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="position-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" data-page="dashboard">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>" href="courses.php" data-page="courses">
                        <i class="fas fa-book me-2"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'available_courses.php' ? 'active' : ''; ?>" href="available_courses.php" data-page="available_courses">
                        <i class="fas fa-plus-circle me-2"></i> Enroll in Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php" data-page="assignments">
                        <i class="fas fa-tasks me-2"></i> Assignments
                        <?php
                        // Get pending assignment count
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) as count 
                            FROM assignments a
                            JOIN modules m ON a.module_id = m.id
                            JOIN courses c ON m.course_id = c.id
                            JOIN enrollments e ON c.id = e.course_id
                            LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
                            WHERE e.student_id = ? AND (s.id IS NULL) AND a.due_date > NOW()
                        ");
                        $stmt->bind_param("ii", $student_id, $student_id);
                        $stmt->execute();
                        $pending = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        if ($pending['count'] > 0) {
                            echo '<span class="badge bg-warning ms-2">' . $pending['count'] . '</span>';
                        }
                        ?>
                    </a>
                </li>
                    
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container-fluid" id="dynamic-content">
            <!-- Profile Header -->
            <div class="row profile-header">
                <div class="col-md-12 text-center">
                    <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <p class="lead"><?php echo htmlspecialchars($student['student_id'] ?? 'Student ID: ' . $student['id']); ?></p>
                </div>
            </div>

            <?php echo $update_message; ?>
            
            <!-- Profile Content -->
            <div class="row">
                <div class="col-md-12 mb-4">
                    <ul class="nav nav-pills mb-3" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button">
                                <i class="fas fa-user me-2"></i>Profile Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="courses-tab" data-bs-toggle="pill" data-bs-target="#courses" type="button">
                                <i class="fas fa-book me-2"></i>Enrolled Courses
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="gpa-tab" data-bs-toggle="pill" data-bs-target="#gpa" type="button">
                                <i class="fas fa-chart-line me-2"></i>Academic Record
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Edit Profile Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                        
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <input type="text" class="form-control" id="address" name="address" 
                                                   value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>">
                                        </div>

                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Courses Tab -->
                        <div class="tab-pane fade" id="courses" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">My Enrolled Courses</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($courses) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($courses as $course): ?>
                                                <?php 
                                                    $progress = 0;
                                                    if ($course['assignment_count'] > 0) {
                                                        $progress = calculateProgress($course['completed_assignments'], $course['assignment_count']);
                                                    }
                                                ?>
                                                <div class="col-md-6 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-header">
                                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row align-items-center">
                                                                <div class="col-4">
                                                                    <div class="position-relative" style="width: 80px; height: 80px;">
                                                                        <canvas class="course-progress-chart" data-progress="<?php echo $progress; ?>"></canvas>
                                                                        <div class="position-absolute top-50 start-50 translate-middle">
                                                                            <strong class="text-primary"><?php echo $progress; ?>%</strong>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-8">
                                                                    <p class="text-muted mb-1">
                                                                        <i class="fas fa-calendar-alt me-1"></i> Started: 
                                                                        <?php echo date('M d, Y', strtotime($course['start_date'])); ?>
                                                                    </p>
                                                                    <p class="text-muted mb-1">
                                                                        <i class="fas fa-tasks me-1"></i> Modules: <?php echo $course['module_count']; ?>
                                                                    </p>
                                                                    <p class="text-muted mb-0">
                                                                        <i class="fas fa-file-alt me-1"></i> Assignments: 
                                                                        <?php echo $course['completed_assignments']; ?>/<?php echo $course['assignment_count']; ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <p class="text-muted mb-0"><?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>...</p>
                                                        </div>
                                                        <div class="card-footer bg-transparent border-0">
                                                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye me-1"></i> View Course
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> You are not enrolled in any courses yet.
                                            <a href="available_courses.php" class="alert-link">Browse available courses</a> to enroll.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- GPA Tab -->
                        <div class="tab-pane fade" id="gpa" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">GPA Summary</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <div class="gpa-circle mb-3">
                                                <span class="fs-1 fw-bold"><?php echo number_format($gpa, 2); ?></span>
                                                <span>GPA (4.0 scale)</span>
                                            </div>
                                            
                                            <div class="row mt-4">
                                                <div class="col-6">
                                                    <div class="text-muted">Average Grade</div>
                                                    <div class="fs-4 fw-bold">
                                                        <?php echo number_format($average_percentage, 1); ?>%
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-muted">Completion Rate</div>
                                                    <div class="fs-4 fw-bold">
                                                        <?php 
                                                            $completion_rate = ($gpa_info['total_assignments'] > 0) ? 
                                                                ($gpa_info['completed_assignments'] / $gpa_info['total_assignments']) * 100 : 0;
                                                            echo number_format($completion_rate, 1); 
                                                        ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Grade Distribution</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php 
                                                $grade_letters = ['A', 'B', 'C', 'D', 'F'];
                                                $colors = [
                                                    'A' => 'success',
                                                    'B' => 'primary',
                                                    'C' => 'info',
                                                    'D' => 'warning',
                                                    'F' => 'danger'
                                                ];
                                            ?>
                                            <?php foreach ($grade_letters as $letter): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <span class="badge bg-<?php echo $colors[$letter]; ?> grade-badge">
                                                            <?php echo $letter; ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex-grow-1 mx-3">
                                                        <div class="progress" style="height: 10px;">
                                                            <?php 
                                                                $count = $grade_distribution[$letter] ?? 0;
                                                                $total_submissions = array_sum($grade_distribution);
                                                                $percentage = ($total_submissions > 0) ? ($count / $total_submissions) * 100 : 0;
                                                            ?>
                                                            <div class="progress-bar bg-<?php echo $colors[$letter]; ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $percentage; ?>%" 
                                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-secondary">
                                                            <?php echo $count; ?> 
                                                            (<?php echo number_format($percentage, 1); ?>%)
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-12">
                                <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Assignment Performance</h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="assignmentChart" width="400" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script>
        // Helper function to calculate progress
        <?php 
        function calculateProgress($completed, $total) {
            return ($total > 0) ? round(($completed / $total) * 100) : 0;
        }
        ?>

        // Initialize course progress charts
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    content.classList.toggle('shift');
                });
            }
            
            // Initialize course progress charts
            const progressCharts = document.querySelectorAll('.course-progress-chart');
            progressCharts.forEach(chart => {
                const ctx = chart.getContext('2d');
                const progress = chart.dataset.progress;
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [progress, 100 - progress],
                            backgroundColor: ['#4e73df', '#eaecf4'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '80%',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    }
                });
            });
            
            // Initialize assignment performance chart
            const assignmentCtx = document.getElementById('assignmentChart');
            if (assignmentCtx) {
                new Chart(assignmentCtx, {
                    type: 'line',
                    data: {
                        labels: [
                            <?php
                            // Get last 10 assignments with grades
                            $stmt = $conn->prepare("
                                SELECT a.title, s.grade, s.submission_date 
                                FROM submissions s
                                JOIN assignments a ON s.assignment_id = a.id
                                WHERE s.student_id = ? AND s.grade IS NOT NULL
                                ORDER BY s.submission_date DESC
                                LIMIT 10
                            ");
                            $stmt->bind_param("i", $student_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $assignments = [];
                            while ($row = $result->fetch_assoc()) {
                                $assignments[] = $row;
                            }
                            $assignments = array_reverse($assignments);
                            
                            foreach ($assignments as $index => $assignment) {
                                echo "'" . htmlspecialchars(substr($assignment['title'], 0, 10)) . "'";
                                if ($index < count($assignments) - 1) {
                                    echo ", ";
                                }
                            }
                            ?>
                        ],
                        datasets: [{
                            label: 'Assignment Grades',
                            data: [
                                <?php
                                foreach ($assignments as $index => $assignment) {
                                    echo $assignment['grade'];
                                    if ($index < count($assignments) - 1) {
                                        echo ", ";
                                    }
                                }
                                ?>
                            ],
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            pointBackgroundColor: '#4e73df',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#4e73df',
                            fill: true,
                            tension: 0.1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.raw + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Handle AJAX content loading for menu links
            const menuLinks = document.querySelectorAll('.menu-link');
            const dynamicContent = document.getElementById('dynamic-content');
            const loadingIndicator = document.getElementById('loading-indicator');
            
            menuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('active')) {
                        e.preventDefault();
                        
                        const page = this.dataset.page;
                        const url = page + '.php?ajax=true';
                        
                        // Show loading indicator
                        loadingIndicator.style.display = 'flex';
                        loadingIndicator.style.position = 'fixed';
                        loadingIndicator.style.top = '50%';
                        loadingIndicator.style.left = '50%';
                        loadingIndicator.style.transform = 'translate(-50%, -50%)';
                        loadingIndicator.style.zIndex = '1000';
                        loadingIndicator.style.flexDirection = 'column';
                        loadingIndicator.style.alignItems = 'center';
                        loadingIndicator.style.justifyContent = 'center';
                        loadingIndicator.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
                        loadingIndicator.style.padding = '20px';
                        loadingIndicator.style.borderRadius = '10px';
                        
                        fetch(url)
                            .then(response => response.text())
                            .then(data => {
                                dynamicContent.innerHTML = data;
                                
                                // Update URL without reloading the page
                                history.pushState(null, '', page + '.php');
                                
                                // Update active link in sidebar
                                menuLinks.forEach(link => link.classList.remove('active'));
                                this.classList.add('active');
                                
                                // Hide loading indicator
                                loadingIndicator.style.display = 'none';
                                
                                // Reinitialize any JS components on the new page
                                if (page === 'profile') {
                                    initializeProfileCharts();
                                }
                            })
                            .catch(error => {
                                console.error('Error loading content:', error);
                                loadingIndicator.style.display = 'none';
                                dynamicContent.innerHTML = '<div class="alert alert-danger">Error loading content. Please try again.</div>';
                            });
                    }
                });
            });
            
            // Function to initialize profile charts
            function initializeProfileCharts() {
                // Reinitialize course progress charts
                const progressCharts = document.querySelectorAll('.course-progress-chart');
                progressCharts.forEach(chart => {
                    const ctx = chart.getContext('2d');
                    const progress = chart.dataset.progress;
                    
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            datasets: [{
                                data: [progress, 100 - progress],
                                backgroundColor: ['#4e73df', '#eaecf4'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            cutout: '80%',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: false
                                }
                            }
                        }
                    });
                });
                
                // Reinitialize assignment performance chart
                const assignmentCtx = document.getElementById('assignmentChart');
                if (assignmentCtx) {
                    // Chart initialization code...
                    // (This would be the same code as above for the chart)
                }
            }
        });
    </script>
</body>
</html>