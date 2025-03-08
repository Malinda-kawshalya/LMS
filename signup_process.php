<?php
// Start session
session_start();

// Database connection
require_once './includes/config.php'; // Adjust path if needed

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate user type
    if (empty($_POST['userType'])) {
        $_SESSION['error'] = "User type is required!";
        header("Location: signup.php");
        exit();
    }
    
    $userType = sanitize_input($_POST['userType']);
    
    // Validate basic info
    $fullName = sanitize_input($_POST['fullName']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Check for empty required fields
    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All required fields must be filled!";
        header("Location: signup.php");
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: signup.php");
        exit();
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long!";
        header("Location: signup.php");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: signup.php");
        exit();
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check for username or email duplicates across all user tables
    $tables = ['students', 'teachers', 'administrators'];
    $duplicate = false;
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($count > 0) {
            $duplicate = true;
            break;
        }
    }
    
    if ($duplicate) {
        $_SESSION['error'] = "Username or email already exists!";
        header("Location: signup.php");
        exit();
    }
    
    // Insert user based on role
    if ($userType === 'student') {
        // Additional student fields
        $studentId = sanitize_input($_POST['studentId'] ?? '');
        $program = sanitize_input($_POST['program'] ?? '');
        $batch = sanitize_input($_POST['batch'] ?? '');
        
        // Validate student ID
        if (empty($studentId)) {
            $_SESSION['error'] = "Student ID is required!";
            header("Location: signup.php");
            exit();
        }
        
        // Check if student ID already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($count > 0) {
            $_SESSION['error'] = "Student ID already exists!";
            header("Location: signup.php");
            exit();
        }
        
        // Insert into students table
        $stmt = $conn->prepare("INSERT INTO students (username, password, email, full_name, student_id, program, batch) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $hashedPassword, $email, $fullName, $studentId, $program, $batch);
        
    } elseif ($userType === 'teacher') {
        // Additional teacher fields
        $department = sanitize_input($_POST['department'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        
        // Validate department
        if (empty($department)) {
            $_SESSION['error'] = "Department is required!";
            header("Location: signup.php");
            exit();
        }
        
        // Insert into teachers table
        $stmt = $conn->prepare("INSERT INTO teachers (username, password, email, full_name, department, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $hashedPassword, $email, $fullName, $department, $phone);
        
    } else {
        $_SESSION['error'] = "Invalid user type!";
        header("Location: signup.php");
        exit();
    }
    
    // Execute the query
    if ($stmt->execute()) {
        $_SESSION['success'] = "Account created successfully! You can now log in.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Error creating account: " . $conn->error;
        header("Location: signup.php");
        exit();
    }
    
    $stmt->close();
    
} else {
    // If not a POST request, redirect to signup page
    header("Location: signup.php");
    exit();
}

// Close connection
$conn->close();
?>