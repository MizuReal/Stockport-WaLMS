<?php
session_start();
include '../server/database.php';
require_once 'session_check.php'; // Adjust path as needed
requireActiveLogin(); // This ensures user is logged in AND has Active status
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['employeeID']) || empty($_SESSION['employeeID'])) {
    header('Location: ../employee-login.php');
    exit();
}

$employeeID = $_SESSION['employeeID'];

// Fetch employee details from the database
try {
    $stmt = $conn->prepare("
        SELECT EmployeeID, FirstName, LastName, Role, Phone, employeeEmail, HireDate, Status
        FROM employees
        WHERE EmployeeID = ?
    ");
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
    } else {
        die("Employee not found.");
    }

    $stmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get profile picture path and check for multiple extensions
$extensions = ['jpg', 'jpeg', 'png'];
$profilePicture = '../assets/imgs/profiles/default.jpg';
$timestamp = time(); // Add timestamp for cache busting

foreach ($extensions as $ext) {
    $testPath = "../assets/imgs/profiles/{$employeeID}.{$ext}";
    if (file_exists($testPath)) {
        $profilePicture = $testPath . "?t=" . $timestamp;
        break;
    }
}

// Handle profile picture upload with improved error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $targetDir = "../assets/imgs/profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileExtension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $targetFile = $targetDir . $employeeID . "." . $fileExtension;

    // Validate file type and size
    $allowedTypes = ['jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (in_array($fileExtension, $allowedTypes) && $_FILES["profile_picture"]["size"] <= $maxSize) {
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
            // Delete old profile pictures with different extensions
            foreach ($extensions as $ext) {
                $oldFile = $targetDir . $employeeID . "." . $ext;
                if ($oldFile !== $targetFile && file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Add cache control headers
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            
            header("Location: employee_profile.php?success=1&t=" . time());
            exit();
        } else {
            $uploadError = "Failed to upload file. Please check permissions.";
        }
    } else {
        $uploadError = "Invalid file type or size. Please use JPG or PNG under 5MB.";
    }
}

// Add success/error messages display
if (isset($_GET['success'])) {
    $successMsg = "Profile picture updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .profile-details {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .profile-upload {
            margin-top: 15px;
        }
        .custom-file-upload {
            display: inline-block;
            margin: 10px 0;
        }
        .custom-file-upload input[type="file"] {
            width: 200px;
        }
        .upload-btn {
            background: #4285f4;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .upload-btn:hover {
            background: #3367d6;
        }
    </style>
    <title>Employee Profile</title>
</head>
<body>
    <?php renderSidebar('employee_profile'); ?>
    
    <div class="content-wrapper">
        <?php renderHeader('Employee Profile'); ?>

        <?php if (isset($uploadError)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($uploadError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="card">
                <div class="card-body text-center">
                    <img src="<?= htmlspecialchars($profilePicture) ?>" class="profile-picture" alt="Profile Picture">
                    <form method="POST" enctype="multipart/form-data" class="profile-upload">
                        <div class="custom-file-upload">
                            <input type="file" class="form-control form-control-sm" name="profile_picture" accept="image/jpeg,image/png">
                        </div>
                        <button type="submit" class="upload-btn">Update Photo</button>
                    </form>

                    <div class="profile-details mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>First Name:</strong> <?= htmlspecialchars($employee['FirstName']) ?></p>
                                <p><strong>Last Name:</strong> <?= htmlspecialchars($employee['LastName']) ?></p>
                                <p><strong>Role:</strong> <?= htmlspecialchars($employee['Role']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($employee['Phone']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?= htmlspecialchars($employee['employeeEmail']) ?></p>
                                <p><strong>Hire Date:</strong> <?= htmlspecialchars($employee['HireDate']) ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-<?= $employee['Status'] === 'Active' ? 'success' : 'danger' ?>"><?= htmlspecialchars($employee['Status']) ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>