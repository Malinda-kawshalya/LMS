<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Get courses taught by this teacher using teacher-courses table
$stmt = $conn->prepare("
    SELECT c.id, c.title 
    FROM courses c
    JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ? 
    ORDER BY c.title
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get current course details if selected
$current_course = null;
$students = [];
if ($course_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.title 
        FROM courses c
        JOIN teacher_courses tc ON c.id = tc.course_id
        WHERE c.id = ? AND tc.teacher_id = ?
    ");
    $stmt->bind_param("ii", $course_id, $teacher_id);
    $stmt->execute();
    $current_course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get students enrolled in this course
    if ($current_course) {
        $stmt = $conn->prepare("
            SELECT s.id, s.full_name  
            FROM students s
            JOIN enrollments e ON s.id = e.student_id
            WHERE e.course_id = ?
            ORDER BY s.full_name
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Get current student details
$current_student = null;
if ($student_id > 0 && $course_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, full_name FROM students 
        WHERE id = ? AND
        EXISTS (SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?)
    ");
    $stmt->bind_param("iii", $student_id, $student_id, $course_id);
    $stmt->execute();
    $current_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Chat - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5ccc;
            --secondary-color: #f8f9fc;
            --text-color: #5a5c69;
            --border-color: #e3e6f0;
            --student-message-bg: #e3f2fd;
            --teacher-message-bg: #e8f5e9;
        }
        
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 20px;
        }
        
        /* Sidebar Styles */
        #wrapper {
            display: flex;
        }
        
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: var(--primary-color);
            color: #fff;
            transition: all 0.3s;
            height: 100vh;
            position: fixed;
            z-index: 1000;
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: var(--primary-dark);
        }
        
        #sidebar .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid var(--primary-dark);
        }
        
        #sidebar ul li a {
            padding: 10px 20px;
            font-size: 1.1em;
            display: block;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        #sidebar ul li a:hover,
        #sidebar ul li a.active {
            color: #fff;
            background: var(--primary-dark);
        }
        
        #sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
            margin-left: 250px;
        }
        
        /* Toggle Button */
        #sidebarCollapse {
            background: var(--primary-color);
            color: #fff;
            border: none;
        }
        
        /* For smaller screens */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            
            #sidebar.active {
                margin-left: 0;
            }
            
            #content {
                margin-left: 0px;
            }
            
            #content.active {
                margin-left: 250px;
            }
            
            #sidebarCollapse span {
                display: none;
            }
        }
        
        /* Chat Box Styles */
        .chat-container {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background-color: #fff;
            overflow: hidden;
        }
        
        .chat-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        #chat-box {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f9f9fc;
            scroll-behavior: smooth;
        }
        
        .message {
            margin-bottom: 12px;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 80%;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .student-message {
            background-color: var(--student-message-bg);
            border-bottom-left-radius: 0;
            float: left;
            clear: both;
        }
        
        .teacher-message {
            background-color: var(--teacher-message-bg);
            border-bottom-right-radius: 0;
            float: right;
            clear: both;
        }
        
        .sender {
            font-weight: 600;
            margin-bottom: 3px;
            display: block;
            font-size: 0.85rem;
        }
        
        .content {
            word-break: break-word;
        }
        
        .timestamp {
            font-size: 0.75rem;
            color: #888;
            display: block;
            text-align: right;
            margin-top: 3px;
        }
        
        .chat-input {
            padding: 15px;
            background-color: #fff;
            border-top: 1px solid var(--border-color);
        }
        
        .chat-input .input-group {
            box-shadow: 0 0.125rem 0.25rem 0 rgba(58, 59, 69, 0.2);
            border-radius: 25px;
            overflow: hidden;
        }
        
        .chat-input input {
            border-radius: 25px 0 0 25px;
            border: 1px solid #e3e6f0;
            padding-left: 20px;
        }
        
        .chat-input button {
            border-radius: 0 25px 25px 0;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .course-selection {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .form-select {
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        
        .clear-fix {
            clear: both;
        }
        
        /* Select2 custom styling */
        .select-container {
            border-radius: 5px;
            overflow: hidden;
        }
        
        .alert {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .navbar {
            padding: 10px 20px;
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="wrapper" id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">


            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li>
                    <a href="assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li>
                    <a href="calendar.php">
                        <i class="fas fa-calendar-alt"></i> Calendar
                    </a>
                </li>
                <li>
                    <a href="#" class="active">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">

            <div class="container-fluid">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1>Teacher Chat</h1>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Course Selection -->
                <div class="course-selection">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="course-select" class="form-label">Select Course</label>
                            <form method="get" class="d-flex">
                                <select id="course-select" name="course_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($student_id) && $student_id > 0): ?>
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if ($course_id > 0 && !empty($students)): ?>
                        <div class="col-md-6">
                            <label for="student-select" class="form-label">Select Student</label>
                            <form method="get" class="d-flex">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <select id="student-select" name="student_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" <?php echo ($student_id == $student['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($course_id > 0 && $student_id > 0 && isset($current_student)): ?>
                    <!-- Chat Section -->
                    <div class="chat-container">
                        <div class="chat-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-circle me-2"></i>
                                    <?php echo htmlspecialchars($current_student['full_name'] ?? 'Unknown Student'); ?> 
                                    <small class="text-light opacity-75">(<?php echo htmlspecialchars($current_course['title']); ?>)</small>
                                </h5>
                            </div>
                        </div>
                        <div id="chat-box">
                            <!-- Messages will load here -->
                        </div>
                        <div class="chat-input">
                            <div class="input-group">
                                <input type="text" id="chat-message" class="form-control" placeholder="Type a message...">
                                <button id="send-message" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send
                                </button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($course_id > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Please select a student to view chat messages.
                    </div>
                <?php elseif (empty($courses)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> You don't have any courses assigned yet.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Please select a course to view student conversations.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Firebase Scripts -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

    <?php if ($course_id > 0 && $student_id > 0 && isset($current_student)): ?>
    <script>
        // Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyA2gTPjI6RbF5cM--O6hQDAiNge8xBYxsI",
            authDomain: "lms-chat-a4413.firebaseapp.com",
            databaseURL: "https://lms-chat-a4413-default-rtdb.firebaseio.com",
            projectId: "lms-chat-a4413",
            storageBucket: "lms-chat-a4413.firebasestorage.app",
            messagingSenderId: "829609086270",
            appId: "1:829609086270:web:45c0d173969ff7a00a306f",
            measurementId: "G-QB1Y6SQYBQ"
        };

        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        // Define variables
        const courseId = <?php echo json_encode($course_id); ?>;
        const teacherId = <?php echo json_encode($teacher_id); ?>;
        const teacherName = <?php echo json_encode($_SESSION['name'] ?? 'Teacher'); ?>;
        const studentId = <?php echo json_encode($student_id); ?>;
        const studentName = <?php echo json_encode($current_student['full_name'] ?? 'Student'); ?>;
        
        const chatBox = document.getElementById("chat-box");
        const chatMessageInput = document.getElementById("chat-message");
        const sendMessageButton = document.getElementById("send-message");

        // Reference to chat messages in Firebase
        const messagesRef = firebase.database().ref(`chats/course_${courseId}/messages`);

        // Format timestamp
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Load messages
        chatBox.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading messages...</p></div>';
        
        // Track if we've received any messages
        let messagesReceived = false;
        
        messagesRef.on("child_added", (snapshot) => {
            const message = snapshot.val();
            
            // Only show messages between this teacher and selected student
            if ((message.sender_type === "teacher" && message.sender_id == teacherId && 
                 (message.recipient_id == studentId || !message.recipient_id)) || 
                (message.sender_type === "student" && message.sender_id == studentId)) {
                
                // Remove loading message if it exists
                if (chatBox.innerHTML.includes('Loading messages')) {
                    chatBox.innerHTML = '';
                }
                
                messagesReceived = true;
                
                const isTeacher = message.sender_type === "teacher";
                const messageElement = document.createElement("div");
                messageElement.className = `message ${isTeacher ? 'teacher-message' : 'student-message'}`;
                
                messageElement.innerHTML = `
                    <span class="sender">${isTeacher ? 'You' : studentName}</span>
                    <span class="content">${message.message}</span>
                    <span class="timestamp">${formatTimestamp(message.timestamp)}</span>
                    <div class="clear-fix"></div>
                `;
                
                chatBox.appendChild(messageElement);
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });

        // Show an error if Firebase connection fails
        messagesRef.on("value", (snapshot) => {
            // This just establishes the connection to Firebase
        }, (error) => {
            chatBox.innerHTML = '<div class="alert alert-danger m-3"><i class="fas fa-exclamation-circle me-2"></i>Failed to connect to chat service. Please try refreshing the page.</div>';
            console.error("Firebase connection error:", error);
        });

        // Check if no messages after loading
        setTimeout(() => {
            if (!messagesReceived) {
                chatBox.innerHTML = '<div class="text-center py-5"><i class="fas fa-comments fa-3x text-muted mb-3"></i><p class="text-muted">No messages yet. Start a conversation!</p></div>';
            }
        }, 5000);

        // Send message
        function sendMessage() {
            const message = chatMessageInput.value.trim();
            if (message) {
                // Disable send button to prevent duplicate messages
                sendMessageButton.disabled = true;
                
                messagesRef.push({
                    sender_id: teacherId,
                    sender_name: teacherName,
                    sender_type: "teacher",
                    recipient_id: studentId,
                    message: message,
                    timestamp: Date.now()
                }).then(() => {
                    chatMessageInput.value = "";
                    sendMessageButton.disabled = false;
                    chatMessageInput.focus();
                }).catch(error => {
                    alert("Failed to send message. Please try again.");
                    console.error("Error sending message:", error);
                    sendMessageButton.disabled = false;
                });
            }
        }

        // Send button click
        sendMessageButton.addEventListener("click", sendMessage);

        // Send on Enter key
        chatMessageInput.addEventListener("keypress", (event) => {
            if (event.key === "Enter") {
                sendMessage();
            }
        });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on small screens
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('active');
        });
        
        // Automatically hide sidebar on small screens initially
        function adjustLayout() {
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('content').classList.remove('active');
            } else {
                document.getElementById('sidebar').classList.add('active');
                document.getElementById('content').classList.add('active');
            }
        }
        
        // Call on page load
        window.addEventListener('load', adjustLayout);
        // Call on resize
        window.addEventListener('resize', adjustLayout);
    </script>
</body>
</html>
<?php $conn->close(); ?>