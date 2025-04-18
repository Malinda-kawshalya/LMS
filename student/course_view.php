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
    .chat-container {
        max-width: 600px;
        margin: 20px auto;
    }
    #chat-box {
        height: 300px;
        overflow-y: scroll;
        border: 1px solid #ccc;
        padding: 10px;
        background-color: #f9f9f9;
        border-radius: 5px;
    }
    .input-group {
        margin-top: 10px;
    }
    .message {
        margin-bottom: 8px;
        padding: 8px;
        border-radius: 5px;
    }
    .student-message {
        background-color: #e3f2fd;
        text-align: right;
        margin-left: 20%;
    }
    .teacher-message {
        background-color: #f1f1f1;
        text-align: left;
        margin-right: 20%;
    }
    .sender {
        font-weight: bold;
        margin-right: 5px;
    }
    .timestamp {
        font-size: 0.8em;
        color: #888;
        margin-left: 5px;
    }
</style>
</head>
<body>
    <div class="container py-5">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($course['title']); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($course['description']); ?></p>
        
        <div class="mb-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Chat Section -->
        <div class="chat-container">
            <h3>Chat with Teacher</h3>
            <div id="chat-box">
                <!-- Messages will be dynamically loaded here -->
            </div>
            <div class="input-group mt-3">
                <input type="text" id="chat-message" class="form-control" placeholder="Type a message...">
                <button id="send-message" class="btn btn-primary">Send</button>
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

    const messageElement = document.createElement("div");
    messageElement.className = `message ${isMine ? 'student-message' : 'teacher-message'}`;

    const senderName = isMine ? "You" : (isStudent ? "Student" : "Teacher");

    messageElement.innerHTML = `
        <span class="sender">${senderName}:</span>
        <span class="content">${message.message}</span>
        <span class="timestamp">${formatTimestamp(message.timestamp)}</span>
    `;

    chatBox.appendChild(messageElement);
    chatBox.scrollTop = chatBox.scrollHeight; // Auto-scroll to the bottom
});

// Handle errors when loading messages
messagesRef.once("value").catch((error) => {
    console.error("Database error:", error);
    alert("Error loading messages. Please check your connection and try again.");
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

// Show loading indicator
window.addEventListener('load', () => {
    const loadingMessage = document.createElement("div");
    loadingMessage.textContent = "Loading messages...";
    loadingMessage.id = "loading-message";
    loadingMessage.style.textAlign = "center";
    loadingMessage.style.color = "#888";
    chatBox.appendChild(loadingMessage);
    
    // Remove loading message once first message is loaded or after timeout
    const timeout = setTimeout(() => {
        const loadingElement = document.getElementById("loading-message");
        if (loadingElement) {
            loadingElement.remove();
        }
    }, 5000);
    
    messagesRef.once("value").then(() => {
        clearTimeout(timeout);
        const loadingElement = document.getElementById("loading-message");
        if (loadingElement) {
            loadingElement.remove();
        }
    });
});

    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>