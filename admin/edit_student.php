<?php

session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Check if ID parameter exists and is numeric
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid student ID";
    header("Location: manage_students.php");
    exit();
}

$student_id = intval($_GET['id']);

// Fetch student data
try {
    $query = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('i', $student_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Student not found";
        header("Location: manage_students.php");
        exit();
    }

    $student = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: manage_students.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid form submission";
        header("Location: manage_students.php");
        exit();
    }
    
    // Validate required fields
    $required_fields = ['full_name', 'email', 'student_id', 'status'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['error'] = "Please fill all required fields: " . implode(', ', $missing_fields);
    } else {
        // Sanitize input
        $name = trim($_POST['full_name']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $student_id_number = trim($_POST['student_id']);
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $program = isset($_POST['program']) ? trim($_POST['program']) : '';
        $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'inactive';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format";
        } else {
            try {
                // Check if email already exists (excluding current student)
                $check_query = "SELECT * FROM students WHERE email = ? AND id != ?";
                $check_stmt = $conn->prepare($check_query);
                if (!$check_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $check_stmt->bind_param('si', $email, $student_id);
                if (!$check_stmt->execute()) {
                    throw new Exception("Execute failed: " . $check_stmt->error);
                }
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $_SESSION['error'] = "Email already exists for another student!";
                } else {
                    // Check password
                    $password = $_POST['password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    if (!empty($password)) {
                        // Validate password match
                        if ($password !== $confirm_password) {
                            $_SESSION['error'] = "Passwords do not match!";
                        } 
                        // Check password complexity
                        elseif (strlen($password) < 8) {
                            $_SESSION['error'] = "Password must be at least 8 characters long";
                        } else {
                            // Password is valid, proceed with update
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_query = "UPDATE students SET full_name = ?, email = ?, student_id = ?, phone = ?, program = ?, 
                                            password = ?, status = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            if (!$update_stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }
                            $update_stmt->bind_param('sssssssi', $name, $email, $student_id_number, $phone, $program, $hashed_password, $status, $student_id);
                        }
                    } else {
                        // Update without changing password
                        $update_query = "UPDATE students SET full_name = ?, email = ?, student_id = ?, phone = ?, program = ?, 
                                        status = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        if (!$update_stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        $update_stmt->bind_param('ssssssi', $name, $email, $student_id_number, $phone, $program, $status, $student_id);
                    }
                    
                    if (!isset($_SESSION['error']) && $update_stmt->execute()) {
                        $_SESSION['success'] = "Student updated successfully!";
                        header("Location: manage_students.php");
                        exit();
                    } elseif (!isset($_SESSION['error'])) {
                        throw new Exception("Failed to update student: " . $update_stmt->error);
                    }
                    
                    if (isset($update_stmt)) {
                        $update_stmt->close();
                    }
                }
                
                $check_stmt->close();
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
    }
}

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - LMS Admin</title>
    
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
        .active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">LMS Admin</a>
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
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Student</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="editStudentForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($student['full_name']); ?>" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($student['email']); ?>" required maxlength="100">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Student ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="student_id" name="student_id" value="<?= htmlspecialchars($student['student_id']); ?>" required maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($student['phone']); ?>" maxlength="15" pattern="[0-9+\-\s()]*">
                                <small class="text-muted">Enter a valid phone number</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="program" class="form-label">Program</label>
                                <input type="text" class="form-control" id="program" name="program" value="<?= htmlspecialchars($student['program']); ?>" maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?= $student['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?= $student['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="8">
                                <small class="text-muted">Minimum 8 characters. Leave empty to keep current password.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editStudentForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Password match validation
            confirmPassword.addEventListener('input', function() {
                if (password.value !== this.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            password.addEventListener('input', function() {
                if (this.value !== confirmPassword.value && confirmPassword.value !== '') {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
                
                // Only validate password complexity if not empty
                if (this.value !== '' && this.value.length < 8) {
                    this.setCustomValidity('Password must be at least 8 characters long');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Submit validation
            form.addEventListener('submit', function(event) {
                if (password.value !== '' && password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        });
    </script>
</body>
</html>