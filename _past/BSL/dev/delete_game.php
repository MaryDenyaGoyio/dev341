<?php
// 데이터베이스 연결 설정
$host = 'localhost';  
$username = 'root';  
$password = 'e^ipi=-1';  
$dbname = 'GameRecords';  

// MySQL 연결
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX 요청으로부터 game_id를 가져옵니다.
$gameId = $_POST['game_id'] ?? '';

// game_id가 유효한지 확인합니다.
if (!empty($gameId)) {
    // DELETE 쿼리를 실행하여 해당 게임을 삭제합니다.
    $deleteQuery = "DELETE FROM Games WHERE GameID = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $gameId);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
?>
