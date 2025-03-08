<?php
session_start();
// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
include_once "./includes/db_connect.php";
include_once "./includes/functions.php";

// Get student information
$student_id = $_SESSION['user_id'];
$student = getStudentDetails($conn, $student_id);

// Handle profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $bio = $_POST['bio'];
    
    // Handle profile image upload
    $profile_image = $student['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = "../uploads/profile_images/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $student_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image = $target_file;
            } else {
                $update_error = "Failed to upload profile image.";
            }
        } else {
            $update_error = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
        }
    }
    
    if (empty($update_error)) {
        // Update student profile
        $result = updateStudentProfile($conn, $student_id, $name, $email, $phone, $bio, $profile_image);
        
        if ($result) {
            $update_success = true;
            // Refresh student data
            $student = getStudentDetails($conn, $student_id);
        } else {
            $update_error = "Failed to update profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LMS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js" defer></script>
</head>
<body>
    <?php include_once "../includes/student_header.php"; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-sidebar">
            <?php include_once "../includes/student_sidebar.php"; ?>
        </div>
        
        <div class="dashboard-content">
            <h1>My Profile</h1>
            
            <?php if ($update_success): ?>
                <div class="alert alert-success">
                    Profile updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if (!empty($update_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $update_error; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-image">
                        <?php if ($student['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Profile Image">
                        <?php else: ?>
                            <div class="profile-initial"><?php echo strtoupper(substr($student['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($student['name']); ?></h2>
                        <p><i class="icon-email"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                        <?php if (!empty($student['phone'])): ?>
                            <p><i class="icon-phone"></i> <?php echo htmlspecialchars($student['phone']); ?></p>
                        <?php endif; ?>
                        <p><i class="icon-id"></i> Student ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
                    </div>
                </div>
                
                <div class="profile-tabs">
                    <button class="tab-btn active" onclick="openProfileTab(event, 'profile-details')">Profile Details</button>
                    <button class="tab-btn" onclick="openProfileTab(event, 'edit-profile')">Edit Profile</button>
                </div>
                
                <div id="profile-details" class="profile-tab-content active">
                    <div class="detail-section">
                        <h3>About Me</h3>
                        <div class="content-box">
                            <?php if (!empty($student['bio'])): ?>
                                <?php echo nl2br(htmlspecialchars($student['bio'])); ?>
                            <?php else: ?>
                                <p class="text-muted">No bio added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3>Student Information</h3>
                        <table class="detail-table">
                            <tr>
                                <th>Department:</th>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                            </tr>
                            <tr>
                                <th>Program:</th>
                                <td><?php echo htmlspecialchars($student['program']); ?></td>
                            </tr>
                            <tr>
                                <th>Year Level:</th>
                                <td><?php echo htmlspecialchars($student['year_level']); ?></td>
                            </tr>
                            <tr>
                                <th>Enrollment Date:</th>
                                <td><?php echo date('F j, Y', strtotime($student['enrollment_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><span class="status-badge <?php echo strtolower($student['status']); ?>"><?php echo htmlspecialchars($student['status']); ?></span></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="detail-section">
                        <h3>Contact Information</h3>
                        <table class="detail-table">
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'Not provided'; ?></td>
                            </tr>
                            <tr>
                                <th>Address:</th>
                                <td><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : 'Not provided'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div id="edit-profile" class="profile-tab-content">
                    <form action="profile.php" method="post" enctype="multipart/form-data" class="edit-profile-form">
                        <div class="form-group">
                            <label for="profile_image">Profile Image</label>
                            <div class="profile-image-upload">
                                <div class="current-image">
                                    <?php if ($student['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Current Profile Image">
                                    <?php else: ?>
                                        <div class="profile-initial"><?php echo strtoupper(substr($student['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/gif" class="form-control">
                                <small class="form-text">Upload a new profile image (JPEG, PNG, or GIF)</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($student['name']); ?>" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($student['phone']); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">About Me</label>
                            <textarea name="bio" id="bio" rows="5" class="form-control"><?php echo htmlspecialchars($student['bio']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openProfileTab(evt, tabId) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('profile-tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tab buttons
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // Show the current tab content and add active class to button
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
    </script>
    
    <?php include_once "../includes/footer.php"; ?>
</body>
</html>