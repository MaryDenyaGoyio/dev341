<?php
    session_start();

    $roomId = $_GET['id'] ?? null;
    $userUuid = $_SESSION['uuid'];

    if (!$roomId) {
        die("no room ID");
    }

    $conn = new mysqli("localhost", "root", "e^ipi=-1", "DragonBall");
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    $query = "SELECT room_name, owner_uuid, challenger_uuid, status FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Join as challenger if needed
        if (is_null($row['challenger_uuid']) && ($row['owner_uuid'] !== $userUuid)) {
            $updateQuery = "UPDATE rooms SET challenger_uuid = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $userUuid, $roomId);

            if ($updateStmt->execute()) {
                $row['challenger_uuid'] = $userUuid;
            }
            $updateStmt->close();
        }

        $roomData = $row;

        if ($roomData['status'] === -1) {
            die("Room is already closed");
        }
    } else {
        die("Room not found");
    }

    $stmt->close();
    $conn->close();

    return $roomData;
?>
