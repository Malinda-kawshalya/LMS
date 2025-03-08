<?php
require_once '../includes/config.php';

try {
    // Check if status column exists
    $result = $conn->query("SHOW COLUMNS FROM courses LIKE 'status'");
    
    if ($result->num_rows === 0) {
        // Status column doesn't exist, create it
        $sql = "ALTER TABLE courses 
                ADD COLUMN status ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active' 
                AFTER description";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error adding status column: " . $conn->error);
        }
        echo "Status column added successfully\n";
    }
    
    // Update any NULL status values
    $sql = "UPDATE courses SET status = 'active' WHERE status IS NULL";
    if (!$conn->query($sql)) {
        throw new Exception("Error updating NULL status values: " . $conn->error);
    }
    
    echo "Database structure updated successfully";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    $conn->close();
}
?>