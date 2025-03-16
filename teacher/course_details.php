<?php
// Start session
session_start();
include "../includes/db_connection.php";

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if course ID is provided
if (!isset($_GET['course_id'])) {
    header('Location: dashboard.php');
    exit();
}

$course_id = $_GET['course_id'];
$teacher_id = $_SESSION['teacher_id'];

// Fetch course details
$sql = "SELECT * FROM courses WHERE id = ? AND teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $course_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Course not found or doesn't belong to this teacher
    header('Location: dashboard.php');
    exit();
}

$course = $result->fetch_assoc();

// Fetch students enrolled in this course
$sql = "SELECT s.id, s.first_name, s.last_name, s.email 
        FROM students s
        JOIN enrollments e ON s.id = e.student_id
        WHERE e.course_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$students_result = $stmt->get_result();

// Fetch course materials
$sql = "SELECT * FROM course_materials WHERE course_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$materials_result = $stmt->get_result();

// Fetch assignments
$sql = "SELECT * FROM assignments WHERE course_id = ? ORDER BY due_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$assignments_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/font-awesome.js"></script>
</head>
<body>
    <?php include "includes/header.php"; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Course Details</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                        <a href="edit_course.php?id=<?php echo $course_id; ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-edit"></i> Edit Course
                        </a>
                    </div>
                    <div class="card-body">
                        <h5>Course Information</h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Code:</strong> <?php echo htmlspecialchars($course['code']); ?></p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($course['created_at'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php echo ($course['status'] == 'active') ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Course Materials Section -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Course Materials</h5>
                                    <a href="add_material.php?course_id=<?php echo $course_id; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i> Add Material
                                    </a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Added On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($materials_result->num_rows > 0): ?>
                                                <?php while ($material = $materials_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($material['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($material['type']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($material['created_at'])); ?></td>
                                                    <td>
                                                        <a href="view_material.php?id=<?php echo $material['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_material.php?id=<?php echo $material['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete_material.php?id=<?php echo $material['id']; ?>" class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Are you sure you want to delete this material?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No materials available for this course.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assignments Section -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Assignments</h5>
                                    <a href="add_assignment.php?course_id=<?php echo $course_id; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i> Add Assignment
                                    </a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Due Date</th>
                                                <th>Max Score</th>
                                                <th>Submissions</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($assignments_result->num_rows > 0): ?>
                                                <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                                        <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                            <span class="badge bg-danger">Passed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $assignment['max_score']; ?></td>
                                                    <td>
                                                        <?php 
                                                        // Count submissions
                                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ?");
                                                        $stmt->bind_param("i", $assignment['id']);
                                                        $stmt->execute();
                                                        $count_result = $stmt->get_result();
                                                        $submission_count = $count_result->fetch_row()[0];
                                                        echo $submission_count;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <a href="view_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-success btn-sm">
                                                            <i class="fas fa-list"></i> Submissions
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No assignments available for this course.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Students Section -->
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Enrolled Students (<?php echo $students_result->num_rows; ?>)</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($students_result->num_rows > 0): ?>
                                                <?php while ($student = $students_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                    <td>
                                                        <a href="student_progress.php?course_id=<?php echo $course_id; ?>&student_id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-chart-line"></i> Progress
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No students enrolled in this course yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include "includes/footer.php"; ?>
    
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>