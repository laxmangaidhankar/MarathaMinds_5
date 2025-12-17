<?php
session_start();

// Check if logged in
if (!isset($_SESSION['volunteer_id'])) {
    header("Location: volunteer_login.php");
    exit();
}

$name = $_SESSION['volunteer_name'];
$email = $_SESSION['volunteer_email'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - Sarathi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #06D6A0;
            --danger: #ef4444;
            --white: #FFFFFF;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-box {
            background: var(--card-bg);
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-box h2 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 24px;
        }
        
        .card h3 {
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .card-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .card-btn:hover {
            background: var(--primary-dark);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            border-top: 1px solid var(--border);
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-hands-helping"></i> Sarathi Volunteer Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <strong><?php echo htmlspecialchars($name); ?></strong></span>
            <form method="POST" action="logout.php">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-box">
            <h2>Welcome to Your Dashboard</h2>
            <p>Email: <?php echo htmlspecialchars($email); ?></p>
            <p>You have successfully logged in to the Sarathi Volunteer Management System.</p>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>My Tasks</h3>
                <p>View and manage your assigned volunteer tasks</p>
                <a href="#" class="card-btn">View Tasks</a>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Attendance</h3>
                <p>Mark your daily attendance</p>
                <a href="#" class="card-btn">Mark Attendance</a>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3>Profile</h3>
                <p>Update your personal information</p>
                <a href="#" class="card-btn">Edit Profile</a>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Reports</h3>
                <p>View your activity reports</p>
                <a href="#" class="card-btn">View Reports</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>© 2024 Sarathi Volunteer Management System</p>
        <p>Need help? Contact support@example.com</p>
    </div>
</body>
</html>