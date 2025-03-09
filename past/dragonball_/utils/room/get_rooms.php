<?php
header('Content-Type: application/json');

session_start();
$userUuid = $_SESSION['uuid'];

$conn = new mysqli('localhost', 'root', 'e^ipi=-1', 'DragonBall');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$query = "
    SELECT rooms.*, users.name AS owner_name,
           (rooms.owner_uuid = ? OR rooms.challenger_uuid = ?) AS in_room
    FROM rooms
    LEFT JOIN users ON rooms.owner_uuid = users.uuid
    WHERE rooms.status != -1
    ORDER BY rooms.time_open DESC
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
