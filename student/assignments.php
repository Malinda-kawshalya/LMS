<?php
// Start the session
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user_id'];

// Fetch assignments for enrolled courses
$sql = "
    SELECT a.*, c.title AS course_name, m.name AS module_name, s.id AS submission_id
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ?
    ORDER BY a.due_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$assignments = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    body {
        background-color: #f8f9fa;
        padding-top: 30px;
    }
    .content {
        margin-left: auto;
        margin-right: auto;
        max-width: 1000px;
        padding: 20px;
        transition: all 0.3s;
    }
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 15px 20px;
    }
    .table {
        margin-bottom: 0;
    }
    .badge {
        font-size: 0.85rem;
        padding: 6px 10px;
    }
    .btn-sm {
        border-radius: 5px;
    }
</style>
</head>
<body>



<div class="content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 fw-bold">Assignments</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Assignments</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Assignments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th>Module</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($assignments->num_rows > 0): ?>
                                <?php while ($row = $assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['module_name']); ?></td>
                                        <td><?php echo date("d M Y", strtotime($row['due_date'])); ?></td>
                                        <td>
                                            <?php if ($row['submission_id']): ?>
                                                <span class="badge bg-success rounded-pill">Submitted</span>
                                            <?php else: ?>
                                                <?php
                                                $due_date = new DateTime($row['due_date']);
                                                $now = new DateTime();
                                                if ($now > $due_date) {
                                                    echo '<span class="badge bg-danger rounded-pill">Overdue</span>';
                                                } else {
                                                    echo '<span class="badge bg-warning rounded-pill">Pending</span>';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="submit_assignment.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i> View / Submit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No assignments found.</td>
                                </tr>
                            <?php endif; ?>
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
