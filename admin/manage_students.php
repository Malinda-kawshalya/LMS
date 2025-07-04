<?php

session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
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

// Delete student function
if (isset($_GET['delete_id'])) {
    $student_id = $_GET['delete_id'];
    
    // Delete query
    $delete_query = "DELETE FROM students WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Student deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete student: " . $conn->error;
    }
    
    $stmt->close();
    header('location: manage_students.php');
    exit;
}

// Fetch all students
$query = "SELECT id, email, full_name, student_id, phone, program, created_at, status FROM students ORDER BY id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - LMS Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
            padding-top: 70px;
            background-color: #4e73df;
            color: white;
            width: 220px;
        }
        .content {
            margin-left: 220px;
            padding: 20px;
            padding-top: 80px;
        }
        .card {
            margin-bottom: 20px;
        }
        .module-row {
            background-color: #f8f9fc;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e8f0fe;
            color: #4e73df;
        }
        .nav-link {
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .nav-item {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">LMS Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_courses.php">
                    <i class="fas fa-book"></i> Manage Courses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="manage_teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white active" href="manage_students.php">
                    <i class="fas fa-user-graduate"></i> Manage Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Manage Students</h4>
                    <a href="add_newstudent.php" class="btn btn-light">
                        <i class="bi bi-plus-circle"></i> Add New Student
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $_SESSION['success']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Student ID</th>
                                    <th>Phone</th>
                                    <th>Program</th>
                                    <th>Date Added</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($student = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $student['full_name']; ?></td>
                                            <td><?= $student['email']; ?></td>
                                            <td><?= $student['student_id']; ?></td>
                                            <td><?= $student['phone'] ? $student['phone'] : 'N/A'; ?></td>
                                            <td><?= $student['program'] ? $student['program'] : 'N/A'; ?></td>
                                            <td><?= $student['created_at']; ?></td>
                                            <td>
                                                <?php if ($student['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="edit_student.php?id=<?= $student['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </a>
                                                <a href="manage_students.php?delete_id=<?= $student['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No students found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>