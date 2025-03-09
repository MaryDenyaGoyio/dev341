<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>블교 DEV래곤볼</title>
    
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        header {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 20px;
            background-color: #1e1e1e;
        }
        .auth {
            color: #ffffff;
            cursor: pointer;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px;
        }
        .buttons {
            display: flex;
            gap: 50px;
            margin-bottom: 60px;
        }
        .button {
            background-color: #333333;
            color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.5em;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background-color: #444444;
        }
        .game-rooms {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px;
            width: 100%;
            max-width: 1200px;
        }
        .room {
            background-color: #1e1e1e;
            color: #ffffff;
            padding: 30px;
            width: 300px;
            height: 150px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .room:hover {
            background-color: #333333;
        }
    </style>
</head>
<body>
    <?php
        session_start();
        $userUuid = $_SESSION['uuid'];  // UUID 사용

        // 데이터베이스 연결
        $conn = new mysqli('db', 'root', 'dragonball', 'DragonBall');
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        // UUID를 이용해 유저 이름 가져오기
        $stmt = $conn->prepare('SELECT name FROM users WHERE uuid = ?');
        $stmt->bind_param('s', $userUuid);
        $stmt->execute();
        $stmt->bind_result($userName);
        $stmt->fetch();
        $stmt->close();

        // 유저 이름을 세션에 저장
        $_SESSION['name'] = $userName;

        // 사용자가 만든 방 또는 참가 중인 방을 확인
        $stmt = $conn->prepare('SELECT id FROM rooms WHERE rooms.status != -1 AND (owner_uuid = ? OR challenger_uuid = ?)');
        $stmt->bind_param('ss', $userUuid, $userUuid);
        $stmt->execute();
        $stmt->bind_result($userRoomId);
        $hasRoom = $stmt->fetch();
        $stmt->close();
        $conn->close();
    ?>
    
    <header>
        <div class="auth" id="login-section">
            <?php if ($userName): ?>
                <strong><?= htmlspecialchars($userName); ?></strong>
                <span id="logout-section" style="cursor: pointer;">logout</span>
            <?php else: ?>
                login
            <?php endif; ?>
        </div>
    </header>

    <div class="main-content">
        <div class="buttons">
            <?php if ($hasRoom): ?>
                <div class="button" id="my-game">My Game</div>
            <?php else: ?>
                <div class="button" id="create-game">New Game</div>
            <?php endif; ?>
            <div class="button" id="play-ai">AI Game</div>
        </div>

        <div class="game-rooms">
            <!-- 생성된 게임 방들이 여기에 추가될 공간 -->
        </div>
    </div>

    <?php
        // 현재 요청된 URI
        $requestUri = $_SERVER['REQUEST_URI'];

        // 클라이언트에 전달
        echo "<script>console.log('서버에서 처리된 요청 경로: " . htmlspecialchars($requestUri) . "');</script>";
    ?>

    <script>
        const loginSection = document.getElementById('login-section');
        loginSection.addEventListener('click', () => {
            <?php if (!$userName): ?>
                const currentUrl = window.location.href;

                const relativePath = './login/login.php'; // 상대 경로
                const anchor = document.createElement('a'); // Anchor 태그 생성
                anchor.href = relativePath; // 상대 경로 설정
                const absoluteUrl = anchor.href; // 절대 경로 계산

                console.log("현재 URL:", currentUrl);
                console.log("리디렉션 하려는 URL:", absoluteUrl); // 절대 경로 출력
                window.location.href = './login/login.php';
            <?php endif; ?>
        });

        const logoutSection = document.getElementById('logout-section');
        if (logoutSection) {
            logoutSection.addEventListener('click', () => {
                if (confirm('Are you sure you want to log out?')) {
                    fetch('./login/logout.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            window.location.reload();
                        } else {
                            alert('Logout failed. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });
        }


        const createGameButton = document.getElementById('create-game');
        const isLoggedIn = '<?= isset($_SESSION['name']) ? 'true' : 'false' ?>';

        if (createGameButton) {
            if (isLoggedIn === 'false') {
                // 로그인하지 않았을 때 버튼 흐리게 하고 클릭 불가 처리
                createGameButton.style.opacity = "0.5";        
                createGameButton.addEventListener('click', () => {
                    alert('You must log in first!');
                });
            } else {
                // 로그인했을 때만 클릭 가능
                createGameButton.addEventListener('click', () => {
                    const roomName = prompt('Enter room name:');
                    const ruleDescription = prompt('Enter game description:');

                    if (roomName && ruleDescription) {
                        fetch('./room/create_room.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `room_name=${encodeURIComponent(roomName)}&rule_description=${encodeURIComponent(ruleDescription)}`
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.status === 'success') {
                                window.location.href = `./room.php?room_id=${result.room_id}`;
                            } else {
                                alert('Failed to create room');
                            }
                        });
                    }
                });
            }
        }

        const playAIButton = document.getElementById('play-ai');
        playAIButton.addEventListener('click', () => {
            alert('AI와 게임을 시작합니다!');
            // AI와의 게임 시작 로직 추가
        });

        function updateRooms() {
            fetch('./room/get_rooms.php')
                .then(response => response.json())
                .then(rooms => {
                    const gameRoomsDiv = document.querySelector('.game-rooms');
                    gameRoomsDiv.innerHTML = '';  // 기존 목록 삭제

                    let userInRoom = false;
                    let userRoomId;

                    rooms.forEach((room) => {
                        const roomDiv = document.createElement('div');
                        roomDiv.classList.add('room');
                        const isUserInRoom = room.owner_uuid === '<?= $userUuid ?>' || room.challenger_uuid === '<?= $userUuid ?>';

                        roomDiv.innerHTML = `
                            <strong>${room.room_name}</strong><br>
                            Owner: ${room.owner_name}<br>
                            About: ${room.rule_description}
                        `;

                        if (isUserInRoom) {
                            userInRoom = true;
                            userRoomId = room.id;
                            roomDiv.style.fontWeight = 'bold';
                            roomDiv.dataset.clickable = "true";
                        } else if (!userInRoom) {
                            roomDiv.dataset.clickable = "true";
                        } else {
                            roomDiv.dataset.clickable = "false";
                            roomDiv.style.opacity = "0.5";
                            roomDiv.style.cursor = "not-allowed";
                        }

                        roomDiv.addEventListener('click', () => {
                            if (roomDiv.dataset.clickable === "true") {
                                window.location.href = `./room.php?room_id=${room.id}`;
                            }
                        });

                        gameRoomsDiv.appendChild(roomDiv);
                    });

                    // 버튼 표시 로직
                    const createGameButton = document.getElementById('create-game');
                    const myGameButton = document.getElementById('my-game');
                    if (userInRoom) {
                        myGameButton.style.display = 'block';
                        myGameButton.onclick = () => {
                            window.location.href = `./room.php?room_id=${userRoomId}`;
                        };
                    } else {
                        createGameButton.style.display = 'block';
                    }
                });
        }

        setInterval(updateRooms, 10000);
        updateRooms();


    </script>
</body>
</html>
