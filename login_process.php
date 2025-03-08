<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once './includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userType = $_POST['userType'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Debug input
    error_log("Login attempt - Type: $userType, Username: $username");
    
    if (empty($userType) || empty($username) || empty($password)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: login.php");
        exit();
    }

    try {
        if ($userType === "admin") {
            $query = "SELECT * FROM administrators WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $username, $username);
        } else {
            $table = ($userType === "student") ? "students" : "teachers";
            $query = "SELECT * FROM $table WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $username, $username);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Debug password check
            error_log("Stored hash: " . $user['password']);
            
            // For testing, temporarily log password comparison
            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $userType;

                if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $table = $userType === "admin" ? "administrators" : 
                            ($userType === "student" ? "students" : "teachers");
                    
                    $updateQuery = "UPDATE $table SET remember_token = ?, token_expiry = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("ssi", $token, $expiry, $user['id']);
                    $updateStmt->execute();
                    
                    setcookie('remember_user', $user['id'], time() + (86400 * 30), "/");
                    setcookie('remember_token', $token, time() + (86400 * 30), "/");
                    setcookie('remember_type', $userType, time() + (86400 * 30), "/");
                }

                header("Location: ./{$userType}/dashboard.php");
                exit();
            } else {
                error_log("Password verification failed");
                $_SESSION['error'] = "Invalid password!";
            }
        } else {
            error_log("No user found with username/email: $username");
            $_SESSION['error'] = "User not found!";
        }
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred during login.";
    } finally {
        if (isset($stmt)) $stmt->close();
    }
    
    header("Location: login.php");
    exit();
}

header("Location: login.php");
exit();
?>