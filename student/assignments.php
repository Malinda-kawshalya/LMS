<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

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

// Get assignments
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container py-4">
    <!-- Assignments List -->
    <div class="row">
        <div class="col-md-12">
            <?php if (empty($assignments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h4>No assignments found</h4>
                    <p class="text-muted">No assignments available at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="card-title mb-1">
                                        <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($assignment['title']); ?>
                                        </a>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        <?php echo htmlspecialchars($assignment['course_title']); ?> - 
                                        <?php echo htmlspecialchars($assignment['module_title']); ?>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted">
                                        <i class="fas fa-calendar"></i> Due: 
                                        <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-3 text-md-end">
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
                                        View Assignment
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
