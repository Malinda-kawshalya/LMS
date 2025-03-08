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

// Set the month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Ensure valid month/year values
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Get the first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$month_name = date('F', $first_day);
$days_in_month = date('t', $first_day);
$start_day = date('w', $first_day); // 0 (Sunday) to 6 (Saturday)

// Get previous and next month links
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

// Get calendar events
$events = array_fill(1, $days_in_month, []);

// Get assignments
$stmt = $conn->prepare("
    SELECT a.id, a.title, a.due_date, c.title as course_name, c.id as course_id
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    AND MONTH(a.due_date) = ?
    AND YEAR(a.due_date) = ?
");
$stmt->bind_param("iii", $student_id, $month, $year);
$stmt->execute();
$assignments = $stmt->get_result();
$stmt->close();

while ($assignment = $assignments->fetch_assoc()) {
    $day = intval(date('j', strtotime($assignment['due_date'])));
    $events[$day][] = [
        'type' => 'assignment',
        'title' => $assignment['title'],
        'course' => $assignment['course_name'],
        'course_id' => $assignment['course_id'],
        'link' => "assignment_detail.php?id={$assignment['id']}",
        'color' => 'danger'
    ];
}

// Get course events (if you have an events table)
// This is an example SQL if you have an events table
$stmt = $conn->prepare("
    SELECT e.id, e.title, e.event_date, e.description, c.title as course_name, c.id as course_id
    FROM events e
    JOIN courses c ON e.course_id = c.id
    JOIN enrollments en ON c.id = en.course_id
    WHERE en.student_id = ?
    AND MONTH(e.event_date) = ?
    AND YEAR(e.event_date) = ?
");
$stmt->bind_param("iii", $student_id, $month, $year);
$stmt->execute();
$course_events = $stmt->get_result();
$stmt->close();

while ($event = $course_events->fetch_assoc()) {
    $day = intval(date('j', strtotime($event['event_date'])));
    $events[$day][] = [
        'type' => 'event',
        'title' => $event['title'],
        'course' => $event['course_name'],
        'course_id' => $event['course_id'],
        'link' => "course_detail.php?id={$event['course_id']}",
        'color' => 'primary'
    ];
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Enhanced Sidebar */
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding-top: 70px;
            background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
            color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .sidebar a:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #fff;
        }
        .sidebar .active {
            color: white;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 0;
            border-left: 4px solid #fff;
        }
        
        /* Main Content Area */
        .content {
            margin-left: 220px;
            padding: 25px;
            padding-top: 85px;
            background-color: #f8f9fc;
            min-height: 100vh;
        }
        
        /* Navigation Bar */
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1rem;
            z-index: 1030;
        }
        
        /* Calendar Styling */
        .calendar-header {
            background-color: #f1f3f9;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #4e73df;
        }
        .calendar-day {
            height: 130px;
            overflow-y: auto;
            transition: all 0.2s ease;
            position: relative;
            padding: 5px;
        }
        .calendar-day::-webkit-scrollbar {
            width: 4px;
        }
        .calendar-day::-webkit-scrollbar-thumb {
            background-color: rgba(78, 115, 223, 0.3);
            border-radius: 4px;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
            transform: scale(1.02);
            z-index: 10;
            box-shadow: 0 0.15rem 0.5rem rgba(58, 59, 69, 0.1);
        }
        .today {
            background-color: rgba(78, 115, 223, 0.15);
            border: 1px solid rgba(78, 115, 223, 0.3);
        }
        .day-number {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #5a5c69;
            position: relative;
            display: inline-block;
        }
        .today .day-number:after {
            content: '';
            position: absolute;
            height: 5px;
            width: 5px;
            background-color: #4e73df;
            border-radius: 50%;
            top: 0;
            right: -8px;
        }
        .calendar-event {
            padding: 4px 8px;
            margin-bottom: 5px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            display: block;
        }
        .calendar-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .event-assignment {
            background-color: #ffebee;
            border-left: 3px solid #f44336;
            color: #c62828;
        }
        .event-course {
            background-color: #e3f2fd;
            border-left: 3px solid #2196f3;
            color: #0d47a1;
        }
        .other-month {
            color: #b7b9cc;
            background-color: #f9f9fb;
        }
        
        /* Card Enhancements */
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
        }
    </style>
    </style>
</head>
<body>
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
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>" href="courses.php">
                        <i class="fas fa-book"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>" href="grades.php">
                        <i class="fas fa-chart-bar"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                        <i class="fas fa-envelope"></i> Messages
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Calendar</h1>
                
                <div class="btn-group">
                    <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="calendar.php" class="btn btn-outline-primary">Today</a>
                    <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-center"><?php echo $month_name . ' ' . $year; ?></h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered m-0">
                            <thead>
                                <tr class="calendar-header">
                                    <th class="text-center">Sunday</th>
                                    <th class="text-center">Monday</th>
                                    <th class="text-center">Tuesday</th>
                                    <th class="text-center">Wednesday</th>
                                    <th class="text-center">Thursday</th>
                                    <th class="text-center">Friday</th>
                                    <th class="text-center">Saturday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $day_count = 1;
                                $day_num = 1;
                                
                                // Create calendar rows
                                while ($day_num <= $days_in_month) {
                                    echo "<tr>";
                                    
                                    // Create 7 columns (days of the week)
                                    for ($i = 0; $i < 7; $i++) {
                                        if (($day_count <= $start_day && $day_num == 1) || ($day_num > $days_in_month)) {
                                            // Empty cell (before first day or after last day)
                                            echo '<td class="calendar-day other-month">&nbsp;</td>';
                                        } else {
                                            // Check if it's today
                                            $is_today = ($day_num == date('j') && $month == date('n') && $year == date('Y'));
                                            $today_class = $is_today ? 'today' : '';
                                            
                                            echo '<td class="calendar-day ' . $today_class . '">';
                                            
                                            // Day number
                                            echo '<div class="day-number">' . $day_num . '</div>';
                                            
                                            // Events for this day
                                            if (isset($events[$day_num]) && count($events[$day_num]) > 0) {
                                                foreach ($events[$day_num] as $event) {
                                                    $event_class = 'event-' . $event['type'];
                                                    echo '<a href="' . $event['link'] . '" class="calendar-event ' . $event_class . ' text-decoration-none">';
                                                    echo '<i class="fas ' . ($event['type'] == 'assignment' ? 'fa-tasks' : 'fa-calendar-day') . '"></i> ';
                                                    echo htmlspecialchars($event['title']) . '<br><small>' . htmlspecialchars($event['course']) . '</small>';
                                                    echo '</a>';
                                                }
                                            }
                                            
                                            echo '</td>';
                                            $day_num++;
                                        }
                                        $day_count++;
                                    }
                                    
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Calendar Legend</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <div class="calendar-event event-assignment me-2" style="width: 20px; height: 20px;"></div>
                                <span>Assignments</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <div class="calendar-event event-course me-2" style="width: 20px; height: 20px;"></div>
                                <span>Course Events</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events List -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Course</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Reset result pointers
                                $assignments->data_seek(0);
                                $course_events->data_seek(0);
                                
                                // Combine both types of events
                                $all_events = [];
                                
                                while ($assignment = $assignments->fetch_assoc()) {
                                    $all_events[] = [
                                        'date' => $assignment['due_date'],
                                        'title' => $assignment['title'],
                                        'course' => $assignment['course_name'],
                                        'course_id' => $assignment['course_id'],
                                        'type' => 'Assignment',
                                        'link' => "assignment_detail.php?id={$assignment['id']}",
                                        'badge_class' => 'danger'
                                    ];
                                }
                                
                                while ($event = $course_events->fetch_assoc()) {
                                    $all_events[] = [
                                        'date' => $event['event_date'],
                                        'title' => $event['title'],
                                        'course' => $event['course_name'],
                                        'course_id' => $event['course_id'],
                                        'type' => 'Course Event',
                                        'link' => "course_detail.php?id={$event['course_id']}",
                                        'badge_class' => 'primary'
                                    ];
                                }
                                
                                // Sort by date
                                usort($all_events, function($a, $b) {
                                    return strtotime($a['date']) - strtotime($b['date']);
                                });
                                
                                if (count($all_events) > 0) {
                                    foreach ($all_events as $event) {
                                        echo '<tr>';
                                        echo '<td>' . date('M d, Y', strtotime($event['date'])) . '</td>';
                                        echo '<td><a href="' . $event['link'] . '">' . htmlspecialchars($event['title']) . '</a></td>';
                                        echo '<td><a href="course_detail.php?id=' . $event['course_id'] . '">' . htmlspecialchars($event['course']) . '</a></td>';
                                        echo '<td><span class="badge bg-' . $event['badge_class'] . '">' . $event['type'] . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No events for this month</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>