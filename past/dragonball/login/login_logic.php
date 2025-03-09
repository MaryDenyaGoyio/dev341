<?php
    header('Content-Type: application/json');

    $conn = new mysqli('db', 'root', 'dragonball', 'DragonBall');

    if ($conn->connect_error) {
        echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $password = $_POST['password'];


        // Check if ID exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($password==$row['pw']) {
                
                session_start();

                $_SESSION['uuid'] = $row['uuid'];
                $_SESSION['logged_in'] = true;
                $_SESSION['name'] = $row['name'];

            } else { 
                echo json_encode(["status" => "error", "message" => "Password does not match: " . $conn->error]);
                exit;
            }
        } else { 
            echo json_encode(["status" => "error", "message" => "ID not found"]);
            exit;
        }

        echo json_encode(["status" => "success"]);
        $stmt->close();
    }
    $conn->close();
?>
