<?php
header('Content-Type: application/json');

session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $roomName = $_POST['room_name'];
    $ownerUuid = $_SESSION['uuid'];
    $ruleDescription = $_POST['rule_description'];

    $conn = new mysqli('localhost', 'root', 'e^ipi=-1', 'DragonBall');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    $stmt = $conn->prepare('INSERT INTO rooms (room_name, owner_uuid, description, status) VALUES (?, ?, ?, 0)');
    $stmt->bind_param('sss', $roomName, $ownerUuid, $ruleDescription);
    if ($stmt->execute()) {
        $roomId = $conn->insert_id;
        echo json_encode(['status' => 'success', 'room_id' => $roomId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create room']);
    }
    $stmt->close();
    $conn->close();
}
?>
