<?php
session_start();
require_once '../includes/config.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

try {
    // Check if student is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    if (!isset($_POST['student_id']) || !isset($_POST['assignment_id'])) {
        throw new Exception('Missing required fields');
    }

    $student_id = (int)$_POST['student_id'];
    $assignment_id = (int)$_POST['assignment_id'];
    $submission_text = $_POST['submission_text'] ?? '';

    // Verify file upload
    if (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload is required');
    }

    $file = $_FILES['file_upload'];
    
    // Validate file type
    $allowed_types = ['pdf', 'doc', 'docx'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('Invalid file type. Only PDF, DOC, and DOCX files are allowed.');
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 10MB.');
    }

    // Create uploads directory
    $upload_dir = '../uploads/submissions/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $filename = uniqid('submission_') . '_' . $student_id . '_' . $assignment_id . '.' . $file_extension;
    $filepath = $upload_dir . $filename;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Error uploading file');
        }

        // Insert submission
        $stmt = $conn->prepare("
            INSERT INTO submissions (student_id, assignment_id, submission_text, file_path, submitted_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param("iiss", $student_id, $assignment_id, $submission_text, $filename);
        
        if (!$stmt->execute()) {
            throw new Exception('Error saving submission');
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Assignment submitted successfully']);

    } catch (Exception $e) {
        $conn->rollback();
        // Clean up uploaded file if it exists
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}