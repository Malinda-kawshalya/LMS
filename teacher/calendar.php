<?php
// Start the session
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
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

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2000 || $year > 2100) $year = date('Y');

// Calculate previous and next month
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get the first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('w', $first_day);
$month_name = date('F', $first_day);
$days_in_month = date('t', $first_day);

// Get assignments for the current month
$start_date = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
$end_date = date('Y-m-d', mktime(0, 0, 0, $month, $days_in_month, $year));

$stmt = $conn->prepare("
    SELECT a.id, a.title, a.due_date, a.description, c.title AS course_title, c.course_code
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
    AND a.due_date BETWEEN ? AND ?
    ORDER BY a.due_date
");
$stmt->bind_param("iss", $teacher_id, $start_date, $end_date);
$stmt->execute();
$assignments_result = $stmt->get_result();

$assignments_by_day = [];
while ($row = $assignments_result->fetch_assoc()) {
    $day = intval(date('j', strtotime($row['due_date'])));
    $assignments_by_day[$day][] = $row;
}
$stmt->close();

// Get upcoming assignments (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

$stmt = $conn->prepare("
    SELECT a.id, a.title, a.due_date, c.title AS course_title, c.course_code,
    (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) AS submission_count
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
    AND a.due_date BETWEEN ? AND ?
    ORDER BY a.due_date
    LIMIT 5
");
$stmt->bind_param("iss", $teacher_id, $today, $next_week);
$stmt->execute();
$upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT c.id) as course_count 
    FROM courses c
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$course_count = $stmt->get_result()->fetch_assoc()['course_count'];
$stmt->close();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Assignment Calendar - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #4e73df;
            --mobile-menu-bg: #36b9cc;
        }

        body {
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
            width: 220px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            color: white;
            padding-top: 70px;
            transition: transform 0.3s ease;
            z-index: 1020;
        }

        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 20px;
            display: block;
        }

        .sidebar a:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .content {
            margin-left: 220px;
            padding: 20px;
            flex: 1;
            transition: margin-left 0.3s ease;
        }

        .mobile-menu {
            display: none;
            background-color: var(--mobile-menu-bg);
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .mobile-menu a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
        }

        .mobile-menu a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.1);
            border: none;
            border-radius: 0.35rem;
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }

        .calendar {
            width: 100%;
            border-collapse: collapse;
        }

        .calendar th {
            background-color: #f8f9fc;
            text-align: center;
            padding: 10px;
        }

        .calendar td {
            width: 14.28%;
            height: 100px;
            vertical-align: top;
            padding: 5px;
            border: 1px solid #e3e6f0;
        }

        .calendar .today {
            background-color: #e6f4ff;
        }

        .calendar .other-month {
            background-color: #f8f9fc;
            color: #aaa;
        }

        .calendar .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .assignment-item {
            margin-bottom: 2px;
            padding: 2px;
            border-radius: 3px;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .assignment-item a {
            color: inherit;
            text-decoration: none;
        }

        .assignment-item:hover {
            opacity: 0.8;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .calendar-title {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
                height: auto;
                padding-top: 0;
                position: fixed;
                top: 56px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding-top: 70px;
            }

            .mobile-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .calendar td {
                height: 80px;
                font-size: 0.9rem;
            }

            .calendar-header {
                flex-direction: column;
                align-items: flex-start;
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

            .calendar td {
                height: 90px;
            }
        }
    </style>
</head>
<body>

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
                <li class="nav-item">
                    <a class="nav-link" href="chatpage.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
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
            <div class="mobile-menu d-md-none">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
                <a href="create_course.php"><i class="fas fa-plus-circle"></i> Create Course</a>
                <a href="assignments.php"><i class="fas fa-tasks"></i> Assignments</a>
                <a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Calendar Widget -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Assignment Calendar</h5>
                        </div>
                        <div class="card-body">
                            <div class="calendar-header">
                                <div class="calendar-title"><?php echo $month_name . ' ' . $year; ?></div>
                                <div class="calendar-nav">
                                    <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                    <a href="calendar.php" class="btn btn-outline-success btn-sm">Today</a>
                                    <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary btn-sm">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                            <table class="calendar">
                                <thead>
                                    <tr>
                                        <th>Sun</th>
                                        <th>Mon</th>
                                        <th>Tue</th>
                                        <th>Wed</th>
                                        <th>Thu</th>
                                        <th>Fri</th>
                                        <th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_day = 1;
                                    $current_date = date('Y-m-d');
                                    $prev_month_days = $first_day_of_week;
                                    $total_cells = $prev_month_days + $days_in_month;
                                    $total_rows = ceil($total_cells / 7);

                                    for ($i = 0; $i < $total_rows; $i++) {
                                        echo "<tr>";
                                        for ($j = 0; $j < 7; $j++) {
                                            $day_class = "";
                                            $day_content = "";

                                            if (($i == 0 && $j < $first_day_of_week) || ($current_day > $days_in_month)) {
                                                $day_class = "other-month";
                                                if ($i == 0 && $j < $first_day_of_week) {
                                                    $prev_month_day = date('j', strtotime('-' . ($first_day_of_week - $j) . ' days', $first_day));
                                                    $day_content = $prev_month_day;
                                                } else {
                                                    $next_month_day = $current_day - $days_in_month;
                                                    $day_content = $next_month_day;
                                                    $current_day++;
                                                }
                                            } else {
                                                $day_content = $current_day;
                                                $current_date_compare = date('Y-m-d', mktime(0, 0, 0, $month, $current_day, $year));
                                                if ($current_date_compare == $current_date) {
                                                    $day_class = "today";
                                                }

                                                if (isset($assignments_by_day[$current_day])) {
                                                    foreach ($assignments_by_day[$current_day] as $assignment) {
                                                        $color = sprintf('#%06X', mt_rand(0, 0xAAAAAA));
                                                        $day_content .= '<div class="assignment-item" style="background-color: ' . $color . '20; border-left: 3px solid ' . $color . ';">';
                                                        $day_content .= '<a href="assignment_details.php?id=' . $assignment['id'] . '" title="' . htmlspecialchars($assignment['title']) . '">';
                                                        $day_content .= '<small>' . htmlspecialchars($assignment['course_code']) . '</small>: ' . htmlspecialchars(substr($assignment['title'], 0, 15)) . (strlen($assignment['title']) > 15 ? '...' : '');
                                                        $day_content .= '</a></div>';
                                                    }
                                                }
                                                $current_day++;
                                            }

                                            echo '<td class="' . $day_class . '"><div class="day-number">' . $day_content . '</div></td>';
                                        }
                                        echo "</tr>";
                                        if ($current_day > $days_in_month) break;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Quick Stats -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white p-3 rounded me-3">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo $course_count; ?></h5>
                                            <small class="text-muted">Total Courses</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success text-white p-3 rounded me-3">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo count($upcoming_assignments); ?></h5>
                                            <small class="text-muted">Upcoming Assignments</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Assignments -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upcoming Assignments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_assignments) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($upcoming_assignments as $assignment): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                                                        <?php echo htmlspecialchars($assignment['course_title']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-info mb-1">
                                                        <?php echo $assignment['submission_count']; ?> Submissions
                                                    </span>
                                                    <div class="small text-muted"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="assignments.php" class="btn btn-outline-primary">Manage Assignments</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No upcoming assignments in the next 7 days.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Similar to student calendar JS, but with teacher-specific modifications if needed
        document.addEventListener('DOMContentLoaded', function() {
            const assignmentItems = document.querySelectorAll('.assignment-item');
            assignmentItems.forEach(item => {
                item.addEventListener('mouseenter', function(e) {
                    const title = this.querySelector('a').getAttribute('title');
                    const assignmentInfo = document.createElement('div');
                    assignmentInfo.className = 'assignment-info';
                    assignmentInfo.innerHTML = `<p><strong>${title}</strong></p>`;
                    assignmentInfo.style.cssText = `
                        position: absolute;
                        background-color: white;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        padding: 10px;
                        z-index: 1000;
                        width: 250px;
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    `;
                    document.body.appendChild(assignmentInfo);
                    assignmentInfo.style.top = (e.pageY + 10) + 'px';
                    assignmentInfo.style.left = (e.pageX + 10) + 'px';
                    assignmentInfo.style.display = 'block';

                    this.addEventListener('mouseleave', function() {
                        assignmentInfo.remove();
                    });
                });
            });
        });
    </script>
</body>
</html>