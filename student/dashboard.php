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

// Get upcoming assignments
$stmt = $conn->prepare("
    SELECT a.*, m.title as module_name, c.title as course_name, c.id as course_id
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

// Get all available courses for enrollment
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND student_id = ?) as is_enrolled
    FROM courses c
    WHERE c.status = 'active'
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$available_courses_result = $stmt->get_result();
$available_courses = [];
while ($row = $available_courses_result->fetch_assoc()) {
    $available_courses[] = $row;
}
$stmt->close();

// Get recent grades
$stmt = $conn->prepare("
    SELECT s.*, a.title as assignment_title, c.title as course_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE s.student_id = ? AND s.grade IS NOT NULL
    ORDER BY s.graded_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_grades = $stmt->get_result();
$stmt->close();

// Get all calendar events (assignments, deadlines, etc)
$stmt = $conn->prepare("
    SELECT 
        a.id, 
        a.title, 
        a.description, 
        a.due_date as date, 
        'assignment' as event_type,
        c.title as course_name,
        c.id as course_id,
        CASE 
            WHEN s.id IS NULL THEN 'pending'
            WHEN s.grade IS NULL THEN 'submitted'
            ELSE 'graded' 
        END as status
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ?
    UNION
    SELECT 
        c.id,
        c.title,
        c.description,
        c.start_date as date,
        'course_start' as event_type,
        c.title as course_name,
        c.id as course_id,
        'info' as status
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ? AND c.start_date >= CURDATE()
    ORDER BY date ASC
");
$stmt->bind_param("iii", $student_id, $student_id, $student_id);
$stmt->execute();
$calendar_events = $stmt->get_result();
$events = [];
while ($row = $calendar_events->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

// Calculate course progress
function calculateProgress($completed, $total) {
    if ($total == 0) return 0;
    return min(100, round(($completed / $total) * 100));
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === 'true';

// If it's an AJAX request, only return the main content
if ($is_ajax) {
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
    } elseif ($current_page == 'available_courses.php') {
        include('available_courses_content.php');
    } else {
        include('dashboard_content.php'); // Default to dashboard
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
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
        .deadline-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .overdue {
            color: var(--danger-color);
            font-weight: bold;
        }
        .course-card {
            transition: transform 0.3s;
            height: 100%;
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
            background-color: rgba(255,255,255,0.7);
            padding: 20px;
            border-radius: 10px;
        }
        .progress-card {
            border-left: 4px solid var(--primary-color);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
        }
        .btn-enroll {
            border-radius: 50px;
            padding: 8px 20px;
        }
        .fc-event {
            cursor: pointer;
        }
        .dashboard-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        .stats-card {
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .stats-card .icon-box {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
        }
        .primary-bg {
            background-color: var(--primary-color);
            color: white;
        }
        .success-bg {
            background-color: var(--success-color);
            color: white;
        }
        .warning-bg {
            background-color: var(--warning-color);
            color: white;
        }
        .info-bg {
            background-color: var(--info-color);
            color: white;
        }
        .danger-bg {
            background-color: var(--danger-color);
            color: white;
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
    <div id="loading-indicator">
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
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>" href="grades.php" data-page="grades">
                        <i class="fas fa-chart-bar me-2"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php" data-page="calendar">
                        <i class="fas fa-calendar me-2"></i> Calendar
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php" data-page="profile">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php" data-page="settings">
                        <i class="fas fa-cogs me-2"></i> Settings
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
                } elseif ($current_page == 'available_courses.php') {
                    include('available_courses_content.php');
                } elseif ($current_page == 'profile.php') {
                    include('profile_content.php');
                } elseif ($current_page == 'settings.php') {
                    include('settings_content.php');
                } else {
                    include('dashboard_content.php'); // Default to dashboard
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Course Enrollment Modal -->
    <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">My Enrolled Courses</h5>
        <a href="available_courses.php" class="btn btn-sm btn-primary">
            <i class="fas fa-book me-1"></i> Browse All Courses
        </a>
    </div>
    <div class="card-body">
        <?php
        // Fetch enrolled courses
        $enrolled_query = "SELECT c.*, e.enrollment_date 
                          FROM courses c 
                          JOIN enrollments e ON c.id = e.course_id 
                          WHERE e.student_id = ? AND c.status = 'active' 
                          ORDER BY e.enrollment_date DESC 
                          LIMIT 5";
        
        $enrolled_stmt = $conn->prepare($enrolled_query);
        $enrolled_stmt->bind_param("i", $student_id);
        $enrolled_stmt->execute();
        $enrolled_courses = $enrolled_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $enrolled_stmt->close();
        
        if (count($enrolled_courses) > 0) {
        ?>
            <div class="row">
                <?php foreach($enrolled_courses as $course): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card course-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?><br>
                                    <strong>Credit Hours:</strong> <?php echo $course['credit_hours']; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-check me-1"></i> 
                                        Enrolled: <?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?>
                                    </small>
                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-success">
                                        Continue
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($enrolled_courses) === 5): ?>
                <div class="text-center mt-3">
                    <a href="enrolled_courses.php" class="btn btn-outline-primary">View All My Courses</a>
                </div>
            <?php endif; ?>
            
        <?php } else { ?>
            <div class="text-center py-4">
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">You're not enrolled in any courses yet</h5>
                <p>Browse our catalog and enroll in courses to get started.</p>
                <a href="available_courses.php" class="btn btn-primary mt-2">
                    Browse Available Courses
                </a>
            </div>
        <?php } ?>
    </div>
</div>
    
    <!-- Assignment Details Modal -->
    <div class="modal fade" id="assignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assignment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="assignmentDetails">
                    <!-- Assignment details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="viewAssignmentBtn">View Full Assignment</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Mobile sidebar toggle
        $('#sidebar-toggle').click(function() {
            $('#sidebar').toggleClass('show');
            $('#content').toggleClass('shift');
        });
        
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
            
            // Close mobile sidebar if open
            if ($(window).width() < 768) {
                $('#sidebar').removeClass('show');
                $('#content').removeClass('shift');
            }
            
            // Load the content via AJAX
            $.ajax({
                url: url + '?ajax=true',
                type: 'GET',
                success: function(response) {
                    // Update the content area with the response
                    $('#dynamic-content').html(response);
                    
                    // Initialize any JavaScript components in the new content
                    initializeComponents();
                    
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
        
        // Handle enrollment modal
        $(document).on('click', '.enroll-btn', function() {
            const courseId = $(this).data('course-id');
            const courseName = $(this).data('course-name');
            
            $('#enrollCourseName').text(courseName);
            $('#confirmEnroll').data('course-id', courseId);
            
            // Get course details
            $.ajax({
                url: 'ajax/get_course_details.php',
                type: 'GET',
                data: { course_id: courseId },
                success: function(response) {
                    $('#enrollmentDetails').html(response);
                    $('#enrollModal').modal('show');
                }
            });
        });
        
        // Handle enrollment confirmation
        $('#confirmEnroll').on('click', function() {
            const courseId = $(this).data('course-id');
            
            // Show loading indicator
            $('#loading-indicator').show();
            
            // Process enrollment
            $.ajax({
                url: 'ajax/enroll_course.php',
                type: 'POST',
                data: { course_id: courseId },
                success: function(response) {
                    // Hide modal
                    $('#enrollModal').modal('hide');
                    
                    // Reload current page
                    const currentPage = $('.menu-link.active').attr('href');
                    
                    if (currentPage) {
                        $.ajax({
                            url: currentPage + '?ajax=true',
                            type: 'GET',
                            success: function(response) {
                                $('#dynamic-content').html(response);
                                $('#loading-indicator').hide();
                                
                                // Show success message
                                const successAlert = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                                                    'You have successfully enrolled in the course!' +
                                                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                                                    '</div>';
                                $('#dynamic-content').prepend(successAlert);
                                
                                // Initialize components
                                initializeComponents();
                            }
                        });
                    } else {
                        location.reload();
                    }
                },
                error: function() {
                    $('#enrollModal').modal('hide');
                    $('#loading-indicator').hide();
                    
                    // Show error message
                    const errorAlert = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                                      'Error enrolling in the course. Please try again.' +
                                      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                                      '</div>';
                    $('#dynamic-content').prepend(errorAlert);
                }
            });
        });
        
       // Handle assignment modal
        $(document).on('click', '.view-assignment', function() {
            const assignmentId = $(this).data('assignment-id');
            
            // Get assignment details
            $.ajax({
                url: 'ajax/get_assignment_details.php',
                type: 'GET',
                data: { assignment_id: assignmentId },
                success: function(response) {
                    $('#assignmentDetails').html(response);
                    $('#viewAssignmentBtn').attr('href', 'assignment_view.php?id=' + assignmentId);
                    $('#assignmentModal').modal('show');
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
        
        // Function to initialize components
        function initializeComponents() {
            // Reinitialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Initialize calendar if it exists
            if (document.getElementById('calendar')) {
                initializeCalendar();
            }
            
            // Initialize course progress charts
            $('.course-progress-chart').each(function() {
                const ctx = this.getContext('2d');
                const progress = $(this).data('progress');
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [progress, 100 - progress],
                            backgroundColor: [
                                '#4e73df',
                                '#eaecf4'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '80%',
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
            
            // Initialize grade distribution chart if it exists
            if (document.getElementById('gradeDistribution')) {
                const ctx = document.getElementById('gradeDistribution').getContext('2d');
                const gradeLabels = JSON.parse(document.getElementById('gradeDistribution').getAttribute('data-labels'));
                const gradeData = JSON.parse(document.getElementById('gradeDistribution').getAttribute('data-values'));
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: gradeLabels,
                        datasets: [{
                            label: 'Grade Distribution',
                            data: gradeData,
                            backgroundColor: [
                                '#e74a3b',
                                '#f6c23e',
                                '#4e73df',
                                '#1cc88a',
                                '#36b9cc'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }
        
        // Function to initialize calendar
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            // Get events data
            $.ajax({
                url: 'ajax/get_calendar_events.php',
                type: 'GET',
                success: function(response) {
                    const events = JSON.parse(response);
                    
                    const calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,listWeek'
                        },
                        events: events,
                        eventClick: function(info) {
                            if (info.event.extendedProps.event_type === 'assignment') {
                                // Show assignment details
                                $.ajax({
                                    url: 'ajax/get_assignment_details.php',
                                    type: 'GET',
                                    data: { assignment_id: info.event.id },
                                    success: function(response) {
                                        $('#assignmentDetails').html(response);
                                        $('#viewAssignmentBtn').attr('href', 'assignment_view.php?id=' + info.event.id);
                                        $('#assignmentModal').modal('show');
                                    }
                                });
                            }
                        },
                        eventClassNames: function(arg) {
                            if (arg.event.extendedProps.status === 'pending' && new Date(arg.event.start) < new Date()) {
                                return ['bg-danger', 'text-white'];
                            } else if (arg.event.extendedProps.status === 'pending') {
                                return ['bg-warning', 'text-dark'];
                            } else if (arg.event.extendedProps.status === 'submitted') {
                                return ['bg-info', 'text-white'];
                            } else if (arg.event.extendedProps.status === 'graded') {
                                return ['bg-success', 'text-white'];
                            } else {
                                return ['bg-primary', 'text-white'];
                            }
                        }
                    });
                    
                    calendar.render();
                }
            });
        }
        
        // Initialize components on first load
        initializeComponents();
    });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>