<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            exit('Invalid request token. Go back and try again.');
        }
    }
}
function log_activity(PDO $pdo, string $action, string $details = ''): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (admin_id, admin_username, action, details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['admin_id'] ?? null, $_SESSION['admin_username'] ?? 'System', $action, substr($details, 0, 255)]);
    } catch (Throwable $ignored) {
        // Logging must not interrupt the primary order operation.
    }
}
function record_order_status(PDO $pdo, int $orderId, string $status, string $note = ''): void {
    $stmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([$orderId, $status, $note, $_SESSION['admin_username'] ?? 'System']);
}
function status_class($status) {
    return 's-' . strtolower(str_replace(' ', '-', (string)$status));
}
?>
