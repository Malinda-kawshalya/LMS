<?php


function getEnrolledCourses($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $courses;
}

function getRecentAssignments($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM assignments WHERE student_id = ? ORDER BY due_date DESC LIMIT 5");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $assignments;
}

function getUpcomingDeadlines($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM deadlines WHERE student_id = ? ORDER BY due_date ASC LIMIT 5");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $deadlines = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $deadlines;
}

function getRecentAnnouncements($conn) {
    $stmt = $conn->prepare("SELECT * FROM announcements ORDER BY date_posted DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $announcements;
}
function countPendingAssignments($conn, $student_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}
function updateStudentProfile($conn, $student_id, $name, $email, $phone, $bio, $profile_image) {
    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, bio = ?, profile_image = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $email, $phone, $bio, $profile_image, $student_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
function calculateOverallGrade($conn, $student_id) {
    $stmt = $conn->prepare("SELECT AVG(grade) as average_grade FROM grades WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $average_grade = $result->fetch_assoc()['average_grade'];
    $stmt->close();
    return $average_grade;
}
function getStudentRecentActivities($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM activities WHERE student_id = ? ORDER BY timestamp DESC LIMIT 10");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $activities;
}

function submitAssignment($conn, $assignment_id, $student_id, $file_name, $file_path, $submission_text) {
    $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_name, file_path, submission_text, submission_date) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $assignment_id, $student_id, $file_name, $file_path, $submission_text);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getPendingAssignments($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM assignments WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $assignments;
}

function getSubmittedAssignments($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM submissions WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $assignments;
}

function getAssignmentDetails($conn, $assignment_id, $student_id) {
    $stmt = $conn->prepare("SELECT a.*, s.id as submission_id, s.file_name as submission_file, s.file_path as submission_path, s.submission_text, s.submission_date, s.grade, s.feedback FROM assignments a LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ? WHERE a.id = ?");
    $stmt->bind_param("ii", $student_id, $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    return $assignment;
}
function getStudentDetails($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    return $student;
}




?>