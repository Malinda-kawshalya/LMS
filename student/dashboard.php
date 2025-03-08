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
           (SELECT COUNT(*) FROM assignments a JOIN modules m ON a.module_id = m.id WHERE m.course_id = c.id) as assignment_count
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Get upcoming assignments
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_name
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ? AND (s.id IS NULL)
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$upcoming_assignments = $stmt->get_result();
$stmt->close();

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === 'true';

// If it's an AJAX request, only return the main content
if ($is_ajax) {
    include('dashboard_content.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LMS</title>
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
        .sidebar .active {
            color: white;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
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
        .deadline-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        .course-card {
            transition: transform 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        #loading-indicator {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Loading Indicator -->
    <div id="loading-indicator">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand ps-3" href="dashboard.php">LMS - Student</a>
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
                    <a class="nav-link menu-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" data-page="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>" href="courses.php" data-page="courses">
                        <i class="fas fa-book"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php" data-page="assignments">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>" href="grades.php" data-page="grades">
                        <i class="fas fa-chart-bar"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php" data-page="calendar">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>" href="messages.php" data-page="messages">
                        <i class="fas fa-envelope"></i> Messages
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div id="dynamic-content">
                <!-- Dashboard content will be loaded here -->
                <?php
                // Include the appropriate content file based on current page
                if ($current_page == 'dashboard.php') {
                    include('dashboard_content.php');
                } elseif ($current_page == 'assignments.php') {
                    include('assignments_content.php');
                } elseif ($current_page == 'courses.php') {
                    include('courses_content.php');
                } elseif ($current_page == 'grades.php') {
                    include('grades_content.php');
                } elseif ($current_page == 'calendar.php') {
                    include('calendar_content.php');
                } elseif ($current_page == 'messages.php') {
                    include('messages_content.php');
                } else {
                    include('dashboard_content.php'); // Default to dashboard
                }
                ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Handle sidebar menu clicks
        $('.menu-link').on('click', function(e) {
            e.preventDefault(); // Prevent normal navigation
            
            // Get the page to load
            const page = $(this).attr('data-page');
            const url = $(this).attr('href');
            
            // Update active class
            $('.menu-link').removeClass('active');
            $(this).addClass('active');
            
            // If it's dashboard, just reload the page
            if (page === 'dashboard' && url === window.location.pathname) {
                return; // Already on dashboard
            }
            
            // Show loading indicator
            $('#loading-indicator').show();
            
            // Load the content via AJAX
            $.ajax({
                url: url + '?ajax=true',
                type: 'GET',
                success: function(response) {
                    // Update the content area with the response
                    $('#dynamic-content').html(response);
                    
                    // Update browser history so back button works
                    history.pushState({page: page}, '', url);
                    
                    // Hide loading indicator
                    $('#loading-indicator').hide();
                },
                error: function() {
                    $('#dynamic-content').html('<div class="alert alert-danger">Error loading content. Please try again.</div>');
                    $('#loading-indicator').hide();
                }
            });
        });
        
        // Handle browser back/forward buttons
        $(window).on('popstate', function(e) {
            if (e.originalEvent && e.originalEvent.state) {
                const page = e.originalEvent.state.page;
                $('.menu-link[data-page="' + page + '"]').click();
            } else {
                // If no state, probably back to dashboard
                window.location.reload();
            }
        });
    });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>