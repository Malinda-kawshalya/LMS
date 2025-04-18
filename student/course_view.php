<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as module_count,
           e.enrollment_date
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE c.id = ? AND e.student_id = ?
");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect if not enrolled
if (!$course) {
    $_SESSION['error'] = "You are not enrolled in this course.";
    header("Location: available_courses.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-color-dark: #3a5ecf;
            --sidebar-width: 250px;
            --header-height: 60px;
            --content-padding: 20px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        
        /* Header Styles */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1030;
            padding: 0 20px;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-color);
            color: white;
            padding-top: calc(var(--header-height) + 20px);
            transition: all 0.3s;
            z-index: 1020;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 14px 20px;
            border-radius: 0;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 25px;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
        }
        
        /* Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: var(--content-padding);
            padding-top: calc(var(--header-height) + var(--content-padding));
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Course Header */
        .course-header {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1rem 0 rgba(58, 59, 69, 0.1);
        }
        
        /* Chat Section */
        .chat-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1rem 0 rgba(58, 59, 69, 0.1);
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .chat-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .chat-header h3 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }
        
        .chat-header i {
            color: var(--primary-color);
            margin-right: 10px;
            font-size: 1.25em;
        }
        
        #chat-box {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f9f9fc;
            border-radius: 8px;
            border: 1px solid #eaeaea;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 12px;
            position: relative;
            clear: both;
            word-break: break-word;
        }
        
        .student-message {
            background-color: var(--primary-color);
            color: white;
            float: right;
            border-bottom-right-radius: 0;
        }
        
        .teacher-message {
            background-color: #e9ecef;
            color: #333;
            float: left;
            border-bottom-left-radius: 0;
        }
        
        .sender {
            font-weight: 600;
            font-size: 0.85em;
            margin-bottom: 3px;
            display: block;
        }
        
        .content {
            line-height: 1.4;
        }
        
        .timestamp {
            font-size: 0.75em;
            opacity: 0.8;
            display: block;
            text-align: right;
            margin-top: 3px;
        }
        
        .chat-input-container {
            display: flex;
            margin-top: 15px;
            background-color: #f9f9fc;
            border-radius: 30px;
            padding: 5px;
            border: 1px solid #eaeaea;
        }
        
        #chat-message {
            border-radius: 30px;
            border: none;
            padding: 10px 15px;
            background-color: transparent;
        }
        
        #chat-message:focus {
            outline: none;
            box-shadow: none;
        }
        
        #send-message {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-color);
            margin-left: 5px;
            transition: all 0.2s;
        }
        
        #send-message:hover {
            background-color: var(--primary-color-dark);
        }
        
        /* Loading Animation */
        .loading-animation {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .loading-dots {
            display: flex;
        }
        
        .loading-dots span {
            width: 8px;
            height: 8px;
            margin: 0 4px;
            background-color: #aaa;
            border-radius: 50%;
            animation: loadingDots 1.4s infinite ease-in-out both;
        }
        
        .loading-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }
        
        .loading-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }
        
        @keyframes loadingDots {
            0%, 80%, 100% { 
                transform: scale(0);
            } 
            40% { 
                transform: scale(1.0);
            }
        }
        
        /* Utility classes */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        /* Responsive Design */
        @media (max-width: 991.98px) {
            :root {
                --sidebar-width: 0px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
                width: 250px;
            }
            
            .toggler-btn {
                display: block !important;
            }
        }
        
        /* Utility */
        .d-none {
            display: none !important;
        }
        
        .toggler-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="top-navbar d-flex align-items-center">
        <button class="toggler-btn me-3" id="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand" href="dashboard.php">LMS Portal</a>
        <div class="ms-auto d-flex align-items-center">
            <div class="me-3">
                <span class="d-none d-md-inline">Welcome, </span><?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?>
            </div>
            <a href="../logout.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sign-out-alt"></i>
                <span class="d-none d-md-inline ms-1">Logout</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="courses.php">
                    <i class="fas fa-book"></i> My Courses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="available_courses.php">
                    <i class="fas fa-plus-circle"></i> Enroll in Courses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="assignments.php">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="fas fa-calendar-alt"></i> Calendar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Course Header -->
        <div class="course-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0"><?php echo htmlspecialchars($course['title']); ?></h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
            <p class="text-muted"><?php echo htmlspecialchars($course['description']); ?></p>
        </div>

        <!-- Chat Section -->
        <div class="chat-container">
            <div class="chat-header">
                <i class="fas fa-comments"></i>
                <h3>Instructor Communication</h3>
            </div>
            <div id="chat-box">
                <!-- Messages will be loaded here -->
                <div class="loading-animation" id="loading-message">
                    <div class="loading-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
            <div class="chat-input-container">
                <input type="text" id="chat-message" class="form-control" placeholder="Type your message here...">
                <button id="send-message" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Firebase v8 Scripts -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-auth.js"></script>

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
        const userId = <?php echo json_encode($student_id); ?>;
        const userName = <?php echo json_encode($_SESSION['name'] ?? 'Student'); ?>;
        const userType = "student";

        // Reference to the chat messages in Firebase
        const messagesRef = firebase.database().ref(`chats/course_${courseId}/messages`);

        const chatBox = document.getElementById("chat-box");
        const chatMessageInput = document.getElementById("chat-message");
        const sendMessageButton = document.getElementById("send-message");
        const sidebarToggle = document.getElementById("sidebar-toggle");
        const sidebar = document.getElementById("sidebar");

        // Toggle sidebar on mobile
        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("show");
        });

        // Function to format timestamp
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Load messages in real-time
        messagesRef.on("child_added", (snapshot) => {
            const message = snapshot.val();
            const isStudent = message.sender_type === "student";
            const isMine = isStudent && message.sender_id == userId;

            // Remove loading message if present
            const loadingElement = document.getElementById("loading-message");
            if (loadingElement) {
                loadingElement.remove();
            }

            // Create message container
            const messageContainer = document.createElement("div");
            messageContainer.className = "clearfix";

            // Create message element
            const messageElement = document.createElement("div");
            messageElement.className = `message ${isMine ? 'student-message' : 'teacher-message'}`;

            const senderName = isMine ? "You" : (isStudent ? message.sender_name : "Instructor");

            // Set message content
            messageElement.innerHTML = `
                <span class="sender">${senderName}</span>
                <span class="content">${message.message}</span>
                <span class="timestamp">${formatTimestamp(message.timestamp)}</span>
            `;

            messageContainer.appendChild(messageElement);
            chatBox.appendChild(messageContainer);
            chatBox.scrollTop = chatBox.scrollHeight; // Auto-scroll to the bottom
        });

        // Handle errors when loading messages
        messagesRef.once("value").catch((error) => {
            console.error("Database error:", error);
            const loadingElement = document.getElementById("loading-message");
            if (loadingElement) {
                loadingElement.innerHTML = `<div class="text-danger">Error loading messages. Please check your connection.</div>`;
            }
        });

        // Send a message
        function sendMessage() {
            const message = chatMessageInput.value.trim();
            if (message) {
                messagesRef.push({
                    sender_id: userId,
                    sender_name: userName,
                    sender_type: userType,
                    message: message,
                    timestamp: Date.now()
                }).then(() => {
                    chatMessageInput.value = ""; // Clear the input
                }).catch(error => {
                    console.error("Error sending message:", error);
                    alert("Failed to send message. Please try again.");
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

        // Handle window resize for responsive design
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove("show");
            }
        });

        // Show loading indicator initially
        // This is already added in HTML as static content
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>