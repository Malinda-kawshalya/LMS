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

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

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
    JOIN courses c ON a.course_id = c.id
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ? AND a.due_date BETWEEN ? AND ?
    ORDER BY a.due_date
");
$stmt->bind_param("iss", $teacher_id, $start_date, $end_date);
$stmt->execute();
$assignments_result = $stmt->get_result();

// Create an array of assignments indexed by day
$assignments_by_day = [];
while ($row = $assignments_result->fetch_assoc()) {
    $day = intval(date('j', strtotime($row['due_date'])));
    if (!isset($assignments_by_day[$day])) {
        $assignments_by_day[$day] = [];
    }
    $assignments_by_day[$day][] = $row;
}
$stmt->close();

// Get upcoming assignments (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

$stmt = $conn->prepare("
    SELECT a.id, a.title, a.due_date, c.title AS course_title, c.course_code
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ? AND a.due_date BETWEEN ? AND ?
    ORDER BY a.due_date
    LIMIT 5
");
$stmt->bind_param("iss", $teacher_id, $today, $next_week);
$stmt->execute();
$upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Calendar - LMS</title>
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
        
        /* Calendar styles */
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
            background-color: #fff8e6;
        }
        .calendar .other-month {
            background-color: #f8f9fc;
            color: #aaa;
        }
        .calendar .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .assignment-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 5px;
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
        .assignment-info {
            position: absolute;
            display: none;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            z-index: 1000;
            width: 250px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .calendar-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .calendar-nav {
            display: flex;
            gap: 10px;
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
                    <a class="nav-link" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="calendar.php">
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
            <!-- Mobile Menu -->
            <div class="mobile-menu">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
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
                                    
                                    // Calculate the number of days we need to show from the previous month
                                    $prev_month_days = $first_day_of_week;
                                    
                                    // Calculate the number of rows needed
                                    $total_cells = $prev_month_days + $days_in_month;
                                    $total_rows = ceil($total_cells / 7);
                                    
                                    for ($i = 0; $i < $total_rows; $i++) {
                                        echo "<tr>";
                                        
                                        for ($j = 0; $j < 7; $j++) {
                                            $day_class = "";
                                            $day_content = "";
                                            
                                            if (($i == 0 && $j < $first_day_of_week) || ($current_day > $days_in_month)) {
                                                // Previous month or next month
                                                $day_class = "other-month";
                                                if ($i == 0 && $j < $first_day_of_week) {
                                                    // Previous month
                                                    $prev_month_day = date('j', strtotime('-' . ($first_day_of_week - $j) . ' days', $first_day));
                                                    $day_content = $prev_month_day;
                                                } else {
                                                    // Next month
                                                    $next_month_day = $current_day - $days_in_month;
                                                    $day_content = $next_month_day;
                                                    $current_day++;
                                                }
                                            } else {
                                                // Current month
                                                $day_content = $current_day;
                                                $current_date_compare = date('Y-m-d', mktime(0, 0, 0, $month, $current_day, $year));
                                                
                                                if ($current_date_compare == $current_date) {
                                                    $day_class = "today";
                                                }
                                                
                                                // Add assignments for this day
                                                if (isset($assignments_by_day[$current_day])) {
                                                    $assignments = $assignments_by_day[$current_day];
                                                    foreach ($assignments as $assignment) {
                                                        $color = sprintf('#%06X', mt_rand(0, 0xAAAAAA));
                                                        $day_content .= '<div class="assignment-item" style="background-color: ' . $color . '20; border-left: 3px solid ' . $color . ';">';
                                                        $day_content .= '<a href="assignment_details.php?id=' . $assignment['id'] . '" title="' . htmlspecialchars($assignment['title']) . '">';
                                                        $day_content .= '<small>' . htmlspecialchars($assignment['course_code']) . '</small>: ' . htmlspecialchars(substr($assignment['title'], 0, 15)) . (strlen($assignment['title']) > 15 ? '...' : '');
                                                        $day_content .= '</a></div>';
                                                    }
                                                }
                                                
                                                $current_day++;
                                            }
                                            
                                            echo '<td class="' . $day_class . '">';
                                            echo '<div class="day-number">' . $day_content . '</div>';
                                            echo '</td>';
                                        }
                                        
                                        echo "</tr>";
                                        if ($current_day > $days_in_month) {
                                            break;
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
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
                                                    <span class="badge <?php echo strtotime($assignment['due_date']) < time() ? 'bg-danger' : 'bg-warning'; ?>">
                                                        <?php 
                                                        $due_date = new DateTime($assignment['due_date']);
                                                        $today = new DateTime();
                                                        
                                                        $interval = $today->diff($due_date);
                                                        if ($interval->invert) {
                                                            echo 'Overdue';
                                                        } else {
                                                            echo $interval->days == 0 ? 'Today' : 'Due in ' . $interval->days . ' day' . ($interval->days > 1 ? 's' : '');
                                                        }
                                                        ?>
                                                    </span>
                                                    <div class="small text-muted"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="assignments.php" class="btn btn-outline-primary">View All Assignments</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No upcoming assignments in the next 7 days.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Calendar Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Calendar Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="create_assignment.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i> Create New Assignment
                                </a>
                                <a href="assignments.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-tasks me-2"></i> Manage Assignments
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Legend -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Legend</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-warning text-white p-2 me-2 rounded" style="width: 20px; height: 20px;"></div>
                                <span>Upcoming Assignment</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-danger text-white p-2 me-2 rounded" style="width: 20px; height: 20px;"></div>
                                <span>Overdue Assignment</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bg-success text-white p-2 me-2 rounded" style="width: 20px; height: 20px;"></div>
                                <span>Completed Assignment</span>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Simple popup for assignment details
        const assignmentItems = document.querySelectorAll('.assignment-item');
        assignmentItems.forEach(item => {
            item.addEventListener('mouseenter', function(e) {
                const title = this.querySelector('a').getAttribute('title');
                const assignmentInfo = document.createElement('div');
                assignmentInfo.className = 'assignment-info';
                assignmentInfo.innerHTML = `<p><strong>${title}</strong></p>`;
                
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