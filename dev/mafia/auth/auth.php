<!-- PHP 부분 -->
<?php
// 세션 시작
session_start();

// 이미 로그인한 경우
if (isset($_SESSION['uuid'])) {
    header("Location: stats.php");
    exit;
}


// POST 확인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['pw'])) {
    
    // Register 로직
    if ($action === 'register') {

        // ID 존재 확인
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "이미 존재하는 ID";
        } else {
            $stmt->close();
            $uuid = uniqid();
            // 여기서는 이름을 아이디와 동일하게 처리 (필요 시 별도의 입력폼 추가)
            $name = $id;

            $stmt = $conn->prepare("INSERT INTO users (uuid, id, pw, name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $uuid, $id, $pw, $name);
            if ($stmt->execute()) {
                echo "회원가입 성공! 이제 로그인 해주세요.";
            } else {
                echo "Failed: " . $conn->error;
            }
            $stmt->close();
        }
    } 

    // Login 로직
    else {
        // DB 연결
        $conn = new mysqli('mysql_dev', 'developer', 'developer341', 'mafia');
        if ($conn->connect_error) { die($conn->connect_error); }

        $id = $_POST['id'];
        $pw = $_POST['pw'];

        // Query
        $stmt = $conn->prepare("SELECT uuid, id, pw, name FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        // User 확인
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if ($pw === $row['pw']) {
                // 세션에 저장
                $_SESSION['uuid'] = $row['uuid'];
                $_SESSION['id'] = $row['id'];
                $_SESSION['name'] = $row['name'];

                header("Location: lobby.php");
                exit;
            } else {
                echo "PW 불일치";
            }
        } else {
            echo "ID 존재하지 않음";
        }
        $stmt->close();
    }
    $conn->close();
}
?>



<!-- HTML 부분 -->
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
<body>
  <h1>Login</h1>

  <form action="login.php" method="post">
    <label for="id">ID:</label>
    <input type="text" name="id" id="id" required>
    <br>
    <label for="pw">PW:</label>
    <input type="password" name="pw" id="pw" required>
    <br>
    <button type="submit">login</button>

  </form>
  <p><a href="index.php">로비</a></p>
</body>
</html>
