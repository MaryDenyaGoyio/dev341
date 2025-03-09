<?php
    $conn = new mysqli("localhost", "root", "e^ipi=-1", "bmt_awards");

    // 연결 체크
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // 폼 데이터 받기
    $id = $_POST['id'];
    $password = $_POST['password'];

    // 사용자 조회
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // 비밀번호 확인

        if ($password==$row['password']) {
            
            session_start();

            $_SESSION['uuid'] = $row['uuid'];
            $_SESSION['logged_in'] = true;
            $_SESSION['name'] = $row['name'];

            header("Location: ../");
            exit;

        } else {
            echo "failed";
        }
    } else {
        echo "not found";   
    }
    $stmt->close();
    $conn->close();
?>
