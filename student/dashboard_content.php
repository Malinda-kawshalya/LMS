<!-- Dashboard Content File -->
<h1 class="h3 mb-4">Dashboard</h1>

<!-- Welcome Card -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Welcome back, <?php echo htmlspecialchars($student['full_name']); ?>!</h5>
        <p class="card-text">Here's an overview of your learning progress and upcoming activities.</p>
    </div>
</div>

<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- My Courses -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">My Courses</h5>
                <a href="courses.php" class="btn btn-sm btn-primary menu-link" data-page="courses">View All</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($courses_result->num_rows > 0): ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                        <p class="card-text small"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?></p>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted"><?php echo $course['module_count']; ?> Modules</small>
                                            <small class="text-muted"><?php echo $course['assignment_count']; ?> Assignments</small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">Go to Course</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-center">You are not enrolled in any courses yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Assignments -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Upcoming Assignments</h5>
                <a href="assignments.php" class="btn btn-sm btn-primary menu-link" data-page="assignments">View All</a>
            </div>
            <div class="card-body">
                <?php if ($upcoming_assignments->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($assignment = $upcoming_assignments->fetch_assoc()): ?>
                            <a href="assignment_detail.php?id=<?php echo $assignment['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                    <?php 
                                    $due_date = strtotime($assignment['due_date']);
                                    $due_class = time() > $due_date ? 'overdue' : '';
                                    ?>
                                    <small class="deadline-date <?php echo $due_class; ?>">
                                        Due: <?php echo date('M d, Y', $due_date); ?>
                                        <?php if (time() > $due_date): ?>
                                            <span class="badge bg-danger ms-1">Overdue</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($assignment['course_name']); ?></p>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center">You don't have any upcoming assignments.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3><?php echo count($courses); ?></h3>
                            <p class="mb-0">Courses</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3><?php echo $upcoming_assignments->num_rows; ?></h3>
                            <p class="mb-0">Pending Assignments</p>
                        </div>
                    </div>
                    <div class="col-12">
                        <a href="grades.php" class="btn btn-outline-primary btn-sm w-100 menu-link" data-page="grades">View Your Grades</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>