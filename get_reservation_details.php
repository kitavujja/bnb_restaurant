<?php
require_once 'config/database.php';
require_once 'config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'auth']);
    exit();
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid']);
    exit();
}

$sql = "SELECT * FROM reservations WHERE reservation_id = $id AND (user_id = $user_id)";
$res = mysqli_query($conn, $sql);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit();
}

$data = [
    'reservation_id' => intval($row['reservation_id']),
    'date' => $row['reservation_date'] . ' ' . $row['reservation_time'],
    'num_guests' => intval($row['num_guests']),
    'status' => $row['status'],
    'payment_status' => $row['payment_status'] ?? 'pending',
    'deposit_amount' => floatval($row['deposit_amount'] ?? 0),
    'payment_method' => $row['payment_method'] ?? null,
    'notes' => $row['notes'] ?? '',
];

echo json_encode(['success' => true, 'reservation' => $data]);
exit();


