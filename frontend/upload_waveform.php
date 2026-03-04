<?php
session_start();
error_reporting(0);
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
$fastapi_url = 'http://127.0.0.1:8000/predict';

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
$curl_error = curl_error($ch);
curl_close($ch);

$prediction = null;
$debug_info = '';

if ($curl_error) {
    $debug_info = 'CURL Error: ' . $curl_error;
    error_log("CURL Error: " . $curl_error);
} elseif ($http_code != 200) {
    $debug_info = 'FastAPI returned HTTP ' . $http_code;
    error_log("FastAPI HTTP Error: " . $http_code . " - Response: " . $response);
} else {
    $result = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // Log what FastAPI returned for debugging
        error_log("FastAPI Response: " . print_r($result, true));
        
        // Try different possible response field names
        if (isset($result['predicted_class'])) {
            $prediction = $result['predicted_class'];
        } elseif (isset($result['prediction'])) {
            $prediction = $result['prediction'];
        } elseif (isset($result['class'])) {
            $prediction = $result['class'];
        } elseif (isset($result['result'])) {
            $prediction = $result['result'];
        } else {
            // If none of the expected fields exist, use the first value in the response
            $first_value = reset($result);
            if (is_string($first_value)) {
                $prediction = $first_value;
            }
        }
        
        $debug_info = 'FastAPI returned: ' . json_encode($result);
    } else {
        $debug_info = 'Invalid JSON from FastAPI';
        error_log("Invalid JSON from FastAPI: " . $response);
    }
}

// Store info in session for next page
$_SESSION['last_uploaded_image'] = $target_path;
$_SESSION['last_prediction'] = $prediction;

// Return JSON to browser with debug info
echo json_encode([
    'success' => true,
    'message' => 'File uploaded and analyzed successfully!',
    'file_path' => $target_path,
    'file_name' => $safe_filename,
    'prediction' => $prediction,
    'debug' => $debug_info // This will help us see what FastAPI returns
]);
exit;
?>