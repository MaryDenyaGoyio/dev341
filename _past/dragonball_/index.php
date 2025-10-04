<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>블교 드래곤볼</title>
    <link rel="stylesheet" href="css/lobby_style.css">
</head>



<body>

    <!-- PHP -->
    <?php
        // session
        session_start();
        $userUuid = $_SESSION['uuid'];
        $userName = $_SESSION['name'];


        // get stats data
        $conn = new mysqli('localhost', 'root', 'e^ipi=-1', 'DragonBall');
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        $rankingData = [];
        // 
        $result = $conn->query('SELECT name, win, lose, rating FROM users WHERE active = TRUE AND (win + lose) > 0 ORDER BY rating DESC LIMIT 10;');
        if ($result->num_rows > 0) {
            $rank = 1;
            while ($row = $result->fetch_assoc()) {
                $row['rank'] = $rank++;
                $row['total'] = $row['win'] + $row['lose'];
                $row['win_rate'] = $row['total'] > 0 ? round(($row['win'] / $row['total']) * 100, 2) . '%' : '0%';
                $rankingData[] = $row;
            }
        }


        // get joined rooms (미구현 - 이 부분이 seession이 저장하고 php로 분리해서 하게 될 것임)
        $stmt = $conn->prepare('SELECT id FROM rooms WHERE rooms.status != -1 AND (owner_uuid = ? OR challenger_uuid = ?)');
        $stmt->bind_param('ss', $userUuid, $userUuid);
        $stmt->execute();
        $stmt->bind_result($joinedRoomId);
        $hasRoom = $stmt->fetch();
        $stmt->close();

        $_SESSION['joinedRoomId'] = $joinedRoomId;
  
        $conn->close();
    ?>
    

    <!-- LOGIN -->
    <header>
        <div class="auth">
            <?php if ($userUuid): ?>
                <strong><?= htmlspecialchars($userName); ?></strong>
                <span id="logout" style="cursor: pointer;">logout</span>
            <?php else: ?>
                <span id="login" style="cursor: pointer;">login</span>
            <?php endif; ?>
        </div>
    </header>


    <div class="main">

        <!-- GAME -->
        <div class="top">
            <?php if ($hasRoom): ?>
                <div class="button" id="my-game">My Game</div>
            <?php else: ?>
                <div class="button" id="create-game">New Game</div>
            <?php endif; ?>
            <div class="button" id="play-ai">AI Game</div>
        </div>


        <!-- STATS -->
        <div class="stats">
            <h2>Player Stats</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>R</th>
                        <th>%</th>
                        <th>W</th>
                        <th>L</th>
                        <th>T</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankingData as $player): ?>
                    <tr>
                        <td><?= $player['rank'] ?></td>
                        <td><?= htmlspecialchars($player['name']) ?></td>
                        <td><?= $player['rating'] ?></td>
                        <td><?= $player['win_rate'] ?></td>
                        <td><?= $player['win'] ?></td>
                        <td><?= $player['lose'] ?></td>
                        <td><?= $player['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>


        <!-- ROOMS (implemented below) -->
        <div class="roomList">

        </div>
    </div>


    <!-- JS -->
    <script>

        // login
        const hasUuid = '<?= isset($_SESSION['uuid']) ? 'true' : 'false' ?>';
        const login = document.getElementById('login');
        if (login) {
            login.addEventListener('click', () => {
                    window.location.href = './login';
            });
        }

        // logout
        const logout = document.getElementById('logout');
        if (logout) {
            logout.addEventListener('click', () => {
                if (confirm('엥 진짜 나가게?')) {
                    
                    // POST method
                    fetch('./utils/login/logout.php', {
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


        // create Game
        let hasRoom = '<?= $hasRoom ?>';
        const createGame = document.getElementById('create-game');

        if (!hasRoom) {
            if (hasUuid === 'false') {
                createGame.style.opacity = "0.5";        
                createGame.addEventListener('click', () => {
                    alert('아이디부터 만들고 와라');
                });
            } else {
                createGame.addEventListener('click', () => {

                    // roon name, description
                    const roomName = prompt('방 이름:');
                    const ruleDescription = prompt('설명:');

                    if (roomName && ruleDescription) {

                        // POST method
                        fetch('./utils/room/create_room.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `room_name=${encodeURIComponent(roomName)}&rule_description=${encodeURIComponent(ruleDescription)}`
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.status === 'success') {

                                // send to created room
                                window.location.href = `./room?id=${result.room_id.toString().padStart(6, '0')}`;
                            } else {
                                alert('Failed to create room');
                            }
                        });
                    }
                });
            }
        }


        // my Game
        let joinedRoomId = '<?= $joinedRoomId ?>';
        const myGame = document.getElementById('my-game');

        if (hasRoom) {
            myGame.style.display = 'block';
            myGame.onclick = () => {
                window.location.href = `./room?id=${joinedRoomId}`;
            };
        } else {
            createGame.style.display = 'block';
        }


        // AI Game
        const playAI = document.getElementById('play-ai');

        playAI.addEventListener('click', () => {
            // AI와의 게임 시작 로직 추가
        });


        // update Rooms
        function updateRooms() {
            fetch('./utils/room/get_rooms.php')
                .then(response => response.json())
                .then(rooms => {
                    // first, check any joined room exists
                    const hasRoom = rooms.some(room => room.in_room);

                    // second, show each rooms
                    const roomListDiv = document.querySelector('.roomList');
                    roomListDiv.innerHTML = '';  // 기존 목록 삭제

                    rooms.forEach((room) => {
                        const roomDiv = document.createElement('div');
                        roomDiv.classList.add('room');

                        roomDiv.innerHTML = `
                            <strong>${room.room_name}</strong><br>
                            Owner: ${room.owner_name}<br>
                            About: ${room.description}
                        `;

                        if (room.in_room) {
                            joinedRoomId = room.id;
                            roomDiv.style.fontWeight = 'bold';

                            roomDiv.dataset.clickable = "true";
                        } else if (!hasRoom) {
                            roomDiv.dataset.clickable = "true";
                        } else {
                            roomDiv.style.opacity = "0.5";
                            roomDiv.style.cursor = "not-allowed";

                            roomDiv.dataset.clickable = "false";
                        }

                        // click room
                        roomDiv.addEventListener('click', () => {
                            if (roomDiv.dataset.clickable === "true") {
                                window.location.href = `./room?id=${room.id}`;
                            }
                        });

                        roomListDiv.appendChild(roomDiv);
                    });
                });
        }

        // update period = 3s
        setInterval(updateRooms, 3000);
        updateRooms();

    </script>
</body>
</html>
