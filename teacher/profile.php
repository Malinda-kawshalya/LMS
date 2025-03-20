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

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $qualification = $_POST['qualification'];
    $bio = $_POST['bio'];
    
    // Check if email is already in use by another teacher
    $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $teacher_id);
    $stmt->execute();
    $email_check = $stmt->get_result();
    $stmt->close();
    
    if ($email_check->num_rows > 0) {
        $error_message = "Email address is already in use by another teacher.";
    } else {
        // Update teacher profile
        $stmt = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, phone = ?, department = ?, qualification = ?, bio = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $full_name, $email, $phone, $department, $qualification, $bio, $teacher_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            
            // Update session data if needed
            $_SESSION['user_email'] = $email;
            
            // Refresh teacher data
            $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $teacher = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
        
        $stmt->close();
    }
}

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

// Get assignment statistics
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_assignments,
    SUM(CASE WHEN due_date < NOW() THEN 1 ELSE 0 END) as past_assignments,
    SUM(CASE WHEN due_date >= NOW() THEN 1 ELSE 0 END) as upcoming_assignments
    FROM assignments
    WHERE created_by = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignment_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get student statistics
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT e.course_id) as courses_taught
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    JOIN teacher_courses tc ON e.course_id = tc.course_id
    WHERE tc.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$student_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - LMS</title>
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
            padding: 20px;
            padding-top: 80px;
        }
        @media (min-width: 768px) {
            .content {
                margin-left: 220px;
            }
        }
        @media (max-width: 767.98px) {
            .sidebar {
                position: static;
                height: auto;
                padding-top: 0;
            }
            .content {
                margin-left: 0;
            }
        }
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
            border-radius: 0.35rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 15px;
            color: white;
        }
        .bg-gradient-primary {
            background: linear-gradient(to right, #4e73df, #224abe);
        }
        .bg-gradient-success {
            background: linear-gradient(to right, #1cc88a, #13855c);
        }
        .bg-gradient-info {
            background: linear-gradient(to right, #36b9cc, #258391);
        }
        .bg-gradient-warning {
            background: linear-gradient(to right, #f6c23e, #dda20a);
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stats-item {
            flex: 1;
            min-width: 200px;
        }
        @media (max-width: 576px) {
            .stats-item {
                min-width: 100%;
            }
        }
        .mobile-menu {
            background-color: #4e73df;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        @media (max-width: 767.98px) {
            .mobile-menu {
                display: block;
            }
        }
        .mobile-menu a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
        }
        .mobile-menu a:hover {
            color: rgba(255, 255, 255, 0.8);
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
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user"></i> Profile
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
            <!-- Mobile Menu -->
            <div class="mobile-menu">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="assignments.php"><i class="fas fa-tasks"></i> Assignments</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <h1 class="h3 mb-4">Teacher Profile</h1>
                    
                    <!-- Teacher Info Summary -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($teacher['email']); ?> |
                                        <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($teacher['phone'] ?? 'Not provided'); ?> |
                                        <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($teacher['department']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <span class="badge bg-primary p-2">Teacher ID: <?php echo $teacher['id']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-container">
                        <div class="stats-item">
                            <div class="stats-card bg-gradient-primary">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <h5><?php echo $student_stats['courses_taught']; ?> Courses</h5>
                                <p class="mb-0">Currently Teaching</p>
                            </div>
                        </div>
                        
                        <div class="stats-item">
                            <div class="stats-card bg-gradient-success">
                                <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                <h5><?php echo $student_stats['total_students']; ?> Students</h5>
                                <p class="mb-0">Under Guidance</p>
                            </div>
                        </div>
                        
                        <div class="stats-item">
                            <div class="stats-card bg-gradient-info">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <h5><?php echo $assignment_stats['total_assignments']; ?> Assignments</h5>
                                <p class="mb-0"><?php echo $assignment_stats['upcoming_assignments']; ?> Upcoming</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <!-- Edit Profile Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Edit Profile</h5>
                                </div>
                                <div class="card-body">
                                    <form action="profile.php" method="post">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="department" class="form-label">Department</label>
                                                <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($teacher['department']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="qualification" class="form-label">Qualification</label>
                                            <input type="text" class="form-control" id="qualification" name="qualification" value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="bio" class="form-label">Bio / About Me</label>
                                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($teacher['bio'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <!-- Current Courses -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Current Courses</h5>
                                    <span class="badge bg-primary"><?php echo count($courses); ?> Courses</span>
                                </div>
                                <div class="card-body">
                                    <?php if (count($courses) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Course Code</th>
                                                        <th>Title</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($courses as $course): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                                                            <td>
                                                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i> No courses assigned yet.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Additional Information -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Additional Information</h5>
                                </div>
                                <div class="card-body">
                                    <dl class="row">
                                        <dt class="col-sm-4">Qualification:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($teacher['qualification'] ?? 'Not provided'); ?></dd>
                                        
                                        <dt class="col-sm-4">Department:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($teacher['department']); ?></dd>
                                        
                                        <dt class="col-sm-4">Bio:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($teacher['bio'] ?? 'Not provided'); ?></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <!-- Recent Activity -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Assignment Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <h2 class="text-primary"><?php echo $assignment_stats['total_assignments']; ?></h2>
                                            <p class="text-muted">Total Assignments</p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h2 class="text-success"><?php echo $assignment_stats['upcoming_assignments']; ?></h2>
                                            <p class="text-muted">Upcoming</p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h2 class="text-warning"><?php echo $assignment_stats['past_assignments']; ?></h2>
                                            <p class="text-muted">Past Due</p>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2 mt-3">
                                        <a href="assignments.php" class="btn btn-outline-primary">View All Assignments</a>
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
</body>
</html>