<?php
    header('Content-Type: application/json');

    $conn = new mysqli("localhost", "root", "e^ipi=-1", "DragonBall");
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $password = $_POST['password'];
        $name = $_POST['name'];

        // check duplication
        $checkStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $checkStmt->bind_param("s", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "ID already taken"]);
            exit;
        }
        $checkStmt->close();

        // Insert new user
        $query = "INSERT INTO users (id, pw, name) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query); // prepare query
        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "Error preparing statement: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("sss", $id, $password, $name); // execute query
        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "message" => "Error executing statement: " . $stmt->error]);
            exit;
        }

        echo json_encode(["status" => "success"]);
        $stmt->close();
    }
    $conn->close();
?>