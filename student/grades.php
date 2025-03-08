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

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = $_POST['assignment_id'];
    
    // Check if file is uploaded
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['assignment_file']['name'];
        $file_tmp = $_FILES['assignment_file']['tmp_name'];
        $file_size = $_FILES['assignment_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed file extensions
        $allowed_extensions = array('pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt');
        
        if (in_array($file_ext, $allowed_extensions)) {
            // Create directory if it doesn't exist
            $upload_dir = "../uploads/assignments/{$student_id}/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_file_name = uniqid() . '_' . $file_name;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Save submission to database
                $submission_text = $_POST['submission_text'];
                $result = submitAssignment($conn, $assignment_id, $student_id, $new_file_name, $upload_path, $submission_text);
                
                if ($result) {
                    $success_message = "Assignment submitted successfully!";
                } else {
                    $error_message = "Failed to submit assignment. Please try again.";
                }
            } else {
                $error_message = "Failed to upload file. Please try again.";
            }
        } else {
            $error_message = "Invalid file type. Allowed file types: " . implode(', ', $allowed_extensions);
        }
    } else if (isset($_POST['submission_text']) && !empty($_POST['submission_text'])) {
        // Text-only submission
        $submission_text = $_POST['submission_text'];
        $result = submitAssignment($conn, $assignment_id, $student_id, null, null, $submission_text);
        
        if ($result) {
            $success_message = "Assignment submitted successfully!";
        } else {
            $error_message = "Failed to submit assignment. Please try again.";
        }
    } else {
        $error_message = "Please upload a file or provide a text submission.";
    }
}

// Get assignments (pending and submitted)
$pending_assignments = getPendingAssignments($conn, $student_id);
$submitted_assignments = getSubmittedAssignments($conn, $student_id);

// Check for assignment detail request
$assignment_detail = null;
if (isset($_GET['id'])) {
    $assignment_id = $_GET['id'];
    $assignment_detail = getAssignmentDetails($conn, $assignment_id, $student_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - LMS</title>
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
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($assignment_detail): ?>
                <!-- Assignment Detail View -->
                <div class="assignment-detail">
                    <div class="back-link">
                        <a href="assignments.php">&larr; Back to Assignments</a>
                    </div>
                    
                    <h1><?php echo htmlspecialchars($assignment_detail['title']); ?></h1>
                    
                    <div class="assignment-meta">
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($assignment_detail['course_name']); ?></p>
                        <p><strong>Due Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($assignment_detail['due_date'])); ?></p>
                        <p><strong>Status:</strong> 
                            <?php if ($assignment_detail['submission_id']): ?>
                                <span class="status-badge submitted">Submitted</span>
                            <?php else: ?>
                                <?php if (strtotime($assignment_detail['due_date']) < time()): ?>
                                    <span class="status-badge overdue">Overdue</span>
                                <?php else: ?>
                                    <span class="status-badge pending">Pending</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($assignment_detail['submission_id']): ?>
                            <p><strong>Submission Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($assignment_detail['submission_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="assignment-description">
                        <h2>Instructions</h2>
                        <div class="content-box">
                            <?php echo nl2br(htmlspecialchars($assignment_detail['description'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($assignment_detail['file_path']): ?>
                        <div class="assignment-files">
                            <h2>Assignment Files</h2>
                            <div class="file-list">
                                <div class="file-item">
                                    <a href="<?php echo htmlspecialchars($assignment_detail['file_path']); ?>" target="_blank" class="file-link">
                                        <span class="file-name"><?php echo htmlspecialchars($assignment_detail['file_name']); ?></span>
                                        <span class="file-action">Download</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment_detail['submission_id']): ?>
                        <!-- Already submitted assignment -->
                        <div class="submission-details">
                            <h2>Your Submission</h2>
                            <?php if ($assignment_detail['submission_file']): ?>
                                <div class="file-list">
                                    <div class="file-item">
                                        <a href="<?php echo htmlspecialchars($assignment_detail['submission_path']); ?>" target="_blank" class="file-link">
                                            <span class="file-name"><?php echo htmlspecialchars($assignment_detail['submission_file']); ?></span>
                                            <span class="file-action">View</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($assignment_detail['submission_text']): ?>
                                <div class="submission-text">
                                    <h3>Submission Text</h3>
                                    <div class="content-box">
                                        <?php echo nl2br(htmlspecialchars($assignment_detail['submission_text'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($assignment_detail['grade']): ?>
                                <div class="submission-grade">
                                    <h3>Grade</h3>
                                    <div class="grade-display">
                                        <span class="grade-value"><?php echo $assignment_detail['grade']; ?></span>
                                        <span class="grade-max">/ <?php echo $assignment_detail['max_grade']; ?></span>
                                    </div>
                                    
                                    <?php if ($assignment_detail['feedback']): ?>
                                        <div class="feedback">
                                            <h3>Feedback</h3>
                                            <div class="content-box">
                                                <?php echo nl2br(htmlspecialchars($assignment_detail['feedback'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="submission-status">
                                    <p>Your submission is awaiting grading.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Submit assignment form -->
                        <div class="submission-form">
                            <h2>Submit Assignment</h2>
                            <form action="assignments.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment_detail['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="submission_text">Submission Text (optional)</label>
                                    <textarea name="submission_text" id="submission_text" rows="6" class="form-control"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="assignment_file">Upload File (optional)</label>
                                    <input type="file" name="assignment_file" id="assignment_file" class="form-control">
                                    <div class="file-help">
                                        <p>Allowed file types: PDF, DOC, DOCX, PPT, PPTX, ZIP, TXT</p>
                                    </div>
                                </div>
                                
                                <button type="submit" name="submit_assignment" class="btn-primary">Submit Assignment</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Assignments List View -->
                <h1>Assignments</h1>
                
                <div class="tab-container">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="openTab(event, 'pending-tab')">Pending</button>
                        <button class="tab-btn" onclick="openTab(event, 'submitted-tab')">Submitted</button>
                    </div>
                    
                    <div id="pending-tab" class="tab-content active">
                        <?php if (count($pending_assignments) > 0): ?>
                            <div class="assignment-list">
                                <?php foreach ($pending_assignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-info">
                                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                            <p class="assignment-course"><?php echo htmlspecialchars($assignment['course_name']); ?></p>
                                            <p class="assignment-due">
                                                Due: <?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?>
                                                <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                    <span class="status-badge overdue">Overdue</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="assignment-actions">
                                            <a href="assignments.php?id=<?php echo $assignment['id']; ?>" class="btn-secondary">View & Submit</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <h2>No pending assignments</h2>
                                <p>You have completed all your assignments. Great job!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="submitted-tab" class="tab-content">
                        <?php if (count($submitted_assignments) > 0): ?>
                            <div class="assignment-list">
                                <?php foreach ($submitted_assignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-info">
                                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                            <p class="assignment-course"><?php echo htmlspecialchars($assignment['course_name']); ?></p>
                                            <p class="assignment-submitted">
                                                Submitted: <?php echo date('F j, Y, g:i a', strtotime($assignment['submission_date'])); ?>
                                            </p>
                                            
                                            <?php if ($assignment['grade']): ?>
                                                <p class="assignment-grade">
                                                    Grade: <span class="grade"><?php echo $assignment['grade']; ?> / <?php echo $assignment['max_grade']; ?></span>
                                                </p>
                                            <?php else: ?>
                                                <p class="assignment-grade-pending">Awaiting Grade</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-actions">
                                            <a href="assignments.php?id=<?php echo $assignment['id']; ?>" class="btn-secondary">View Details</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <h2>No submitted assignments</h2>
                                <p>You haven't submitted any assignments yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabId) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
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