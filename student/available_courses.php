<?php
session_start();
require_once '../includes/config.php';


// Check if status column exists
$check_query = "SHOW COLUMNS FROM courses LIKE 'status'";
$result = $conn->query($check_query);

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Base query with proper column references
$query = "SELECT c.*, 
         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS student_count,
         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND student_id = ?) AS is_enrolled
         FROM courses c 
         WHERE c.status = 'active'";

// Initialize parameters array with student_id
$params = array($student_id);
$types = "i";



// Pagination
$items_per_page = 9;
$current_page = max(1, $_GET['page'] ?? 1);
$offset = ($current_page - 1) * $items_per_page;

try {
    // Get total count for pagination
    $count_query = str_replace("c.*,", "COUNT(*) as count,", $query);
    $count_stmt = $conn->prepare($count_query);
    
    if (!$count_stmt) {
        throw new Exception("Error preparing count query: " . $conn->error);
    }
    
    // Fix bind_param reference issue
    $bindParams = array_merge(array($types), $params);
    $bindReferences = array();
    foreach($bindParams as $key => $value) {
        $bindReferences[$key] = &$bindParams[$key];
    }
    
    call_user_func_array(array($count_stmt, 'bind_param'), $bindReferences);
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['count'];
    $total_pages = ceil($total_items / $items_per_page);
    $count_stmt->close();

    // Add pagination to main query
    $query .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $items_per_page;
    $types .= "ii";

    // Get courses with fixed bind_param
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparing main query: " . $conn->error);
    }
    
    $bindParams = array_merge(array($types), $params);
    $bindReferences = array();
    foreach($bindParams as $key => $value) {
        $bindReferences[$key] = &$bindParams[$key];
    }
    
    call_user_func_array(array($stmt, 'bind_param'), $bindReferences);
    $stmt->execute();
    $available_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


} catch (Exception $e) {
    error_log("Error in available_courses.php: " . $e->getMessage());
    $error_message = "An error occurred while fetching courses. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-light: #cfe2ff;
            --secondary-color: #6c757d;
            --success-color: #198754;
        }
        
        .course-card {
            transition: all 0.3s ease;
            height: 100%;
            border-radius: 12px;
            overflow: hidden;
            border: none;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
        }
        
        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0.75rem 1.5rem rgba(13, 110, 253, 0.2);
        }
        
        .card-img-top {
            height: 180px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color), #4e73df);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.75rem;
        }
        
        .badge {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
        }
        
        .btn-sm {
            border-radius: 50px;
            font-weight: 500;
            padding: 0.375rem 1rem;
            transition: all 0.2s ease;
        }
        
        .btn-sm:hover {
            transform: scale(1.05);
        }
        
        .container.py-5 {
            padding-top: 2.5rem !important;
            padding-bottom: 2.5rem !important;
        }
        
        .pagination .page-link {
            border-radius: 4px;
            margin: 0 3px;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
   

    <div class="container py-5">
        <!-- Error Message Display -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="display-5 mb-0">Available Courses</h1>
                <p class="lead">Browse and enroll in courses to enhance your skills.</p>
                <a href="dashboard.php" class="btn btn-secondary mb-4">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
        <div class="container py-5">
            
        <!-- Error Message Display -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Session Message Display (Add this section) -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>" role="alert">
                <?php echo $_SESSION['message']['text']; ?>
            </div>
            <?php unset($_SESSION['message']); // Clear the message after displaying ?>
        <?php endif; ?>
        
    <div class="container py-5">



        <!-- Course Listing -->
        <?php if (count($available_courses) > 0): ?>
            <div class="row">
                <?php foreach($available_courses as $course): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card h-100">
                            <div class="card-img-top bg-primary text-white text-center py-4">
                                <i class="fas fa-graduation-cap fa-3x"></i>
                            </div>
                            <div class="card-body">
                                <div class="badge bg-<?php echo isset($course['is_enrolled']) && $course['is_enrolled'] ? 'success' : 'info'; ?> mb-2">
                                    <?php echo isset($course['is_enrolled']) && $course['is_enrolled'] ? 'Enrolled' : 'Available'; ?>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?><br>
                                    <strong>Credit Hours:</strong> <?php echo $course['credit_hours']; ?><br>
                                    <?php echo isset($course['description']) ? substr(htmlspecialchars($course['description']), 0, 100) . '...' : 'No description available'; ?>
                                </p>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-tag me-1"></i> 
                                        <?php echo isset($course['category']) ? htmlspecialchars($course['category']) : 'General'; ?>
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-users me-1"></i> 
                                        <?php echo isset($course['student_count']) ? $course['student_count'] : 0; ?> students
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i> 
                                        Added: <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                                    </small>
                                    <?php if (isset($course['is_enrolled']) && $course['is_enrolled']): ?>
                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-success">
                                            Continue
                                        </a>
                                    <?php else: ?>
                                        <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                            Enroll Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page-1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']) : ''; ?>" tabindex="-1" aria-disabled="<?php echo ($current_page <= 1) ? 'true' : 'false'; ?>">Previous</a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page+1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']) : ''; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- No courses found message -->
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No courses available</h4>
                    <p>
                        <?php if(isset($_GET['search']) || isset($_GET['category'])): ?>
                            No courses match your search criteria. Try adjusting your filters.
                            <br><br>
                            <a href="available_courses.php" class="btn btn-outline-primary">View All Courses</a>
                        <?php else: ?>
                            Check back later for new course offerings.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Optional JavaScript for enhanced interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any additional JavaScript functionality here
            console.log('Page loaded');
        });
    </script>
</body>
</html>