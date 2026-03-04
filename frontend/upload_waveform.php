<?php
session_start();
error_reporting(0); // Turn off error reporting to avoid corrupting JSON output
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized. Please sign in.']);
    exit;
}

$userID = (int)$_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['waveform_file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['waveform_file'];

// Upload error check
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE   => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension'
    ];
    $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error';
    echo json_encode(['error' => 'Upload error: ' . $error_msg]);
    exit;
}

// Validate file type
$allowed_types = ['png', 'jpg', 'jpeg'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_types)) {
    echo json_encode(['error' => 'Only PNG, JPG, JPEG files are allowed.']);
    exit;
}

// Validate file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large (max 10MB).']);
    exit;
}

// Create uploads directory if needed
$target_dir = 'uploads/';
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}
if (!is_writable($target_dir)) {
    echo json_encode(['error' => 'Upload directory is not writable']);
    exit;
}

// Generate safe filename and save
$safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name']);
$target_path = $target_dir . $safe_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    echo json_encode(['error' => 'Failed to save uploaded file.']);
    exit;
}

// --- Call FastAPI model ---
$fastapi_url = 'http://127.0.0.1:8000/predict'; // adjust if needed

// Prepare file for cURL
$curl_file = new CURLFile($target_path);
$post_data = ['file' => $curl_file];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fastapi_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$prediction = null;
if ($http_code == 200) {
    $result = json_decode($response, true);
    if (isset($result['predicted_class'])) {
        $prediction = $result['predicted_class'];
    }
} else {
    error_log("FastAPI call failed: HTTP $http_code - $response");
}

// Store info in session for next page
$_SESSION['last_uploaded_image'] = $target_path;
$_SESSION['last_prediction'] = $prediction;

// Return JSON to browser
echo json_encode([
    'success' => true,
    'message' => 'File uploaded and analyzed successfully!',
    'file_path' => $target_path,
    'file_name' => $safe_filename,
    'prediction' => $prediction
]);
exit;