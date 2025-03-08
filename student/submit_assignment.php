<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user_id'];
$assignment_id = $_GET['id'] ?? 0;

// Fetch assignment details
$stmt = $conn->prepare("
    SELECT a.*, c.title AS course_name, m.name AS module_name, s.file_path AS submitted_file
    FROM assignments a
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE a.id = ?
");
$stmt->bind_param("ii", $student_id, $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
        $file_name = basename($_FILES["file"]["name"]);
        $file_path = "uploads/" . time() . "_" . $file_name;
        move_uploaded_file($_FILES["file"]["tmp_name"], $file_path);

        $stmt = $conn->prepare("INSERT INTO submissions (student_id, assignment_id, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $student_id, $assignment_id, $file_path);
        $stmt->execute();
        $stmt->close();

        header("Location: assignments.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h2>Submit Assignment: <?php echo htmlspecialchars($assignment['title']); ?></h2>
    <p>Course: <?php echo htmlspecialchars($assignment['course_name']); ?></p>
    <p>Module: <?php echo htmlspecialchars($assignment['module_name']); ?></p>
    <p>Due Date: <?php echo date("d M Y", strtotime($assignment['due_date'])); ?></p>

    <?php if ($assignment['submitted_file']): ?>
        <div class="alert alert-success">You have already submitted this assignment.</div>
        <p><strong>Submitted File:</strong> <a href="<?php echo $assignment['submitted_file']; ?>" download>Download</a></p>
    <?php else: ?>
        <form action="" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="file" class="form-label">Upload File:</label>
                <input type="file" class="form-control" name="file" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit Assignment</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
