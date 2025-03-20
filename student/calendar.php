<?php
session_start();
include '../config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Get events for the current month
$student_id = $_SESSION['student_id'];
$firstDay = date('Y-m-d', strtotime("$year-$month-01"));
$lastDay = date('Y-m-t', strtotime("$year-$month-01"));

$query = "SELECT e.*, c.course_name 
          FROM events e 
          JOIN course_enrollment ce ON e.course_id = ce.course_id 
          JOIN courses c ON e.course_id = c.course_id 
          WHERE ce.student_id = ? 
          AND e.event_date BETWEEN ? AND ?
          ORDER BY e.event_date";
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $student_id, $firstDay, $lastDay);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $eventDate = date('j', strtotime($row['event_date']));
    $events[$eventDate][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Calendar</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar th, .calendar td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .calendar th {
            background-color: #f2f2f2;
        }
        .today {
            background-color: #e6f7ff;
        }
        .event-date {
            background-color: #ffe6e6;
        }
        .event-list {
            min-height: 80px;
            font-size: 12px;
            text-align: left;
        }
        .event-item {
            margin-bottom: 5px;
            padding: 3px;
            background-color: #f8f9fa;
            border-radius: 3px;
        }
        .nav-month {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-4">
        <h2>Student Calendar</h2>
        
        <div class="nav-month">
            <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="btn btn-sm btn-primary">&lt; Previous Month</a>
            <span class="mx-3"><strong><?= date('F Y', strtotime("$year-$month-01")) ?></strong></span>
            <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="btn btn-sm btn-primary">Next Month &gt;</a>
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
                // Get the first day of the month
                $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
                $numberDays = date('t', $firstDayOfMonth);
                $dateComponents = getdate($firstDayOfMonth);
                $dayOfWeek = $dateComponents['wday'];
                
                // Create the calendar
                $calendar = "<tr>";
                
                // Add empty cells for days before the first day of the month
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    $calendar .= "<td></td>";
                }
                
                // Add days to the calendar
                $currentDay = 1;
                $today = date('j') == $currentDay && date('n') == $month && date('Y') == $year ? 'today' : '';
                
                while ($currentDay <= $numberDays) {
                    // If it's a new week, start a new row
                    if ($dayOfWeek == 7) {
                        $dayOfWeek = 0;
                        $calendar .= "</tr><tr>";
                    }
                    
                    $today = date('j') == $currentDay && date('n') == $month && date('Y') == $year ? 'today' : '';
                    $eventClass = isset($events[$currentDay]) ? 'event-date' : '';
                    $combinedClass = $today && $eventClass ? 'today event-date' : ($today ? 'today' : ($eventClass ? 'event-date' : ''));
                    
                    $calendar .= "<td class='$combinedClass'>";
                    $calendar .= "<div>$currentDay</div>";
                    
                    // Add events for this day
                    $calendar .= "<div class='event-list'>";
                    if (isset($events[$currentDay])) {
                        foreach ($events[$currentDay] as $event) {
                            $calendar .= "<div class='event-item'>";
                            $calendar .= "<strong>{$event['event_title']}</strong><br>";
                            $calendar .= "{$event['course_name']}<br>";
                            $calendar .= date('g:i A', strtotime($event['event_time']));
                            $calendar .= "</div>";
                        }
                    }
                    $calendar .= "</div>";
                    
                    $calendar .= "</td>";
                    
                    $currentDay++;
                    $dayOfWeek++;
                }
                
                // Complete the row of the last week in month, if necessary
                if ($dayOfWeek != 7) {
                    $remainingDays = 7 - $dayOfWeek;
                    for ($i = 0; $i < $remainingDays; $i++) {
                        $calendar .= "<td></td>";
                    }
                }
                
                $calendar .= "</tr>";
                echo $calendar;
                ?>
            </tbody>
        </table>
    </div>
    
   
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
</body>
</html>