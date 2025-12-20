<?php
session_start();
require_once "../../connection/db_con.php";

// Check if admin is logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Handle Property Report Resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_report'])) {
    $report_id = intval($_POST['resolve_report']);
    $action = $_POST['action'] ?? '';
    $admin_notes = $_POST['admin_notes'] ?? '';

    $query = $conn->prepare("SELECT reporter_id, reported_user_id FROM reports WHERE id = ?");
    $query->bind_param("i", $report_id);
    $query->execute();
    $result = $query->get_result();
    $report = $result->fetch_assoc();

    if ($report) {
        $reporter_id = $report['reporter_id'];
        $reported_user_id = $report['reported_user_id'];

        if ($action === 'mark_resolved') {
            $conn->query("UPDATE reports SET status='Resolved' WHERE id=$report_id");
            
            $msg_reporter = "Your report has been resolved. Thank you for helping maintain our community.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_update', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'warn_user') {
            $msg_reported = "You received a warning regarding a property report. Please follow community guidelines.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'user_warning', 0, NOW())");
            $stmt->bind_param("is", $reported_user_id, $msg_reported);
            $stmt->execute();

            $conn->query("UPDATE reports SET status='Resolved' WHERE id=$report_id");
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'ban_user') {
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $reported_user_id");
            $conn->query("UPDATE reports SET status='Resolved' WHERE id=$report_id");

            $msg_reporter = "The reported user has been banned. Thank you for your report.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_update', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'dismiss') {
            $conn->query("UPDATE reports SET status='Dismissed' WHERE id=$report_id");

            $msg_reporter = "Your report has been reviewed and dismissed.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_dismissed', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Handle Reported User Resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_reported_user'])) {
    $report_id = intval($_POST['resolve_reported_user']);
    $action = $_POST['action'] ?? '';

    $query = $conn->prepare("SELECT reporter_id, reported_user_id FROM report_users WHERE id = ?");
    $query->bind_param("i", $report_id);
    $query->execute();
    $result = $query->get_result();
    $report = $result->fetch_assoc();

    if ($report) {
        $reporter_id = $report['reporter_id'];
        $reported_user_id = $report['reported_user_id'];

        if ($action === 'warn') {
            $msg_reporter = "We have issued the user a warning. If more reports come, a ban may be issued.";
            $msg_reported = "You received a warning due to a recent report. Follow community guidelines.";

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_update', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'user_warning', 0, NOW())");
            $stmt2->bind_param("is", $reported_user_id, $msg_reported);
            $stmt2->execute();

            $update = $conn->prepare("UPDATE report_users SET status = 'Resolved', updated_at = NOW() WHERE id = ?");
            $update->bind_param("i", $report_id);
            $update->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'temporary_ban') {
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $reported_user_id");

            $msg_reporter = "We have temporarily banned the reported user.";
            $msg_reported = "Your account has been temporarily banned.";

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_update', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'user_ban', 0, NOW())");
            $stmt2->bind_param("is", $reported_user_id, $msg_reported);
            $stmt2->execute();

            $update = $conn->prepare("UPDATE report_users SET status = 'Resolved', updated_at = NOW() WHERE id = ?");
            $update->bind_param("i", $report_id);
            $update->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'permanent_ban') {
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $reported_user_id");

            $msg_reporter = "We have permanently banned the reported user.";
            $msg_reported = "Your account has been permanently banned.";

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_update', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'user_ban', 0, NOW())");
            $stmt2->bind_param("is", $reported_user_id, $msg_reported);
            $stmt2->execute();

            $update = $conn->prepare("UPDATE report_users SET status = 'Resolved', updated_at = NOW() WHERE id = ?");
            $update->bind_param("i", $report_id);
            $update->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action === 'dismiss') {
            $update = $conn->prepare("UPDATE report_users SET status = 'Dismissed', updated_at = NOW() WHERE id = ?");
            $update->bind_param("i", $report_id);
            $update->execute();

            $msg_reporter = "The report has been dismissed.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'report_dismissed', 0, NOW())");
            $stmt->bind_param("is", $reporter_id, $msg_reporter);
            $stmt->execute();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

    <title>Admin Dashboard</title>
    <style>
        table {
            background-color: transparent !important;
        }
        td {
            color: #333;
        }
        /* Scrollable table wrapper */
        .table-wrapper {
            overflow-x: auto;      
            overflow-y: auto;      
            max-height: 400px;     
            margin-bottom: 20px;   
        }

        /* Search Bar Styles */
        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-bar input {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            width: 300px;
            max-width: 100%;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .search-bar label {
            font-weight: 500;
            color: #333;
            white-space: nowrap;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal-content {
            margin: 10% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
            overflow: hidden;
        }

        /* Modal Header - Green Theme */
        .modal-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px 25px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
        }

        /* Modal Body */
        .modal-body {
            padding: 25px;
        }

        .modal-body p {
            margin: 0 0 20px 0;
            font-size: 1rem;
            line-height: 1.5;
            color: #555;
        }

        .modal-body .reported-user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            margin-bottom: 20px;
        }

        .modal-body .reported-user-info b {
            color: #333;
            font-weight: 600;
        }

        /* Form Styles */
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }

        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Modal Buttons */
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-modal {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .btn-modal-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-modal-success:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-modal-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-modal-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Close Button */
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 10;
        }

        .close:hover {
            color: #ffeb3b;
            transform: scale(1.1);
        }

        /* View Details Link */
        a.view-details {
            color: #4CAF50;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            padding: 5px 10px;
            border-radius: 4px;
            background: #f8f9ff;
        }

        a.view-details:hover {
            color: #45a049;
            background: #eef1ff;
            text-decoration: none;
        }

        /* Badge */
        .badge {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 12px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
            animation: pulse 2s infinite;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Report Details Specific Styles */
        #reportDetailsModal .modal-content {
            max-width: 600px;
        }

        #reportDetailsModal .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }

        #reportDetailsText {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Resolve Modal Specific Styles */
        #resolveReportModal .modal-content,
        #resolveReportedUserModal .modal-content {
            max-width: 500px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .btn-modal {
                width: 100%;
            }

            .search-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-bar input {
                width: 100%;
            }
        }

        /* Status Indicators */
        .status-pending {
            color: #ffa726;
            font-weight: 600;
        }

        .status-resolved {
            color: #4CAF50;
            font-weight: 600;
        }

        .status-dismissed {
            color: #6c757d;
            font-weight: 600;
        }
    </style>
</head>
<body>
<header>
    <h2><i class="fa-solid fa-user-tie"></i> LandSeek <b>Admin Access</b></h2>
    <nav>
        <ul>
            <li><a href="manage_data.php" class="active"><i class="fa-solid fa-database"></i> Manage Data</a></li>
            <li><a href="../logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>

<div class="dashboard-container">
    <div class="sidebar">
        <a href="#" class="nav-link active" data-target="users-management">
            <i class="fa-solid fa-users"></i> Users Management
        </a>
        <a href="#" class="nav-link" data-target="properties-management">
            <i class="fa-solid fa-house"></i> Properties Management
        </a>
        <a href="#" class="nav-link" data-target="reports-management">
            <i class="fa-solid fa-flag"></i> Reports Management
            <?php
            $pending_reports_res = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE status='Pending'");
            $pending_reports = $pending_reports_res ? $pending_reports_res->fetch_assoc()['total'] : 0;
            if ($pending_reports > 0) echo '<span class="badge">'.$pending_reports.'</span>';
            ?>
        </a>
        <a href="#" class="nav-link" data-target="reported-users-management">
            <i class="fa-solid fa-user-slash"></i> Reported Users
            <?php
            $pending_reported_users_res = $conn->query("SELECT COUNT(*) AS total FROM report_users WHERE status='Pending'");
            $pending_reported_users = $pending_reported_users_res ? $pending_reported_users_res->fetch_assoc()['total'] : 0;
            if ($pending_reported_users > 0) echo '<span class="badge">'.$pending_reported_users.'</span>';
            ?>
        </a>
    </div>

    <main>
        <!-- USERS MANAGEMENT -->
        <section id="users-management" class="active">
            <h3>Users Management</h3>
            <div class="search-bar">
                <input type="text" id="userSearch" placeholder="Search by Name...">
            </div>
            <div class="table-wrapper">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Verified</th>
                            <th>Active</th>
                            <th>Registered At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $conn->query("SELECT users.*, user_profiles.full_name FROM users LEFT JOIN user_profiles ON user_profiles.user_id = users.id ORDER BY users.created_at DESC");
                        while($user = $users->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['full_name'] ?: 'N/A') ?></td>
                            <td><?= $user['is_verified'] ? 'Yes' : 'No' ?></td>
                            <td><?= $user['is_active'] ? 'Active' : 'Banned' ?></td>
                            <td><?= $user['created_at'] ?></td>
                            <td>
                                <a href="actions/ban_user.php?id=<?= $user['id'] ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirmAction('Are you sure you want to <?= $user['is_active'] ? 'ban' : 'unban' ?> this user?')">
                                    <?= $user['is_active'] ? 'Ban' : 'Unban' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- PROPERTIES MANAGEMENT -->
        <section id="properties-management">
            <h3>Properties Management</h3>
            <div class="search-bar">
                <input type="text" id="propertySearch" placeholder="Search by Title, Owner, or Region...">
            </div>
            <div class="table-wrapper">
                <table id="propertiesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Owner</th>
                            <th>Region</th>
                            <th>Classification</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Visits</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $props = $conn->query("
                                SELECT 
                                    p.*, 
                                    up.full_name AS owner_name
                                FROM properties p
                                JOIN users u ON p.user_id = u.id
                                LEFT JOIN user_profiles up ON up.user_id = u.id
                                ORDER BY p.created_at DESC
                            ");

                            while ($prop = $props->fetch_assoc()):
                            ?>
                        <tr>
                            <td><?= $prop['id'] ?></td>
                            <td><?= htmlspecialchars($prop['title']) ?></td>
                            <td><?= htmlspecialchars($prop['owner_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($prop['region']) ?></td>
                            <td><?= htmlspecialchars($prop['classification']) ?></td>
                            <td><?= htmlspecialchars($prop['price_range']) ?></td>
                            <td><?= htmlspecialchars($prop['status']) ?></td>
                            <td><?= $prop['visits'] ?></td>
                            <td>
                                <a href="actions/delete_property.php?id=<?= $prop['id'] ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirmAction('Are you sure you want to delete this property? This action cannot be undone.')">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- REPORTS MANAGEMENT -->
        <section id="reports-management">
            <h3>Reports Management</h3>
            <div class="search-bar">
                <input type="text" id="reportSearch" placeholder="Search by Reporter, Reported User, or Reason...">
            </div>
            <div class="table-wrapper">
                <table id="reportsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reporter</th>
                            <th>Reported User</th>
                            <th>Property</th>
                            <th>Reason</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reports = $conn->query("SELECT r.*, 
                                up1.full_name AS reporter_name, 
                                up2.full_name AS reported_name, 
                                p.title AS property_title
                            FROM reports r
                            JOIN users u1 ON r.reporter_id=u1.id
                            JOIN users u2 ON r.reported_user_id=u2.id
                            LEFT JOIN user_profiles up1 ON up1.user_id = u1.id
                            LEFT JOIN user_profiles up2 ON up2.user_id = u2.id
                            LEFT JOIN properties p ON r.property_id=p.id
                            ORDER BY r.created_at DESC");
                        while($report = $reports->fetch_assoc()):
                            $status_class = 'status-' . strtolower($report['status']);
                        ?>
                        <tr>
                            <td><?= $report['id'] ?></td>
                            <td><?= htmlspecialchars($report['reporter_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($report['reported_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($report['property_title'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($report['reason']) ?></td>
                            <td><a href="#" class="view-details" data-details="<?= htmlspecialchars($report['details'], ENT_QUOTES) ?>">View Details</a></td>
                            <td class="<?= $status_class ?>"><?= htmlspecialchars($report['status']) ?></td>
                            <td>
                                <button 
                                    class="btn btn-warning open-report-modal" 
                                    data-id="<?= $report['id'] ?>"
                                    data-reported="<?= htmlspecialchars($report['reported_name'] ?: 'N/A') ?>">
                                    Resolve
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- REPORTED USERS MANAGEMENT -->
        <section id="reported-users-management">
            <h3>Reported Users Management</h3>
            <div class="search-bar">
                <input type="text" id="reportedUserSearch" placeholder="Search by Reporter, Reported User, or Reason...">
            </div>
            <div class="table-wrapper">
                <table id="reportedUsersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reporter</th>
                            <th>Reported User</th>
                            <th>Reason</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reportedUsers = $conn->query("
                            SELECT ru.*, 
                                up1.full_name AS reporter_name, 
                                up2.full_name AS reported_name
                            FROM report_users ru
                            JOIN users u1 ON ru.reporter_id = u1.id
                            JOIN users u2 ON ru.reported_user_id = u2.id
                            LEFT JOIN user_profiles up1 ON up1.user_id = u1.id
                            LEFT JOIN user_profiles up2 ON up2.user_id = u2.id
                            ORDER BY ru.created_at DESC
                        ");
                        while($ru = $reportedUsers->fetch_assoc()):
                            $status_class = 'status-' . strtolower($ru['status']);
                        ?>
                        <tr>
                            <td><?= $ru['id'] ?></td>
                            <td><?= htmlspecialchars($ru['reporter_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($ru['reported_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($ru['reason']) ?></td>
                            <td><a href="#" class="view-details" data-details="<?= htmlspecialchars($ru['details'], ENT_QUOTES) ?>">View Details</a></td>
                            <td class="<?= $status_class ?>"><?= htmlspecialchars($ru['status']) ?></td>
                            <td><?= $ru['created_at'] ?></td>
                            <td><?= $ru['updated_at'] ?></td>
                            <td>
                                <button 
                                    class="btn btn-warning open-reported-user-modal" 
                                    data-id="<?= $ru['id'] ?>"
                                    data-reported="<?= htmlspecialchars($ru['reported_name'] ?: 'N/A') ?>">
                                    Resolve
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Report Details Modal -->
<div id="reportDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Report Details</h3>
            <span class="close" onclick="closeReportModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="reportDetailsText"></p>
        </div>
    </div>
</div>

<!-- Resolve Report Modal -->
<div id="resolveReportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Resolve Report</h3>
            <span class="close" onclick="closeResolveReportModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="reported-user-info">
                <p><b>Reported User:</b> <span id="reportedUserName"></span></p>
            </div>
            <form method="POST" id="resolveReportForm" onsubmit="return confirmResolveAction('report')" class="modal-form">
                <input type="hidden" name="resolve_report" id="resolveReportId">

                <div class="form-group">
                    <label for="actionSelect">Choose an Action:</label>
                    <select name="action" id="actionSelect" required>
                        <option value="">-- Select Action --</option>
                        <option value="mark_resolved">Mark as Resolved</option>
                        <option value="warn_user">Warn User</option>
                        <option value="ban_user">Ban User</option>
                        <option value="dismiss">Dismiss Report</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="admin_notes">Admin Notes (optional):</label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" placeholder="Add any additional notes or context for this action..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeResolveReportModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-success">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve Reported User Modal -->
<div id="resolveReportedUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Resolve Reported User</h3>
            <span class="close" onclick="closeResolveReportedUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="reported-user-info">
                <p><b>Reported User:</b> <span id="reportedUserName2"></span></p>
            </div>
            <form method="POST" id="resolveReportedUserForm" onsubmit="return confirmResolveAction('reported_user')" class="modal-form">
                <input type="hidden" name="resolve_reported_user" id="resolveReportedUserId">

                <div class="form-group">
                    <label for="actionSelect2">Choose an Action:</label>
                    <select name="action" id="actionSelect2" required>
                        <option value="">-- Select Action --</option>
                        <option value="warn">Warn User</option>
                        <option value="temporary_ban">Temporary Ban</option>
                        <option value="permanent_ban">Permanent Ban</option>
                        <option value="dismiss">Dismiss Report</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="admin_notes2">Admin Notes (optional):</label>
                    <textarea name="admin_notes" id="admin_notes2" rows="3" placeholder="Add any additional notes or context for this action..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeResolveReportedUserModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-success">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Confirmation function for direct actions
function confirmAction(message) {
    return confirm(message);
}

// Confirmation function for resolve actions
function confirmResolveAction(type) {
    let actionSelect = type === 'report' ? document.getElementById('actionSelect') : document.getElementById('actionSelect2');
    let selectedAction = actionSelect.options[actionSelect.selectedIndex].text;
    let reportedUserName = type === 'report' ? document.getElementById('reportedUserName').textContent : document.getElementById('reportedUserName2').textContent;
    
    let message = `Are you sure you want to ${selectedAction.toLowerCase()} for ${reportedUserName}?`;
    
    if (selectedAction.includes('Ban')) {
        message = `WARNING: You are about to BAN ${reportedUserName}. This action will restrict their account access. Are you sure?`;
    } else if (selectedAction.includes('Permanent Ban')) {
        message = `CRITICAL: You are about to PERMANENTLY BAN ${reportedUserName}. This action cannot be undone. Are you absolutely sure?`;
    }
    
    return confirm(message);
}

const navLinks = document.querySelectorAll('.nav-link');
const sections = document.querySelectorAll('section');

navLinks.forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        const target = link.getAttribute('data-target');
        sections.forEach(sec => {
            sec.classList.remove('active');
            if(sec.id === target) sec.classList.add('active');
        });
    });
});

// USERS TABLE SEARCH
const userSearch = document.getElementById('userSearch');
const usersTable = document.getElementById('usersTable').getElementsByTagName('tbody')[0];

userSearch.addEventListener('keyup', () => {
    const filter = userSearch.value.toLowerCase();
    Array.from(usersTable.rows).forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        if(name.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// PROPERTIES TABLE SEARCH
const propertySearch = document.getElementById('propertySearch');
const propertiesTable = document.getElementById('propertiesTable').getElementsByTagName('tbody')[0];

propertySearch.addEventListener('keyup', () => {
    const filter = propertySearch.value.toLowerCase();
    Array.from(propertiesTable.rows).forEach(row => {
        const title = row.cells[1].textContent.toLowerCase();
        const owner = row.cells[2].textContent.toLowerCase();
        const region = row.cells[3].textContent.toLowerCase();
        
        if(title.includes(filter) || owner.includes(filter) || region.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// REPORTS TABLE SEARCH
const reportSearch = document.getElementById('reportSearch');
const reportsTable = document.getElementById('reportsTable').getElementsByTagName('tbody')[0];

reportSearch.addEventListener('keyup', () => {
    const filter = reportSearch.value.toLowerCase();
    Array.from(reportsTable.rows).forEach(row => {
        const reporter = row.cells[1].textContent.toLowerCase();
        const reportedUser = row.cells[2].textContent.toLowerCase();
        const reason = row.cells[4].textContent.toLowerCase();
        
        if(reporter.includes(filter) || reportedUser.includes(filter) || reason.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// REPORTED USERS TABLE SEARCH
const reportedUserSearch = document.getElementById('reportedUserSearch');
const reportedUsersTable = document.getElementById('reportedUsersTable').getElementsByTagName('tbody')[0];

reportedUserSearch.addEventListener('keyup', () => {
    const filter = reportedUserSearch.value.toLowerCase();
    Array.from(reportedUsersTable.rows).forEach(row => {
        const reporter = row.cells[1].textContent.toLowerCase();
        const reportedUser = row.cells[2].textContent.toLowerCase();
        const reason = row.cells[3].textContent.toLowerCase();
        
        if(reporter.includes(filter) || reportedUser.includes(filter) || reason.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// REPORT DETAILS MODAL
const reportDetailsModal = document.getElementById('reportDetailsModal');
const reportDetailsText = document.getElementById('reportDetailsText');

document.querySelectorAll('.view-details').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const details = link.getAttribute('data-details');
        reportDetailsText.textContent = details && details !== 'null' ? details : 'No details provided.';
        reportDetailsModal.style.display = 'block';
    });
});

function closeReportModal() {
    reportDetailsModal.style.display = 'none';
}

// RESOLVE REPORT MODAL
const resolveReportModal = document.getElementById('resolveReportModal');
document.querySelectorAll('.open-report-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reportedUserName').textContent = btn.dataset.reported;
        document.getElementById('resolveReportId').value = btn.dataset.id;
        resolveReportModal.style.display = 'block';
    });
});

function closeResolveReportModal() {
    resolveReportModal.style.display = 'none';
}

// RESOLVE REPORTED USER MODAL
const resolveReportedUserModal = document.getElementById('resolveReportedUserModal');
document.querySelectorAll('.open-reported-user-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reportedUserName2').textContent = btn.dataset.reported;
        document.getElementById('resolveReportedUserId').value = btn.dataset.id;
        resolveReportedUserModal.style.display = 'block';
    });
});

function closeResolveReportedUserModal() {
    resolveReportedUserModal.style.display = 'none';
}

// Close modals on outside click
window.addEventListener('click', e => {
    if (e.target === reportDetailsModal) closeReportModal();
    if (e.target === resolveReportModal) closeResolveReportModal();
    if (e.target === resolveReportedUserModal) closeResolveReportedUserModal();
});

// Close modals on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeReportModal();
        closeResolveReportModal();
        closeResolveReportedUserModal();
    }
});
</script>

</body>
</html>