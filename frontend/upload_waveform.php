<?php
// Turn OFF error display to browser (prevents output before headers)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log errors, just don't display

// Start session FIRST, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');

try {
    // Check dependencies
    if (!file_exists('db_connection.php')) {
        throw new Exception("db_connection.php file is missing.");
    }
    require_once 'db_connection.php';

    if (!file_exists('upload_helpers.php')) {
        throw new Exception("upload_helpers.php file is missing.");
    }
    require_once 'upload_helpers.php';

    // Auth Check
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login again.']);
        exit;
    }

    $userID = (int)$_SESSION['user_id'];

    // File Check
    if (!isset($_FILES['waveform_file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
        exit;
    }

    // Check for upload errors
    if ($_FILES['waveform_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by PHP extension'
        ];
        
        $errorMsg = $uploadErrors[$_FILES['waveform_file']['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    // Run the upload function
    $result = handleFileUpload($conn, $userID);

    if (isset($result['success']) && $result['success']) {
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => $result['error'] ?? 'Unknown error during upload'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Upload Error: " . $e->getMessage());
    
    // Return error as JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}

// Close connection if it exists
if (isset($conn)) {
    $conn->close();
}
?>