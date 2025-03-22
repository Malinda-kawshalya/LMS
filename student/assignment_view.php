<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if we're trying to download a file
if (isset($_GET['download']) && isset($_GET['submission_id'])) {
    $submission_id = (int)$_GET['submission_id'];
    
    // Fetch submission details
    $stmt = $conn->prepare("SELECT * FROM submissions WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $submission_id, $_SESSION['user_id']);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$submission) {
        die("Invalid submission ID or you do not have permission to download this file.");
    }

    $file_path = '../uploads/submissions/' . $submission['file_path'];

    if (!file_exists($file_path)) {
        die("File not found.");
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

// Fetch assignment details
$stmt = $conn->prepare("
    SELECT a.*, m.title as module_title, c.title as course_title 
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if already submitted
$stmt = $conn->prepare("
    SELECT * FROM submissions 
    WHERE assignment_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$has_submitted = !empty($submission);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assignment['title']); ?> - Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
            --text-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            color: var(--text-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-top: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .assignment-detail {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaecf4;
        }
        
        .assignment-detail:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4e73df;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            display: none;
            z-index: 99;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        
        .back-to-top:hover {
            background: var(--accent-color);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        textarea:focus, input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .form-file-upload {
            border: 2px dashed #e2e8f0;
            padding: 25px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .form-file-upload:hover {
            border-color: var(--primary-color);
            background-color: rgba(78, 115, 223, 0.05);
        }

        .due-date {
            color: #e74a3b;
            font-weight: 600;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white px-4 mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Student Portal
            </a>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="form-container">
            <div class="page-header">
                <div>
                    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                    <p class="text-muted">
                        <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($assignment['course_title']); ?> | 
                        <i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($assignment['module_title']); ?>
                    </p>
                </div>
                <div>
                    <?php 
                    $now = new DateTime();
                    $due = new DateTime($assignment['due_date']);
                    $interval = $now->diff($due);
                    $is_late = $now > $due;
                    
                    if ($has_submitted): ?>
                        <span class="status-badge bg-success">
                            <i class="fas fa-check-circle me-1"></i> Submitted
                        </span>
                    <?php elseif ($is_late): ?>
                        <span class="status-badge bg-danger">
                            <i class="fas fa-exclamation-circle me-1"></i> Overdue
                        </span>
                    <?php else: ?>
                        <span class="status-badge bg-warning text-dark">
                            <i class="fas fa-clock me-1"></i> Pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4 shadow-sm">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Assignment Details</h6>
                    <div class="due-date">
                        <i class="fas fa-calendar-alt me-1"></i> Due: <?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?>
                        <?php if (!$is_late && !$has_submitted): 
                            $days_left = $interval->days;
                            $hours_left = $interval->h;
                            if ($days_left < 2): ?>
                                <span class="ms-2 badge bg-danger pulse">
                                    <?php 
                                    if ($days_left == 0 && $hours_left == 0) {
                                        echo "Due today!";
                                    } elseif ($days_left == 0) {
                                        echo "Only {$hours_left}h left!";
                                    } else {
                                        echo "Only {$days_left} day(s) left!";
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="assignment-detail">
                        <div class="detail-label">Description:</div>
                        <div class="mt-2"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></div>
                    </div>
                    
                    <?php if (isset($assignment['max_score'])): ?>
                    <div class="assignment-detail">
                        <div class="detail-label">Maximum Score:</div>
                        <div class="mt-1"><?php echo htmlspecialchars($assignment['max_score']); ?> points</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($has_submitted): ?>
                <div class="card border-left-success shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-success font-weight-bold mb-1">
                                    <i class="fas fa-check-circle me-1"></i> Assignment Submitted
                                </div>
                                <div class="mb-0 text-gray-800">Submitted on: <?php echo date('F j, Y, g:i a', strtotime($submission['submitted_at'])); ?></div>
                                
                                <?php if (isset($submission['grade'])): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <h5 class="text-primary">Grading Result:</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="h1 mb-0 me-3 fw-bold text-gray-800">
                                                <?php echo $submission['grade']; ?>/<?php echo $assignment['max_score']; ?>
                                            </div>
                                            <div>
                                                <?php 
                                                $score_percentage = ($submission['grade'] / $assignment['max_score']) * 100;
                                                if ($score_percentage >= 90): ?>
                                                    <div class="text-success">Excellent!</div>
                                                <?php elseif ($score_percentage >= 70): ?>
                                                    <div class="text-primary">Good job!</div>
                                                <?php elseif ($score_percentage >= 50): ?>
                                                    <div class="text-warning">Satisfactory</div>
                                                <?php else: ?>
                                                    <div class="text-danger">Needs improvement</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3 text-muted">
                                        <i class="fas fa-hourglass-half me-1"></i> Your submission is pending review
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-check fa-3x text-gray-300"></i>
                            </div>
                        </div>
                        
                        <!-- Add download link if file exists -->
                        <?php if (isset($submission['file_path']) && !empty($submission['file_path'])): ?>
                            <div class="mt-3 pt-3 border-top">
                                <a href="assignment_view.php?id=<?php echo $assignment_id; ?>&download=1&submission_id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-download me-1"></i> Download Your Submission
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Submit Your Assignment</h6>
                    </div>
                    <div class="card-body">
                        <form id="assignmentForm" enctype="multipart/form-data">
                            <input type="hidden" id="student_id" name="student_id" value="<?php echo $student_id; ?>">
                            <input type="hidden" id="assignment_id" name="assignment_id" value="<?php echo $assignment_id; ?>">
                            
                            <div class="mb-4">
                                <label for="submission_text" class="form-label fw-bold text-primary">Submission Notes:</label>
                                <textarea class="form-control" id="submission_text" name="submission_text" rows="5" 
                                          placeholder="Add any comments or notes about your submission here..."></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="file_upload" class="form-label fw-bold text-primary">Upload Assignment File:</label>
                                <div class="form-file-upload" id="dropZone">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                    <h5>Drag & Drop your file here</h5>
                                    <p class="text-muted">or</p>
                                    <input type="file" class="form-control d-none" id="file_upload" name="file_upload">
                                    <button type="button" class="btn btn-outline-primary" id="browseBtn">
                                        <i class="fas fa-folder-open me-1"></i> Browse Files
                                    </button>
                                    <div class="form-text mt-3">Accepted file types: PDF, DOC, DOCX (Max size: 10MB)</div>
                                </div>
                                <div id="fileDetails" class="d-none alert alert-info">
                                    <i class="fas fa-file-alt me-2"></i>
                                    <span id="fileName">No file selected</span>
                                    <button type="button" class="btn btn-sm text-danger float-end" id="removeFile">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-outline-secondary me-md-2" onclick="location.href='dashboard.php'">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Assignment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div id="statusMessage" class="alert mt-3" style="display: none;"></div>
        </div>
    </div>

    <!-- Back to top button -->
    <a href="#" class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </a>

    <script>
    // Back to top button
    window.onscroll = function() {
        if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
            document.getElementById('backToTop').style.display = 'block';
        } else {
            document.getElementById('backToTop').style.display = 'none';
        }
    };
    
    document.getElementById('backToTop')?.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
    
    // File upload handling
    const fileUpload = document.getElementById('file_upload');
    const browseBtn = document.getElementById('browseBtn');
    const dropZone = document.getElementById('dropZone');
    const fileDetails = document.getElementById('fileDetails');
    const fileName = document.getElementById('fileName');
    const removeFile = document.getElementById('removeFile');
    
    if (browseBtn) {
        browseBtn.addEventListener('click', function() {
            fileUpload.click();
        });
    }
    
    if (fileUpload) {
        fileUpload.addEventListener('change', function() {
            updateFileDetails();
        });
    }
    
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('bg-light');
        }
        
        function unhighlight() {
            dropZone.classList.remove('bg-light');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileUpload.files = files;
            updateFileDetails();
        }
    }
    
    if (removeFile) {
        removeFile.addEventListener('click', function() {
            fileUpload.value = '';
            fileDetails.classList.add('d-none');
            dropZone.classList.remove('d-none');
        });
    }
    
    function updateFileDetails() {
        if (fileUpload.files.length > 0) {
            const file = fileUpload.files[0];
            fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            fileDetails.classList.remove('d-none');
            dropZone.classList.add('d-none');
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(1) + ' MB';
    }
    
    // Form submission
    document.getElementById('assignmentForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const statusMessage = document.getElementById('statusMessage');
        const submitButton = this.querySelector('button[type="submit"]');
        
        // Validate file upload
        if (fileUpload.files.length === 0) {
            statusMessage.style.display = 'block';
            statusMessage.className = 'alert alert-warning mt-3';
            statusMessage.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please upload a file before submitting.';
            return;
        }
        
        // Disable submit button while processing
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        
        // Display loading message
        statusMessage.style.display = 'block';
        statusMessage.className = 'alert alert-info mt-3';
        statusMessage.innerHTML = '<i class="fas fa-sync fa-spin me-2"></i>Uploading your assignment. Please wait...';
        
        fetch('submit_assignment.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            throw new Error('Response was not JSON: ' + await response.text());
        })
        .then(data => {
            statusMessage.style.display = 'block';
            if (data.success) {
                statusMessage.className = 'alert alert-success mt-3';
                statusMessage.innerHTML = '<i class="fas fa-check-circle me-2"></i>Assignment submitted successfully! Redirecting...';
                setTimeout(() => window.location.reload(), 2000);
            } else {
                statusMessage.className = 'alert alert-danger mt-3';
                statusMessage.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error: ' + data.message;
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Assignment';
            }
        })
        .catch(error => {
            statusMessage.style.display = 'block';
            statusMessage.className = 'alert alert-danger mt-3';
            statusMessage.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error submitting assignment. Please try again. Details: ' + error.message;
            console.error('Error:', error);
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Assignment';
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>