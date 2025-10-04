<?php
    header('Content-Type: application/json');

    $conn = new mysqli("localhost", "root", "e^ipi=-1", "DragonBall");
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $password = $_POST['password'];

        // check id
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($password==$row['pw']) {
                
                // make session
                session_start();
                $_SESSION['uuid'] = $row['uuid'];
                $_SESSION['logged_in'] = true; // 뭔가 이거 안 쓰는 중임
                $_SESSION['name'] = $row['name'];

            } 
            
            // Wrong password
            else {
                echo json_encode(["status" => "error", "message" => "Password does not match"]);
                exit;
            }
        } 
        
        // Wrong id
        else { 
            echo json_encode(["status" => "error", "message" => "ID not found"]);
            exit;
        }
        echo json_encode(["status" => "success"]);
        $stmt->close();
    }
    $conn->close();
?>
