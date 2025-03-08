<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .signup-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .signup-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .signup-title {
            text-align: center;
            margin-bottom: 20px;
            color: #3a3a3a;
        }
        .btn-signup {
            background-color: #4e73df;
            border-color: #4e73df;
            width: 100%;
        }
        .signup-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="signup-logo">
                <h2>LMS</h2>
            </div>
            <h4 class="signup-title">Create Your Account</h4>
            
            <?php
            // Display error or success messages if any
            session_start();
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            
            <form action="signup_process.php" method="post">
                <div class="mb-3">
                    <label for="userType" class="form-label">Register As *</label>
                    <select class="form-select" id="userType" name="userType" required>
                        <option value="">Select User Type</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fullName" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="fullName" name="fullName" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="confirmPassword" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    </div>
                </div>
                
                <!-- Student-specific fields, shown only when Student is selected -->
                <div id="studentFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="studentId" class="form-label">Student ID *</label>
                            <input type="text" class="form-control" id="studentId" name="studentId">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="program" class="form-label">Program/Major</label>
                            <input type="text" class="form-control" id="program" name="program">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch" class="form-label">Batch/Year</label>
                        <input type="text" class="form-control" id="batch" name="batch">
                    </div>
                </div>
                
                <!-- Teacher-specific fields, shown only when Teacher is selected -->
                <div id="teacherFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <input type="text" class="form-control" id="department" name="department">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="termsCheck" required>
                    <label class="form-check-label" for="termsCheck">I agree to the terms and conditions</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-signup">Sign Up</button>
            </form>
            
            <div class="signup-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide role-specific fields based on selection
        document.getElementById('userType').addEventListener('change', function() {
            const userType = this.value;
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');
            
            // Hide all role-specific fields first
            studentFields.style.display = 'none';
            teacherFields.style.display = 'none';
            
            // Show fields based on selection
            if (userType === 'student') {
                studentFields.style.display = 'block';
                document.getElementById('studentId').required = true;
                document.getElementById('department').required = false;
            } else if (userType === 'teacher') {
                teacherFields.style.display = 'block';
                document.getElementById('department').required = true;
                document.getElementById('studentId').required = false;
            }
        });
    </script>
</body>
</html>