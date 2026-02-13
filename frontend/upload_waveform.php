<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Function to return JSON
function returnJSON($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    returnJSON(['error' => 'Unauthorized. Please sign in.']);
}

$userID = (int)$_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['waveform_file'])) {
    returnJSON(['error' => 'No file uploaded']);
}

$file = $_FILES['waveform_file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error';
    returnJSON(['error' => 'Upload error: ' . $error_msg]);
}

$target_dir = "uploads/";

// Create uploads directory if it doesn't exist
if (!file_exists($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        returnJSON(['error' => 'Failed to create upload directory']);
    }
}

// Check if directory is writable
if (!is_writable($target_dir)) {
    returnJSON(['error' => 'Upload directory is not writable']);
}

$filename = basename($file["name"]);
// Sanitize filename
$filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
$target_file = $target_dir . time() . "_" . $filename;
$fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Allowed file types
$allowed_types = ['png', 'jpg', 'jpeg'];
if (!in_array($fileType, $allowed_types)) {
    returnJSON(['error' => 'Only PNG, JPG, JPEG files are allowed.']);
}

// Max size 10MB
$maxSize = 10 * 1024 * 1024;
if ($file["size"] > $maxSize) {
    returnJSON(['error' => 'File too large (max 10MB).']);
}

// Upload file ONLY - NO DATABASE INSERT
if (!move_uploaded_file($file["tmp_name"], $target_file)) {
    returnJSON(['error' => 'File upload failed.']);
}

// Store in session temporarily
$_SESSION['temp_upload'] = [
    'file_path' => $target_file,
    'file_name' => $filename,
    'userID' => $userID,
    'timestamp' => time()
];

// Return success with file info
returnJSON([
    'success' => true,
    'message' => 'File uploaded successfully!',
    'file_path' => $target_file,
    'file_name' => $filename
]);