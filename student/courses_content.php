<div class="row mb-4">
    <div class="col">
        <h1 class="h3 fw-bold">My Courses</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Courses</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <?php if ($courses->num_rows > 0): ?>
        <?php while ($course = $courses->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 course-card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">
                                <i class="fas fa-book-open"></i> <?php echo $course['module_count']; ?> Modules
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-tasks"></i> <?php echo $course['assignment_count']; ?> Assignments
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                            View Course
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col">
            <div class="alert alert-info">
                You are not enrolled in any courses yet.
            </div>
        </div>
    <?php endif; ?>
</div>