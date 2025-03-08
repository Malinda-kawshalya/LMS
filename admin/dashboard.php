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

// Get all teachers
$stmt = $conn->prepare("
    SELECT * FROM teachers 
    ORDER BY full_name
");
$stmt->execute();
$teachers = $stmt->get_result();
$stmt->close();
// Get total courses count
$result = $conn->query("SELECT COUNT(*) as total FROM courses");
$total_courses = $result->fetch_assoc()['total'];

// Get total teachers count
$result = $conn->query("SELECT COUNT(*) as total FROM teachers");
$total_teachers = $result->fetch_assoc()['total'];

// Get total students count
$result = $conn->query("SELECT COUNT(*) as total FROM students");
$total_students = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LMS</title>
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
                <a class="nav-link text-white active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_courses.php">
                    <i class="fas fa-book"></i> Manage Courses
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

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Admin Dashboard</h1>

            <?php
            // Process course creation form submission
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['title'])) {
                // Sanitize and validate inputs
                $title = $conn->real_escape_string($_POST['title']);
                $course_code = $conn->real_escape_string($_POST['course_code']);
                $description = $conn->real_escape_string($_POST['description']);
                $credit_hours = (int)$_POST['credit_hours'];
                $teacher_id = (int)$_POST['teacher_id'];
                
                // Insert course into database
                $stmt = $conn->prepare("INSERT INTO courses (title, course_code, description, credit_hours, teacher_id) 
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $title, $course_code, $description, $credit_hours, $teacher_id);
                
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> Course has been added successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Failed to add course: ' . $stmt->error . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                }
                $stmt->close();
                
                // Refresh teachers list after adding course
                $stmt = $conn->prepare("SELECT * FROM teachers ORDER BY full_name");
                $stmt->execute();
                $teachers = $stmt->get_result();
                $stmt->close();
            }
            ?>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Courses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_courses; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-book fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Teachers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_teachers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Students</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Course Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Create New Course</h6>
                </div>
                <div class="card-body">
                    <form action="dashboard.php" method="post">
                        <div class="mb-3">
                            <label for="title" class="form-label">Course Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="credit_hours" class="form-label">Credit Hours</label>
                            <input type="number" class="form-control" id="credit_hours" name="credit_hours" required min="1">
                        </div>

                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Assign Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?> 
                                        (<?php echo htmlspecialchars($teacher['email']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Create Course</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>