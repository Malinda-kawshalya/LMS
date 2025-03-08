<?php
// courses_content.php - Display enrolled courses
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800">My Courses</h1>
        <p class="lead">View and manage all your enrolled courses.</p>
    </div>
</div>

<?php if (count($courses) > 0): ?>
    <div class="row">
        <?php foreach($courses as $course): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card course-card h-100">
                    <?php if ($course['image']): ?>
                        <img src="../uploads/courses/<?php echo $course['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                    <?php else: ?>
                        <div class="card-img-top bg-primary text-white text-center py-4">
                            <i class="fas fa-graduation-cap fa-3x"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small">
                            <?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>...
                        </p>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">
                                <i class="fas fa-book me-1"></i> <?php echo $course['module_count']; ?> modules
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-tasks me-1"></i> <?php echo $course['assignment_count']; ?> assignments
                            </small>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <?php 
                            $progress = calculateProgress($course['completed_assignments'], $course['assignment_count']); 
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small><?php echo $progress; ?>% complete</small>
                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                Continue
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i> 
                            Started: <?php echo date('M d, Y', strtotime($course['start_date'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card shadow">
        <div class="card-body text-center py-5">
            <i class="fas fa-graduation-cap fa-4x text-gray-300 mb-3"></i>
            <h4>You haven't enrolled in any courses yet</h4>
            <p class="mb-4">Browse available courses and start your learning journey today!</p>
            <a href="available_courses.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i>Browse Courses
            </a>
        </div>
    </div>
<?php endif; ?>