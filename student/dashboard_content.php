<?php
// dashboard_content.php - Main dashboard content

// Calculate overall progress
$total_assignments = 0;
$completed_assignments = 0;

foreach($courses as $course) {
    $total_assignments += $course['assignment_count'];
    $completed_assignments += $course['completed_assignments'];
}

$overall_progress = calculateProgress($completed_assignments, $total_assignments);

// Get today's date
$today = date('Y-m-d');
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800">Welcome, <?php echo htmlspecialchars($student['full_name']); ?>!</h1>
        <p class="mb-0">Here's your learning progress and upcoming deadlines.</p>
    </div>
</div>

<!-- Dashboard Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Enrolled Courses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($courses); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-book fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Overall Progress</div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $overall_progress; ?>%</div>
                            </div>
                            <div class="col">
                                <div class="progress progress-sm mr-2">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $overall_progress; ?>%" 
                                         aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Completed Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $completed_assignments; ?> / <?php echo $total_assignments; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $pending_count = $upcoming_assignments->num_rows;
                            echo $pending_count; 
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Courses Section -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">My Courses</h6>
                <a href="courses.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($courses) > 0): ?>
                    <div class="row">
                        <?php foreach(array_slice($courses, 0, 2) as $course): ?>
                            <div class="col-md-12 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h5 class="card-title mb-0">
                                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($course['title']); ?>
                                                    </a>
                                                </h5>
                                                <small class="text-muted">
                                                    <?php echo $course['module_count']; ?> modules, 
                                                    <?php echo $course['assignment_count']; ?> assignments
                                                </small>
                                            </div>
                                        </div>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <?php 
                                            $progress = calculateProgress($course['completed_assignments'], $course['assignment_count']); 
                                            ?>
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small><?php echo $progress; ?>% complete</small>
                                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Continue Learning
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center my-4">
                        <i class="fas fa-book fa-3x text-gray-300 mb-3"></i>
                        <p>You are not enrolled in any courses yet.</p>
                        <a href="available_courses.php" class="btn btn-primary">Browse Courses</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Assignments Section -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Upcoming Assignments</h6>
                <a href="assignments.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if ($upcoming_assignments->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while($assignment = $upcoming_assignments->fetch_assoc()): ?>
                            <?php
                            $due_date = new DateTime($assignment['due_date']);
                            $now = new DateTime();
                            $interval = $now->diff($due_date);
                            $is_overdue = $now > $due_date;
                            $days_left = $interval->days;
                            
                            if ($is_overdue) {
                                $status_class = 'danger';
                                $status_text = 'Overdue';
                            } elseif ($days_left <= 1) {
                                $status_class = 'warning';
                                $status_text = 'Due Today';
                                if ($days_left == 1) $status_text = 'Due Tomorrow';
                            } else {
                                $status_class = 'info';
                                $status_text = 'Due in ' . $days_left . ' days';
                            }
                            ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <a href="#" class="view-assignment" data-assignment-id="<?php echo $assignment['id']; ?>">
                                            <?php echo htmlspecialchars($assignment['title']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($assignment['course_name']); ?>
                                    </small>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <small class="deadline-date">
                                        Due: <?php echo date('M d, Y, g:i A', strtotime($assignment['due_date'])); ?>
                                    </small>
                                    <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">Start</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center my-4">
                        <i class="fas fa-check-circle fa-3x text-gray-300 mb-3"></i>
                        <p>You don't have any pending assignments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Grades Section -->
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Grades</h6>
                <a href="grades.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if ($recent_grades->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Grade</th>
                                    <th>Feedback</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($grade = $recent_grades->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <a href="assignment_view.php?id=<?php echo $grade['assignment_id']; ?>">
                                                <?php echo htmlspecialchars($grade['assignment_title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($grade['course_name']); ?></td>
                                        <td>
                                            <?php
                                            $grade_value = $grade['grade'];
                                            $grade_class = 'secondary';
                                            
                                            if ($grade_value >= 90) {
                                                $grade_class = 'success';
                                            } elseif ($grade_value >= 80) {
                                                $grade_class = 'primary';
                                            } elseif ($grade_value >= 70) {
                                                $grade_class = 'info';
                                            } elseif ($grade_value >= 60) {
                                                $grade_class = 'warning';
                                            } else {
                                                $grade_class = 'danger';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $grade_class; ?>">
                                                <?php echo $grade_value; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $feedback = $grade['feedback'] ? substr($grade['feedback'], 0, 50) . '...' : 'No feedback provided';
                                            echo htmlspecialchars($feedback); 
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($grade['graded_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center my-4">
                        <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                        <p>No grades available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>