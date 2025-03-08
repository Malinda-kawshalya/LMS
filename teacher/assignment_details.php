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
// Check if assignment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to assignments page
    header("Location: assignments.php");
    exit();
}
$assignment_id = intval($_GET['id']);
// Check if the assignment belongs to the logged-in teacher
$stmt = $conn->prepare("
    SELECT a.*, c.id as course_id, c.title as course_title, c.course_code, m.title as module_title
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE a.id = ? AND c.teacher_id = ?
");
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Assignment not found or doesn't belong to this teacher
    $_SESSION['error'] = "Assignment not found or you don't have permission to access it.";
    header("Location: assignments.php");
    exit();
}

$assignment = $result->fetch_assoc();
$stmt->close();

// Get all submissions for this assignment
$stmt = $conn->prepare("
    SELECT s.*, st.first_name, st.last_name, st.email, st.student_id as student_number
    FROM submissions s
    JOIN students st ON s.student_id = st.id
    WHERE s.assignment_id = ?
    ORDER BY s.submission_date DESC
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$submissions = $stmt->get_result();
$stmt->close();

// Count total submissions
$total_submissions = $submissions->num_rows;

// Count graded submissions
$stmt = $conn->prepare("
    SELECT COUNT(*) as graded_count
    FROM submissions
    WHERE assignment_id = ? AND grade IS NOT NULL
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$graded_result = $stmt->get_result()->fetch_assoc();
$graded_submissions = $graded_result['graded_count'];
$stmt->close();

// Handle form submission for updating assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $due_date = $conn->real_escape_string($_POST['due_date']);
    $max_score = intval($_POST['max_score']);
    
    $stmt = $conn->prepare("
        UPDATE assignments
        SET title = ?, description = ?, due_date = ?, max_score = ?
        WHERE id = ? AND id IN (
            SELECT a.id FROM assignments a
            JOIN modules m ON a.module_id = m.id
            JOIN courses c ON m.course_id = c.id
            WHERE c.teacher_id = ?
        )
    ");
    $stmt->bind_param("sssiii", $title, $description, $due_date, $max_score, $assignment_id, $teacher_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Assignment updated successfully!";
        header("Location: view_assignment.php?id=" . $assignment_id);
        exit();
    } else {
        $_SESSION['error'] = "Failed to update assignment: " . $conn->error;
    }
    $stmt->close();
}

// Page title
$page_title = "View Assignment: " . $assignment['title'];
include '../includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
            <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $assignment['course_id']; ?>"><?php echo $assignment['course_code']; ?></a></li>
            <li class="breadcrumb-item"><a href="modules.php?course_id=<?php echo $assignment['course_id']; ?>"><?php echo $assignment['module_title']; ?></a></li>
            <li class="breadcrumb-item"><a href="assignments.php">Assignments</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $assignment['title']; ?></li>
        </ol>
    </nav>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Assignment Details</h4>
            <div>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editAssignmentModal">
                    <i class="fas fa-edit"></i> Edit Assignment
                </button>
                <a href="assignments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5><?php echo $assignment['title']; ?></h5>
                    <p class="text-muted">Course: <?php echo $assignment['course_title']; ?> (<?php echo $assignment['course_code']; ?>)</p>
                    <p class="text-muted">Module: <?php echo $assignment['module_title']; ?></p>
                    <div class="mt-4">
                        <h6>Description:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br($assignment['description']); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Due Date:</h6>
                            <p><?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?></p>
                            
                            <h6>Max Score:</h6>
                            <p><?php echo $assignment['max_score']; ?> points</p>
                            
                            <h6>Created On:</h6>
                            <p><?php echo date('F j, Y', strtotime($assignment['created_at'])); ?></p>
                            
                            <h6>Submissions:</h6>
                            <p><?php echo $total_submissions; ?> total (<?php echo $graded_submissions; ?> graded)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h4>Student Submissions</h4>
        </div>
        <div class="card-body">
            <?php if ($submissions->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Submission Date</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($submission = $submissions->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo $submission['first_name'] . ' ' . $submission['last_name']; ?>
                                        <br>
                                        <small class="text-muted"><?php echo $submission['student_number']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i a', strtotime($submission['submission_date'])); ?>
                                        <?php 
                                            $due_date = new DateTime($assignment['due_date']);
                                            $submit_date = new DateTime($submission['submission_date']);
                                            if ($submit_date > $due_date): 
                                        ?>
                                            <span class="badge badge-warning">Late</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['grade'] === NULL): ?>
                                            <span class="badge badge-secondary">Not Graded</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['grade'] !== NULL): ?>
                                            <?php echo $submission['grade']; ?> / <?php echo $assignment['max_score']; ?>
                                            (<?php echo round(($submission['grade'] / $assignment['max_score']) * 100); ?>%)
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="grade_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Grade
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No submissions for this assignment yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="editAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAssignmentModalLabel">Edit Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Assignment Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $assignment['title']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="6" required><?php echo $assignment['description']; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="max_score">Maximum Score</label>
                        <input type="number" class="form-control" id="max_score" name="max_score" value="<?php echo $assignment['max_score']; ?>" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_assignment" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php

$conn->close();
?>