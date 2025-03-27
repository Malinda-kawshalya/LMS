<?php
// Start the session
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
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

// Get assignment grades
$stmt = $conn->prepare("
    SELECT 
        a.title AS assignment_title,
        c.title AS course_title,
        s.letter_grade,
        s.score,
        s.feedback,
        s.submitted_at,
        a.due_date
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE s.student_id = ? AND s.letter_grade IS NOT NULL
    ORDER BY s.submitted_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$assignment_grades_result = $stmt->get_result();
$assignment_grades = $assignment_grades_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission (unchanged for brevity, assumed to work as is)
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    $stmt = $conn->prepare("UPDATE students SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $student_id);
    
    if ($stmt->execute()) {
        $update_message = '<div class="alert alert-success">Profile updated successfully!</div>';
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

function calculateGPA($grades) {
    $total_points = 0;
    $total_credits = count($grades);

    foreach ($grades as $grade) {
        switch ($grade['letter_grade']) {
            case 'A':
                $total_points += 4.0;
                break;
            case 'B':
                $total_points += 3.0;
                break;
            case 'C':
                $total_points += 2.0;
                break;
            case 'D':
                $total_points += 1.0;
                break;
            case 'F':
                $total_points += 0.0;
                break;
        }
    }

    return $total_credits > 0 ? $total_points / $total_credits : 0;
}

// Calculate GPA
$gpa = calculateGPA($assignment_grades);

$current_page = basename($_SERVER['PHP_SELF']);
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
            --sidebar-width: 220px;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15);
            z-index: 1030;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-color);
            color: white;
            padding-top: 70px;
            transition: transform 0.3s ease;
            z-index: 1020;
        }

        .sidebar a {
            padding: 12px 20px;
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
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            flex: 1;
            transition: margin-left 0.3s ease;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
        }

        .profile-header {
            background-color: var(--primary-color);
            padding: 30px 0;
            color: white;
            margin-bottom: 30px;
        }

        .gpa-circle {
            width: 160px;
            height: 160px;
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
            font-size: 1.1rem;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .assignment-table {
            font-size: 0.9rem;
        }

        .assignment-table th, .assignment-table td {
            vertical-align: middle;
        }

        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
                height: auto;
                padding-top: 0;
                top: 56px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding-top: 70px;
            }

            .gpa-circle {
                width: 100px;
                height: 100px;
            }

            .assignment-table {
                font-size: 0.8rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar {
                width: 180px;
            }

            .content {
                margin-left: 180px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
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
    <div class="sidebar d-md-block" id="sidebar">
        <div class="position-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>" href="courses.php">
                        <i class="fas fa-book me-2"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'available_courses.php' ? 'active' : ''; ?>" href="available_courses.php">
                        <i class="fas fa-plus-circle me-2"></i> Enroll in Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php">
                        <i class="fas fa-tasks me-2"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user-circle me-2"></i> My Profile
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
    <div class="content">
        <div class="container-fluid">
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
                                            </div>
                                            <div class="col-md-6">
                                                <label for="address" class="form-label">Address</label>
                                                <input type="text" class="form-control" id="address" name="address" 
                                                       value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>">
                                            </div>
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
                                                    $progress = $course['assignment_count'] > 0 ? 
                                                        round(($course['completed_assignments'] / $course['assignment_count']) * 100) : 0;
                                                ?>
                                                <div class="col-md-6 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-body">
                                                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                            <p class="text-muted mb-1">
                                                                <i class="fas fa-tasks me-1"></i> 
                                                                <?php echo $course['completed_assignments']; ?>/<?php echo $course['assignment_count']; ?> Assignments
                                                            </p>
                                                            <div class="progress">
                                                                <div class="progress-bar bg-success" role="progressbar" 
                                                                     style="width: <?php echo $progress; ?>%" 
                                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                </div>
                                                            </div>
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

                        <!-- GPA Tab with Assignment Grades -->
<!-- GPA Tab with Assignment Grades -->
<div class="tab-pane fade" id="gpa" role="tabpanel">
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">GPA Summary</h5>
                </div>
                <div class="card-body text-center">
                    <div class="gpa-circle mb-3">
                        <span class="fs-1 fw-bold"><?php echo number_format($gpa, 2); ?></span>
                        <span>GPA (4.0 scale)</span>
                    </div>
                </div>
            </div>
        </div>

            <div class="card-body">
                <?php 
                $grade_letters = ['A', 'B', 'C', 'D', 'F'];
                        $colors = ['A' => 'success', 'B' => 'primary', 'C' => 'info', 'D' => 'warning', 'F' => 'danger'];
                    ?>
                
            
      
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Assignment Grades</h5>
                </div>
                <div class="card-body">
                    <?php if (count($assignment_grades) > 0): ?>
                        <div class="table-responsive">
                            <table class="table assignment-table table-striped">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Course</th>
                                        <th>Letter Grade</th>
                                        <th>Score</th>
                                        <th>Feedback</th>
                                        <th>Submitted</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignment_grades as $grade): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($grade['assignment_title']); ?></td>
                                            <td><?php echo htmlspecialchars($grade['course_title']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $colors[$grade['letter_grade']]; ?>">
                                                    <?php echo $grade['letter_grade']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($grade['score'], 1); ?></td>
                                            <td><?php echo htmlspecialchars($grade['feedback']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($grade['submitted_at'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($grade['due_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No graded assignments found.
                        </div>
                    <?php endif; ?>
                </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');

            navbarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 767.98 && !sidebar.contains(e.target) && !navbarToggler.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>