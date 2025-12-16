<?php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'approve':
            $request_id = intval($_GET['id']);
            approveVolunteerRequest($conn, $admin_id, $request_id);
            break;
            
        case 'reject':
            $request_id = intval($_GET['id']);
            rejectVolunteerRequest($conn, $admin_id, $request_id);
            break;
            
        case 'delete':
            $request_id = intval($_GET['id']);
            deleteVolunteerRequest($conn, $request_id);
            break;
            
        case 'send_invite':
            $request_id = intval($_GET['id']);
            sendVolunteerInvite($conn, $admin_id, $request_id);
            break;
    }
}

// Function to approve volunteer request
function approveVolunteerRequest($conn, $admin_id, $request_id) {
    $invite_code = generateInviteCode();
    
    $sql = "UPDATE volunteer_requests 
            SET status = 'approved', 
                invite_code = ?,
                invited_by_admin_id = ?,
                updated_at = NOW()
            WHERE id = ? AND status = 'pending'";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $invite_code, $admin_id, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Get request details
        $get_sql = "SELECT * FROM volunteer_requests WHERE id = ?";
        $get_stmt = mysqli_prepare($conn, $get_sql);
        mysqli_stmt_bind_param($get_stmt, "i", $request_id);
        mysqli_stmt_execute($get_stmt);
        $result = mysqli_stmt_get_result($get_stmt);
        $request = mysqli_fetch_assoc($result);
        
        // Create volunteer account
        $temp_password = bin2hex(random_bytes(4)); // Generate temporary password
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $vol_sql = "INSERT INTO volunteers 
                   (request_id, full_name, email, mobile_number, password, ngo_name, role_position) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $vol_stmt = mysqli_prepare($conn, $vol_sql);
        mysqli_stmt_bind_param($vol_stmt, "issssss", 
            $request_id, $request['full_name'], $request['email'], 
            $request['mobile_number'], $hashed_password, $request['ngo_name'], 
            $request['role_position']);
        
        mysqli_stmt_execute($vol_stmt);
        
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'approve', 'Approved volunteer request #{$request_id}')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Volunteer approved! Invite code: " . $invite_code;
    } else {
        $_SESSION['error'] = "Failed to approve request";
    }
    
    header("Location: admin_panel.php");
    exit();
}

// Function to reject volunteer request
function rejectVolunteerRequest($conn, $admin_id, $request_id) {
    $sql = "UPDATE volunteer_requests 
            SET status = 'rejected', 
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'reject', 'Rejected volunteer request #{$request_id}')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Volunteer request rejected";
    } else {
        $_SESSION['error'] = "Failed to reject request";
    }
    
    header("Location: admin_panel.php");
    exit();
}

// Function to send invite
function sendVolunteerInvite($conn, $admin_id, $request_id) {
    $sql = "SELECT * FROM volunteer_requests WHERE id = ? AND status = 'approved'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // In real app, send email with invite link
        $invite_link = "http://yourdomain.com/volunteerlogin.php?invite=" . $row['invite_code'];
        
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'invite', 'Sent invite to {$row['email']}')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Invite sent to " . $row['email'];
    }
    
    header("Location: admin_panel.php");
    exit();
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'pending') as pending,
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'approved') as approved,
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'rejected') as rejected,
    (SELECT COUNT(*) FROM volunteers WHERE status = 'active') as active_volunteers";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get pending requests
$pending_sql = "SELECT * FROM volunteer_requests WHERE status = 'pending' ORDER BY created_at DESC";
$pending_result = mysqli_query($conn, $pending_sql);

// Get recent activity
$activity_sql = "SELECT al.*, a.username 
                FROM activity_logs al 
                LEFT JOIN admins a ON al.admin_id = a.id 
                ORDER BY al.created_at DESC 
                LIMIT 10";
$activity_result = mysqli_query($conn, $activity_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Sarathi</title>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #1e40af);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            padding: 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        
        /* Main Content */
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
        
        /* Stats Cards */
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
        
        .stat-card.pending { border-top-color: var(--warning); }
        .stat-card.approved { border-top-color: var(--secondary); }
        .stat-card.rejected { border-top-color: var(--danger); }
        .stat-card.active { border-top-color: var(--info); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        /* Tables */
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-success { background: var(--secondary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-info { background: var(--info); color: white; }
        
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
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
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
            <li><a href="admin_panel.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#"><i class="fas fa-users"></i> Volunteers</a></li>
            <li><a href="#"><i class="fas fa-user-clock"></i> Pending Requests</a></li>
            <li><a href="#"><i class="fas fa-history"></i> Activity Log</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            <!-- In admin_panel.php sidebar -->
<li><a href="admin_contact.php"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Welcome, <?php echo $_SESSION['admin_name']; ?>!</h1>
                <p style="color: var(--text-light);">Organization: <?php echo $_SESSION['org_name']; ?></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <span style="background: var(--primary); color: white; padding: 8px 15px; border-radius: 20px;">
                    Admin
                </span>
            </div>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #fca5a5;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-clock" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Pending Requests</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Awaiting approval</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Approved</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Volunteers approved</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-times-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Rejected</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Requests rejected</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-number"><?php echo $stats['active_volunteers']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-check" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Active Volunteers</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Currently active</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Requests Table -->
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-user-clock"></i> Pending Volunteer Requests</h2>
                <span class="status-badge status-pending"><?php echo $stats['pending']; ?> Requests</span>
            </div>
            
            <?php if(mysqli_num_rows($pending_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>NGO</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($pending_result)): ?>
                            <tr>
                                <td>VR-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo $row['full_name']; ?></strong><br>
                                    <small style="color: var(--text-light);"><?php echo $row['role_position']; ?></small>
                                </td>
                                <td>
                                    <?php echo $row['email']; ?><br>
                                    <small style="color: var(--text-light);"><?php echo $row['mobile_number']; ?></small>
                                </td>
                                <td><?php echo $row['ngo_name']; ?></td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-pending">Pending</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-success btn-sm" 
                                                onclick="approveRequest(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="rejectRequest(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <button class="btn btn-info btn-sm" 
                                                onclick="viewDetails(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-light);">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3>No Pending Requests</h3>
                    <p>All volunteer requests have been processed.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-history"></i> Recent Activity</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                        <tr>
                            <td><?php echo date('H:i d/m', strtotime($activity['created_at'])); ?></td>
                            <td>
                                <?php 
                                    $badge_class = '';
                                    switch($activity['action_type']) {
                                        case 'login': $badge_class = 'status-pending'; break;
                                        case 'approve': $badge_class = 'status-approved'; break;
                                        case 'reject': $badge_class = 'status-rejected'; break;
                                        default: $badge_class = 'status-pending';
                                    }
                                ?>
                                <span class="status-badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($activity['action_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $activity['description']; ?></td>
                            <td><?php echo $activity['username'] ?? 'System'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal for View Details -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <!-- Details will be loaded via AJAX -->
        </div>
    </div>
    
    <script>
        function approveRequest(id) {
            if (confirm('Are you sure you want to approve this volunteer request?')) {
                window.location.href = 'admin_panel.php?action=approve&id=' + id;
            }
        }
        
        function rejectRequest(id) {
            if (confirm('Are you sure you want to reject this volunteer request?')) {
                window.location.href = 'admin_panel.php?action=reject&id=' + id;
            }
        }
        
        function sendInvite(id) {
            if (confirm('Send invitation email to this volunteer?')) {
                window.location.href = 'admin_panel.php?action=send_invite&id=' + id;
            }
        }
        
        function viewDetails(id) {
            // In a real app, this would fetch details via AJAX
            alert('View details for request ID: ' + id);
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>