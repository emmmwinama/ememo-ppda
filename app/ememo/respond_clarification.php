<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'memo_utils.php'; // Ensure this contains insertTrail()

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.');
    }

    $user_id = $_SESSION['user_id'];

    // 🔄 Read and decode JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        throw new Exception('Invalid JSON payload.');
    }

    $clarification_id = intval($input['clarification_id'] ?? 0);
    $response_comment = trim($input['response_comment'] ?? '');

    if ($clarification_id <= 0 || $response_comment === '') {
        throw new Exception('Missing required fields: clarification_id or response_comment.');
    }

    // ✅ Update the clarification
    $stmt = $conn->prepare("
        UPDATE memo_clarifications 
        SET response_comment = ?, responded_at = NOW(), status = 'Responded'
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("sii", $response_comment, $clarification_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Get memo_id for logging trail
    $stmt = $conn->prepare("SELECT memo_id FROM memo_clarifications WHERE id = ?");
    $stmt->bind_param("i", $clarification_id);
    $stmt->execute();
    $stmt->bind_result($memo_id);
    if (!$stmt->fetch()) {
        throw new Exception("Clarification not found.");
    }
    $stmt->close();

    // ✅ Insert action trail
    insertTrail($conn, $memo_id, $user_id, null, 'Clarification Responded', 'Respond Clarification', $response_comment);

    echo json_encode([
        'status' => 'success',
        'message' => 'Clarification responded successfully.'
    ]);

} catch (Exception $e) {
    error_log("Respond Clarification Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
