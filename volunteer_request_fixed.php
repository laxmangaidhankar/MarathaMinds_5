<?php
require_once 'config/config.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'assets/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    // Sanitize inputs
    $full_name = sanitizeInput($_POST['full_name']);
    $age = intval($_POST['age']);
    $gender = sanitizeInput($_POST['gender']);
    $mobile = sanitizeInput($_POST['mobile']);
    $email = sanitizeInput($_POST['email']);
    $education = sanitizeInput($_POST['education']);
    $skills = sanitizeInput($_POST['skills']);
    $ngo_name = sanitizeInput($_POST['ngo_name']);
    $role = sanitizeInput($_POST['role']);
    $message = sanitizeInput($_POST['message']);
    
    // File uploads
    $passport_photo = '';
    $aadhaar_card = '';
    $school_certificate = '';
    
    // Upload passport photo
    if (!empty($_FILES['passport_photo']['name'])) {
        $upload_result = uploadFile($_FILES['passport_photo'], 'passport');
        if (isset($upload_result['file_path'])) {
            $passport_photo = $upload_result['file_path'];
        } else {
            $error = $upload_result['error'] ?? 'Failed to upload passport photo';
        }
    } else {
        $error = "Passport photo is required";
    }
    
    // Upload Aadhaar card
    if (!$error && !empty($_FILES['aadhaar_card']['name'])) {
        $upload_result = uploadFile($_FILES['aadhaar_card'], 'aadhaar');
        if (isset($upload_result['file_path'])) {
            $aadhaar_card = $upload_result['file_path'];
        } else {
            $error = $upload_result['error'] ?? 'Failed to upload Aadhaar card';
        }
    } else if (!$error) {
        $error = "Aadhaar card is required";
    }
    
    // Upload school certificate
    if (!$error && !empty($_FILES['school_certificate']['name'])) {
        $upload_result = uploadFile($_FILES['school_certificate'], 'certificate');
        if (isset($upload_result['file_path'])) {
            $school_certificate = $upload_result['file_path'];
        } else {
            $error = $upload_result['error'] ?? 'Failed to upload certificate';
        }
    } else if (!$error) {
        $error = "School certificate is required";
    }
    
    if (!$error) {
        // Check if email already exists
        $check_sql = "SELECT id FROM volunteer_requests WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Email already registered! Please wait for admin approval.";
        } else {
            // Insert into database
            $sql = "INSERT INTO volunteer_requests 
                    (full_name, age, gender, mobile_number, email, education, skills, 
                     passport_photo, aadhaar_card, school_certificate, ngo_name, role_position, request_message) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sississssssss", 
                $full_name, $age, $gender, $mobile, $email, $education, $skills,
                $passport_photo, $aadhaar_card, $school_certificate, $ngo_name, $role, $message);
            
            if (mysqli_stmt_execute($stmt)) {
                $request_id = mysqli_insert_id($conn);
                $success = "Your request has been submitted successfully! Request ID: VR-" . str_pad($request_id, 6, '0', STR_PAD_LEFT);
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Failed to submit request: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Volunteer Access - Sarathi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same CSS as before, but with file upload preview */
        :root {
            --primary: #2563eb;
            --secondary: #06D6A0;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .file-upload {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .file-preview {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--success);
        }
        
        .file-upload.has-file {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .file-upload.error {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            margin-top: 5px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto 0;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: var(--primary);">
                    <i class="fas fa-user-plus"></i> Volunteer Access Request
                </h1>
                <p style="color: var(--text-light);">
                    Complete this form to request volunteer access. All fields are mandatory.
                </p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong><?php echo $success; ?></strong>
                        <p style="margin-top: 5px; font-size: 0.95rem;">
                            Your NGO admin will review your request within 24-48 hours.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" id="volunteerForm">
                <!-- Personal Information Section -->
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--light-bg);">
                    <h2 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user"></i> Personal Information
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Age *</label>
                            <input type="number" name="age" min="18" max="80" class="form-control" 
                                   value="<?php echo isset($_POST['age']) ? $_POST['age'] : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Mobile Number *</label>
                            <input type="tel" name="mobile" pattern="[0-9]{10}" class="form-control" 
                                   value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" 
                                   placeholder="10-digit mobile number" required>
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Email Address *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <!-- Education & Skills Section -->
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--light-bg);">
                    <h2 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-graduation-cap"></i> Education & Skills
                    </h2>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Highest Education *</label>
                        <select name="education" class="form-control" required>
                            <option value="">Select Education</option>
                            <option value="High School" <?php echo (isset($_POST['education']) && $_POST['education'] == 'High School') ? 'selected' : ''; ?>>High School</option>
                            <option value="Diploma" <?php echo (isset($_POST['education']) && $_POST['education'] == 'Diploma') ? 'selected' : ''; ?>>Diploma</option>
                            <option value="Bachelor's Degree" <?php echo (isset($_POST['education']) && $_POST['education'] == 'Bachelor\'s Degree') ? 'selected' : ''; ?>>Bachelor's Degree</option>
                            <option value="Master's Degree" <?php echo (isset($_POST['education']) && $_POST['education'] == 'Master\'s Degree') ? 'selected' : ''; ?>>Master's Degree</option>
                            <option value="PhD" <?php echo (isset($_POST['education']) && $_POST['education'] == 'PhD') ? 'selected' : ''; ?>>PhD</option>
                            <option value="Other" <?php echo (isset($_POST['education']) && $_POST['education'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Skills & Expertise</label>
                        <textarea name="skills" class="form-control" rows="3" 
                                  placeholder="List your skills (e.g., Communication, Teaching, Technical Skills, etc.)"><?php echo isset($_POST['skills']) ? htmlspecialchars($_POST['skills']) : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- NGO Information Section -->
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--light-bg);">
                    <h2 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-building"></i> NGO Information
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">NGO/Organization Name *</label>
                            <input type="text" name="ngo_name" class="form-control" 
                                   value="<?php echo isset($_POST['ngo_name']) ? htmlspecialchars($_POST['ngo_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Role/Position *</label>
                            <input type="text" name="role" class="form-control" 
                                   value="<?php echo isset($_POST['role']) ? htmlspecialchars($_POST['role']) : ''; ?>" 
                                   placeholder="Field Worker, Coordinator, etc." required>
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Why do you want to volunteer? *</label>
                        <textarea name="message" class="form-control" rows="4" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Document Upload Section -->
                <div style="margin-bottom: 30px;">
                    <h2 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-upload"></i> Document Upload
                    </h2>
                    
                    <p style="color: var(--text-light); margin-bottom: 20px;">
                        Upload clear scans/photos. Maximum file size: 5MB each. Allowed formats: JPG, PNG, PDF
                    </p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Passport Size Photo *</label>
                            <div class="file-upload" id="passportUpload">
                                <i class="fas fa-camera" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                                <p>Click to upload photo</p>
                                <p style="font-size: 0.9rem; color: var(--text-light);">(JPG, PNG, Max 5MB)</p>
                                <input type="file" id="passport" name="passport_photo" 
                                       accept="image/*" style="display: none;" required>
                                <div class="file-preview" id="passportPreview"></div>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">Aadhaar Card *</label>
                            <div class="file-upload" id="aadhaarUpload">
                                <i class="fas fa-id-card" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                                <p>Click to upload Aadhaar</p>
                                <p style="font-size: 0.9rem; color: var(--text-light);">(JPG, PNG, PDF, Max 5MB)</p>
                                <input type="file" id="aadhaar" name="aadhaar_card" 
                                       accept="image/*,.pdf" style="display: none;" required>
                                <div class="file-preview" id="aadhaarPreview"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark);">School Leaving Certificate *</label>
                        <div class="file-upload" id="certificateUpload">
                            <i class="fas fa-file-certificate" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <p>Click to upload certificate</p>
                            <p style="font-size: 0.9rem; color: var(--text-light);">(JPG, PNG, PDF, Max 5MB)</p>
                            <input type="file" id="certificate" name="school_certificate" 
                                   accept="image/*,.pdf" style="display: none;" required>
                            <div class="file-preview" id="certificatePreview"></div>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                    
                    <p style="margin-top: 20px; color: var(--text-light); font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> By submitting, you agree to our terms and conditions
                    </p>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                <p style="color: var(--text-light);">
                    Already have access? <a href="volunteerlogin.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Login here</a>
                </p>
                <p style="margin-top: 10px;">
                    <a href="index.html" style="color: var(--text-light); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // File upload preview functionality
        function setupFileUpload(uploadId, inputId, previewId) {
            const uploadDiv = document.getElementById(uploadId);
            const fileInput = document.getElementById(inputId);
            const previewDiv = document.getElementById(previewId);
            
            uploadDiv.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Check file size (5MB = 5 * 1024 * 1024 bytes)
                    if (file.size > 5 * 1024 * 1024) {
                        uploadDiv.classList.add('error');
                        previewDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> File too large (max 5MB)';
                        fileInput.value = '';
                        return;
                    }
                    
                    // Check file type
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    if (!validTypes.includes(file.type)) {
                        uploadDiv.classList.add('error');
                        previewDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Invalid file type';
                        fileInput.value = '';
                        return;
                    }
                    
                    // Valid file
                    uploadDiv.classList.remove('error');
                    uploadDiv.classList.add('has-file');
                    previewDiv.innerHTML = `<i class="fas fa-check"></i> ${file.name} (${(file.size/1024/1024).toFixed(2)} MB)`;
                }
            });
        }
        
        // Initialize file uploads
        setupFileUpload('passportUpload', 'passport', 'passportPreview');
        setupFileUpload('aadhaarUpload', 'aadhaar', 'aadhaarPreview');
        setupFileUpload('certificateUpload', 'certificate', 'certificatePreview');
        
        // Form submission
        document.getElementById('volunteerForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Validate file sizes before submit
            const files = [
                document.getElementById('passport').files[0],
                document.getElementById('aadhaar').files[0],
                document.getElementById('certificate').files[0]
            ];
            
            for (let file of files) {
                if (file && file.size > 5 * 1024 * 1024) {
                    e.preventDefault();
                    alert('One or more files exceed 5MB limit');
                    return;
                }
            }
            
            // Change button to loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Re-enable after 10 seconds in case of error
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });
        
        // Auto-focus first field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="full_name"]').focus();
        });
    </script>
</body>
</html>