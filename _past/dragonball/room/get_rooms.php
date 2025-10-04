<?php
header('Content-Type: application/json');

session_start();
$userUuid = $_SESSION['uuid'];

$conn = new mysqli('db', 'root', 'dragonball', 'DragonBall');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$query = "
    SELECT rooms.*, users.name AS owner_name,
           (rooms.owner_uuid = ? OR rooms.challenger_uuid = ?) AS isUserInRoom
    FROM rooms
    LEFT JOIN users ON rooms.owner_uuid = users.uuid
    WHERE rooms.status != -1
    ORDER BY rooms.created_time DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $userUuid, $userUuid);
$stmt->execute();
$result = $stmt->get_result();

$rooms = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($rooms);
?>
