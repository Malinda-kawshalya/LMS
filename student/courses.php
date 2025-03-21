<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Define current page for active link highlighting
$current_page = basename(__FILE__);

// Fetch enrolled courses with progress
$query = "
    SELECT 
        c.id,
        c.title,
        c.description,
        c.course_code,
        (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
        (SELECT COUNT(*) FROM assignments a 
         JOIN modules m ON a.module_id = m.id 
         WHERE m.course_id = c.id) as assignment_count,
        (SELECT COUNT(*) FROM submissions s 
         JOIN assignments a ON s.assignment_id = a.id 
         JOIN modules m ON a.module_id = m.id 
         WHERE m.course_id = c.id AND s.student_id = ?) as completed_assignments
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY c.title ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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

        /* Navbar */
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15);
            z-index: 1030;
        }

        /* Sidebar */
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

        /* Content */
        .content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            flex: 1;
            transition: margin-left 0.3s ease;
            margin-top: 20px;
        }

        .course-card {
            height: 100%;
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.1);
            transition: transform 0.2s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
        }

        .card-title a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .card-title a:hover {
            text-decoration: underline;
        }

        .progress {
            height: 8px;
            margin: 10px 0;
        }

        .course-stats {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        .btn {
            border-radius: 50px;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: scale(1.05);
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
                padding-top: 100px;
            }

            .course-card .card-body {
                padding: 1rem;
            }

            .course-stats {
                font-size: 0.85rem;
            }

            .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar {
                width: 180px;
            }

            .content {
                margin-left: 180px;
            }

            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }
    </style>
</head>
<body>

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
                    <a class="nav-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                        <i class="fas fa-calendar-alt me-2"></i> Calendar
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
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Courses</h2>
                <a href="available_courses.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Browse More
                </a>
            </div>

            <?php if (empty($courses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h4>No Courses Found</h4>
                    <p class="text-muted">You haven't enrolled in any courses yet.</p>
                    <a href="available_courses.php" class="btn btn-primary">Browse Available Courses</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($courses as $course): ?>
                        <?php 
                        $progress = $course['assignment_count'] > 0 
                            ? round(($course['completed_assignments'] / $course['assignment_count']) * 100) 
                            : 0;
                        ?>
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <div class="card course-card shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">
                                        <a href="course_view.php?id=<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted small mb-2">
                                        <?php echo htmlspecialchars($course['course_code']); ?>
                                    </p>
                                    <div class="course-stats mb-3">
                                        <i class="fas fa-book-reader me-1"></i> <?php echo $course['module_count']; ?> Modules
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-tasks me-1"></i> <?php echo $course['assignment_count']; ?> Assignments
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: <?php echo $progress; ?>%" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="text-muted small mt-2">
                                        Progress: <?php echo $progress; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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