<?php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();

// Handle actions
if (isset($_GET['action'])) {
    $volunteer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    switch ($_GET['action']) {
        case 'deactivate':
            deactivateVolunteer($conn, $volunteer_id);
            break;
            
        case 'activate':
            activateVolunteer($conn, $volunteer_id);
            break;
            
        case 'delete':
            deleteVolunteer($conn, $volunteer_id);
            break;
            
        case 'view_docs':
            viewVolunteerDocuments($conn, $volunteer_id);
            exit();
    }
}

function deactivateVolunteer($conn, $volunteer_id) {
    $sql = "UPDATE volunteers SET status = 'inactive' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "✅ Volunteer deactivated successfully";
    } else {
        $_SESSION['error'] = "❌ Failed to deactivate volunteer";
    }
    
    header("Location: admin_volunteers.php");
    exit();
}

function activateVolunteer($conn, $volunteer_id) {
    $sql = "UPDATE volunteers SET status = 'active' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "✅ Volunteer activated successfully";
    } else {
        $_SESSION['error'] = "❌ Failed to activate volunteer";
    }
    
    header("Location: admin_volunteers.php");
    exit();
}

function deleteVolunteer($conn, $volunteer_id) {
    $sql = "DELETE FROM volunteers WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "✅ Volunteer deleted successfully";
    } else {
        $_SESSION['error'] = "❌ Failed to delete volunteer";
    }
    
    header("Location: admin_volunteers.php");
    exit();
}

function viewVolunteerDocuments($conn, $volunteer_id) {
    // IMPORTANT: Set content type to JSON
    header('Content-Type: application/json');
    
    // First get volunteer details
    $sql = "SELECT * FROM volunteers WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $volunteer = mysqli_fetch_assoc($result);
    
    if (!$volunteer) {
        echo json_encode(['error' => 'Volunteer not found']);
        exit();
    }
    
    // Default fields
    $default_fields = [
        'passport_photo' => '',
        'aadhaar_card' => '',
        'school_certificate' => '',
        'education' => '',
        'skills' => '',
        'email' => '',
        'mobile_number' => '',
        'full_name' => '',
        'ngo_name' => 'Sarathi',
        'role_position' => 'field worker'
    ];
    
    // Merge defaults with actual data
    $volunteer = array_merge($default_fields, $volunteer);
    
    // Check if volunteer has document paths
    $has_docs_in_volunteers = !empty($volunteer['passport_photo']) || 
                              !empty($volunteer['aadhaar_card']) || 
                              !empty($volunteer['school_certificate']);
    
    if (!$has_docs_in_volunteers) {
        // Try to get documents from volunteer_requests table
        // Search by email and mobile number
        $request_sql = "SELECT passport_photo, aadhaar_card, school_certificate 
                       FROM volunteer_requests 
                       WHERE (email = ? OR mobile_number = ? OR full_name = ?)
                       AND (passport_photo IS NOT NULL OR aadhaar_card IS NOT NULL OR school_certificate IS NOT NULL)
                       ORDER BY created_at DESC LIMIT 1";
        
        $stmt2 = mysqli_prepare($conn, $request_sql);
        mysqli_stmt_bind_param($stmt2, "sss", 
            $volunteer['email'], 
            $volunteer['mobile_number'],
            $volunteer['full_name']
        );
        mysqli_stmt_execute($stmt2);
        $request_result = mysqli_stmt_get_result($stmt2);
        
        if ($request_docs = mysqli_fetch_assoc($request_result)) {
            // Found documents in requests table, merge them
            $volunteer = array_merge($volunteer, $request_docs);
        }
    }
    
    // Function to fix file paths - IMPORTANT!
    function fixFilePath($path) {
        if (empty($path)) return '';
        
        // If already a full URL, return as is
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        
        // Base URL for your application
        $base_url = 'http://localhost/sarathi_volunteer_system/';
        
        // Remove any leading slashes or dots
        $path = ltrim($path, './');
        
        // If path starts with assets/, use as is
        if (strpos($path, 'assets/') === 0) {
            return $base_url . $path;
        }
        
        // If it's just a filename, assume it's in assets/uploads/
        if (strpos($path, '/') === false && strpos($path, '\\') === false) {
            // Try to determine which folder it belongs to
            if (strpos($path, 'passport') !== false) {
                return $base_url . 'assets/uploads/passport/' . $path;
            } elseif (strpos($path, 'aadhaar') !== false) {
                return $base_url . 'assets/uploads/aadhaar/' . $path;
            } elseif (strpos($path, 'certificate') !== false) {
                return $base_url . 'assets/uploads/certificate/' . $path;
            } else {
                return $base_url . 'assets/uploads/' . $path;
            }
        }
        
        // Default: prepend base URL
        return $base_url . $path;
    }
    
    // Fix all document paths
    $volunteer['passport_photo'] = fixFilePath($volunteer['passport_photo']);
    $volunteer['aadhaar_card'] = fixFilePath($volunteer['aadhaar_card']);
    $volunteer['school_certificate'] = fixFilePath($volunteer['school_certificate']);
    
    // Debug info (optional - remove in production)
    $volunteer['debug'] = [
        'original_email' => $volunteer['email'],
        'original_mobile' => $volunteer['mobile_number'],
        'has_docs_in_table' => $has_docs_in_volunteers
    ];
    
    echo json_encode($volunteer);
    exit();
}

// Get all volunteers with their details
$sql = "SELECT * FROM volunteers ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM volunteers WHERE status = 'active') as active,
    (SELECT COUNT(*) FROM volunteers WHERE status = 'inactive') as inactive,
    (SELECT COUNT(*) FROM volunteers) as total";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteers - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #06D6A0;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --sidebar-width: 250px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #1e40af);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .header {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 4px solid var(--primary);
        }
        
        .stat-card.active { border-top-color: var(--secondary); }
        .stat-card.inactive { border-top-color: var(--warning); }
        .stat-card.total { border-top-color: var(--info); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .table-container {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        .btn-success { background: var(--secondary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fef3c7; color: #92400e; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .document-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        .document-card img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            background: #f8fafc;
            padding: 10px;
        }
        
        .document-info {
            padding: 15px;
            border-top: 1px solid var(--border);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .file-path {
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            word-break: break-all;
        }
        
        .no-docs-message {
            text-align: center;
            padding: 40px;
            background: #f8fafc;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .document-selector {
            margin: 10px 0;
            padding: 10px;
            background: #f0f9ff;
            border-radius: 5px;
            border: 1px dashed var(--info);
        }
        
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-hands-helping"></i> Sarathi</h2>
            <p style="opacity: 0.8; font-size: 0.9rem;">Admin Panel</p>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="admin_panel.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_volunteers.php" class="active"><i class="fas fa-users"></i> Volunteers</a></li>
            <li><a href="admin_requests.php"><i class="fas fa-user-clock"></i> Requests</a></li>
            <li><a href="admin_contact.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Volunteer Management</h1>
                <p style="color: var(--text-light);">Manage all registered volunteers</p>
            </div>
            <div>
                <button class="btn btn-info" onclick="window.location.href='admin_panel.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card active">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-check" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Active</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Currently active</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card inactive">
                <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-times" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Inactive</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Deactivated</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-users" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Total</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">All volunteers</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Volunteers Table -->
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-users"></i> All Volunteers</h2>
                <span class="status-badge status-active"><?php echo $stats['total']; ?> Volunteers</span>
            </div>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name & Details</th>
                            <th>Contact Info</th>
                            <th>NGO Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            // Ensure all fields have values
                            $row['education'] = $row['education'] ?? '';
                            $row['skills'] = $row['skills'] ?? '';
                            $row['ngo_name'] = $row['ngo_name'] ?? 'Sarathi';
                            $row['role_position'] = $row['role_position'] ?? 'field worker';
                        ?>
                            <tr>
                                <td>V-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                    <small style="color: var(--text-light);"><?php echo htmlspecialchars($row['education']); ?></small><br>
                                    <small><?php echo htmlspecialchars($row['skills']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['email']); ?><br>
                                    <small style="color: var(--text-light);"><?php echo htmlspecialchars($row['mobile_number']); ?></small><br>
                                    <small>Joined: <?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['ngo_name']); ?><br>
                                    <small style="color: var(--text-light);"><?php echo htmlspecialchars($row['role_position']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button class="btn btn-info btn-sm" onclick="viewDocuments(<?php echo $row['id']; ?>, this)">
                                            <i class="fas fa-file-alt"></i> Docs
                                        </button>
                                        
                                        <?php if($row['status'] == 'active'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="deactivateVolunteer(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-user-times"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm" onclick="activateVolunteer(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-user-check"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-danger btn-sm" onclick="deleteVolunteer(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-light);">
                    <i class="fas fa-users" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3>No Volunteers Found</h3>
                    <p>There are no registered volunteers yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for Viewing Documents -->
    <div class="modal" id="docsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Volunteer Documents</h2>
                <button class="close-modal" onclick="closeDocsModal()">&times;</button>
            </div>
            <div id="docsModalBody" style="padding: 20px;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <script>
        // View Volunteer Documents
        function viewDocuments(id, buttonElement) {
            // Show loading state on button
            const originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<span class="loading"></span> Loading...';
            buttonElement.disabled = true;
            
            fetch(`admin_volunteers.php?action=view_docs&id=${id}`)
                .then(response => {
                    // First check if response is JSON
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        return response.text().then(text => {
                            console.error("Received non-JSON response:", text.substring(0, 200));
                            throw new Error("Server error. Please check PHP error logs.");
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset button
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.disabled = false;
                    
                    const modalBody = document.getElementById('docsModalBody');
                    
                    // Check for error
                    if (data.error) {
                        modalBody.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
                                <h3>${data.error}</h3>
                                <p>Volunteer not found in the system.</p>
                            </div>
                        `;
                    } else {
                        // Prepare document URLs with fallbacks
                        const passportPhoto = data.passport_photo || '';
                        const aadhaarCard = data.aadhaar_card || '';
                        const schoolCertificate = data.school_certificate || '';
                        
                        const hasDocuments = passportPhoto || aadhaarCard || schoolCertificate;
                        
                        modalBody.innerHTML = `
                            <div style="margin-bottom: 20px;">
                                <h3>${data.full_name || 'Unknown Volunteer'}'s Documents</h3>
                                <p><strong>Email:</strong> ${data.email || 'Not available'}</p>
                                <p><strong>Mobile:</strong> ${data.mobile_number || 'Not available'}</p>
                                <p><strong>Volunteer ID:</strong> V-${String(data.id).padStart(6, '0')}</p>
                            </div>
                            
                            ${hasDocuments ? `
                                <div class="documents-grid">
                                    ${passportPhoto ? `
                                        <div class="document-card">
                                            <h4>Passport Photo</h4>
                                            <img src="${passportPhoto}" 
                                                 alt="Passport Photo" 
                                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                            <div class="document-info">
                                                <a href="${passportPhoto}" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View Full
                                                </a>
                                                <div class="file-path">${passportPhoto}</div>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="document-card">
                                            <h4>Passport Photo</h4>
                                            <div class="no-docs-message">
                                                <i class="fas fa-file-exclamation" style="font-size: 2rem; color: var(--warning);"></i>
                                                <p>No passport photo available</p>
                                            </div>
                                        </div>
                                    `}
                                    
                                    ${aadhaarCard ? `
                                        <div class="document-card">
                                            <h4>Aadhaar Card</h4>
                                            <img src="${aadhaarCard}" 
                                                 alt="Aadhaar Card" 
                                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                            <div class="document-info">
                                                <a href="${aadhaarCard}" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View Full
                                                </a>
                                                <div class="file-path">${aadhaarCard}</div>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="document-card">
                                            <h4>Aadhaar Card</h4>
                                            <div class="no-docs-message">
                                                <i class="fas fa-file-exclamation" style="font-size: 2rem; color: var(--warning);"></i>
                                                <p>No Aadhaar card available</p>
                                            </div>
                                        </div>
                                    `}
                                    
                                    ${schoolCertificate ? `
                                        <div class="document-card">
                                            <h4>School Certificate</h4>
                                            <img src="${schoolCertificate}" 
                                                 alt="Certificate" 
                                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                            <div class="document-info">
                                                <a href="${schoolCertificate}" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View Full
                                                </a>
                                                <div class="file-path">${schoolCertificate}</div>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="document-card">
                                            <h4>School Certificate</h4>
                                            <div class="no-docs-message">
                                                <i class="fas fa-file-exclamation" style="font-size: 2rem; color: var(--warning);"></i>
                                                <p>No school certificate available</p>
                                            </div>
                                        </div>
                                    `}
                                </div>
                                
                                <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px;">
                                    <p style="color: var(--text-light); font-size: 0.9rem; margin: 0;">
                                        <i class="fas fa-info-circle"></i> 
                                        Documents are retrieved from both volunteers and volunteer_requests tables.
                                    </p>
                                </div>
                            ` : `
                                <div class="no-docs-message">
                                    <i class="fas fa-file-exclamation" style="font-size: 3rem; color: var(--warning);"></i>
                                    <h3>No Documents Found</h3>
                                    <p>No documents available for this volunteer in the database.</p>
                                    <div style="margin-top: 20px;">
                                        <p style="color: var(--text-light);">Possible reasons:</p>
                                        <ul style="text-align: left; display: inline-block; color: var(--text-light);">
                                            <li>Documents were not uploaded during registration</li>
                                            <li>Documents might be in the original request (check Requests section)</li>
                                            <li>Database records might need updating</li>
                                        </ul>
                                    </div>
                                    <div style="margin-top: 20px;">
                                        <a href="admin_requests.php" class="btn btn-info">
                                            <i class="fas fa-search"></i> Check Original Requests
                                        </a>
                                        <button class="btn btn-warning" onclick="location.reload()">
                                            <i class="fas fa-redo"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                            `}
                        `;
                    }
                    
                    document.getElementById('docsModal').style.display = 'flex';
                })
                .catch(error => {
                    // Reset button on error
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.disabled = false;
                    
                    console.error('Error:', error);
                    const modalBody = document.getElementById('docsModalBody');
                    modalBody.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
                            <h3>Error Loading Documents</h3>
                            <p>Failed to load volunteer documents. Please try again.</p>
                            <p style="color: var(--text-light); font-size: 0.9rem;">${error.message}</p>
                            <div style="margin-top: 20px;">
                                <button class="btn btn-info" onclick="window.location.href='admin_requests.php'">
                                    <i class="fas fa-search"></i> Check Requests Instead
                                </button>
                                <button class="btn btn-warning" onclick="location.reload()">
                                    <i class="fas fa-redo"></i> Reload Page
                                </button>
                            </div>
                        </div>
                    `;
                    document.getElementById('docsModal').style.display = 'flex';
                });
        }
        
        function closeDocsModal() {
            document.getElementById('docsModal').style.display = 'none';
        }
        
        // Deactivate Volunteer
        function deactivateVolunteer(id) {
            if (confirm('Are you sure you want to deactivate this volunteer?')) {
                window.location.href = `admin_volunteers.php?action=deactivate&id=${id}`;
            }
        }
        
        // Activate Volunteer
        function activateVolunteer(id) {
            if (confirm('Are you sure you want to activate this volunteer?')) {
                window.location.href = `admin_volunteers.php?action=activate&id=${id}`;
            }
        }
        
        // Delete Volunteer
        function deleteVolunteer(id) {
            if (confirm('⚠️ WARNING: This will permanently delete the volunteer!\n\nAre you sure?')) {
                window.location.href = `admin_volunteers.php?action=delete&id=${id}`;
            }
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeDocsModal();
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>