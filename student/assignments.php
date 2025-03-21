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

// Base query for assignments
$query = "
    SELECT 
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.max_score,
        m.title as module_title,
        c.id as course_id,
        c.title as course_title,
        s.submitted_at,
        s.grade,
        CASE 
            WHEN s.id IS NULL AND a.due_date < NOW() THEN 'overdue'
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
    ORDER BY 
    CASE 
        WHEN s.id IS NULL AND a.due_date >= NOW() THEN 1
        WHEN s.id IS NULL AND a.due_date < NOW() THEN 2
        WHEN s.grade IS NULL THEN 3
        ELSE 4
    END,
    a.due_date ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - LMS</title>
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
        }

        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.1);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .card-title a:hover {
            text-decoration: underline;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        .btn-sm {
            border-radius: 50px;
            padding: 0.375rem 1rem;
            transition: all 0.2s ease;
        }

        .btn-sm:hover {
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
                padding-top: 70px;
            }

            .card-body {
                padding: 1rem;
            }

            .row.align-items-center > div {
                margin-bottom: 10px;
            }

            .text-md-end {
                text-align: left !important;
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
                    <a class="nav-link " href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a>
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
            <h2 class="mb-4"></h2>
            <div class="row">
                <div class="col-12">
                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                            <h4>No Assignments Found</h4>
                            <p class="text-muted">No assignments available at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6 col-12">
                                            <h5 class="card-title mb-1">
                                                <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>">
                                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                                </a>
                                            </h5>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($assignment['course_title']); ?> - 
                                                <?php echo htmlspecialchars($assignment['module_title']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="text-muted">
                                                <i class="fas fa-calendar me-1"></i> 
                                                <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6 text-md-end">
                                            <?php
                                            $badge_class = match($assignment['status']) {
                                                'overdue' => 'bg-danger',
                                                'pending' => 'bg-warning text-dark',
                                                'submitted' => 'bg-info',
                                                'graded' => 'bg-success',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> mb-2 mb-md-0">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                            <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" 
                                               class="btn btn-sm btn-primary ms-2">
                                                View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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