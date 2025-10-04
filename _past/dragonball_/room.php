<?php
    session_start();
    $roomData = include './utils/room/join_room.php';
    $roomName = $roomData['room_name'];
    $ownerUuid = $roomData['owner_uuid'];
    $challengerUuid = $roomData['challenger_uuid'];
    $nowStatus = $roomData['status'];
?>


<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> <?= htmlspecialchars($roomName) ?>의 방 </title>
    <link rel="stylesheet" href="css/room_style.css">
</head>

<body>
    <?php include './css/room_style.php'; ?>



<!-- JS -->
<script src="/socket.io/socket.io.js"></script>
<script type="module">
    // JS module
    import { 
        adjustName, 
        colorMove,
        updateRoomPart, 
        updateReadyPart,
        updateActionPart, 
        updateGamePart, 
        updateSpectatorPart, 
        enableButton 
    } from './utils/room/room_style.js';

    // websocket
    const socket = io('http://124.50.137.165', {
        path: '/socket.io',
        transports: ['websocket']
    });

    const roomId = '<?= $_GET['id'] ?>';
    const userUuid = '<?= $_SESSION['uuid'] ?>' || null;
    const userName = '<?= $_SESSION['name'] ?>' || null;
    const ownerUuid = '<?= $ownerUuid ?>' || null;
    const challengerUuid = '<?= $challengerUuid ?>' || null;

    let imOwner = ownerUuid && (ownerUuid === userUuid);
    let imChallenger = challengerUuid && (challengerUuid === userUuid);
    let nowStatus = <?= $nowStatus ?>;
    let hasChosen = false;


    // A-1) JOIN
    socket.emit('join', imOwner ? 0 : imChallenger ? 1 : -1, roomId, userUuid, userName);

    // B-0) INVALID ROOM
    socket.on('invalid', () => {
        console.log(`[INVALID]`);
        window.location.href = './';
    });

    // B-0-1) ROOM DELETED
    socket.on('abandoned', () => {
        console.log(`[ABANDONED]`);
        alert('방장 튐;;');
        window.location.href = './';
    });

    // B-0-2) PAUSE (DISCONNECTION) 미구현
    socket.on('pause', () => {
        console.log(`[PAUSE]`);
    });

    // A-2-1) GET READY
    document.getElementById('start-game').addEventListener('click', () => {
        if (nowStatus === 0) {
            console.log(`ready`);
            socket.emit('ready', imOwner ? 0 : imChallenger ? 1 : -1, roomId, userUuid);
        }
    });

    // A-2-2) QUIT
    document.getElementById('quit-game').addEventListener('click', () => {
        if (imOwner) {
            console.log(`closeRoom`);
            alert('방삭튀');
            socket.emit('closeRoom', roomId, userUuid);
        } else if (imChallenger) {
            console.log(`challengerGiveUp`);
            alert('관전으로 전환');
            socket.emit('challengerGiveUp', roomId, userUuid, userName);
        }
    });


    // A-3) SELECT MOVE
    document.querySelectorAll('.choice').forEach(choice => {

        choice.addEventListener('click', () => {
            const move = choice.textContent.trim();

            if (nowStatus === 1 && !hasChosen) {
                if (imOwner || imChallenger) {
                    console.log(`click`);

                    socket.emit('getMove', imOwner ? 0 : imChallenger ? 1 : null, roomId, move);
                    hasChosen = true;

                    document.querySelectorAll('.player .choice').forEach(button => {
                        button.classList.add('disabled');
                    });
                }
            }
        });
    });



    // B-1) UPDATE ROOM
    socket.on('updateRoom', (roomData) => {
        console.log(`[UPDATE ROOM]`, {roomData});

        const { owner, challenger, status, result, ready, etc, ownerUuid, challengerUuid } = roomData;
    
        imOwner = ownerUuid && (ownerUuid === userUuid);
        imChallenger = challengerUuid && (challengerUuid === userUuid);
        nowStatus = status;

        updateRoomPart(imOwner, imChallenger, roomData)
    });

    // B-2-1) READY
    socket.on('ready', (ready, here) => {
        console.log(`[READY]`, {ready, here});
        updateReadyPart(imOwner, imChallenger, ready, here)
    });

    // B-3) UPDATE GAME
    socket.on('updateGame', (gameData, result) => {
        console.log(`[UPDATE GAME]`, {gameData, result});

        const { now, past, cnt } = gameData

        updateGamePart(imOwner, imChallenger, gameData, result);
        enableButton(imOwner, imChallenger, cnt, nowStatus);

        hasChosen = false; // 생각중 - 시작할 때도 관리해야하나

        if (result === 0){
            // C-1) check Reception
            socket.emit('ReceivedCountDown', imOwner? 0 : imChallenger? 1 : -1, roomId, 3);
        } else if (result === 1 || result === -1) {
            const countdownElement = document.querySelector('.vs');
            countdownElement.textContent = 'VS';
        }
    });

    // B-4) UPDATE spectators
    socket.on('updateSpectators', (spectators) => {
        console.log(`[SPECTATOR JOIN]`, {spectators});
        updateSpectatorPart(imOwner, imChallenger, spectators)
    });

    // B-5) START, END
    socket.on('setStatus', (status) => {
        console.log(`[SET STATUS]`, {status});
        nowStatus = status;
        updateActionPart(imOwner, imChallenger, status);
    });

    // C-2) COUNTDOWN
    socket.on('countdown', (time) => {
        console.log(`[COUNT]`, {time});
        const countdownElement = document.querySelector('.vs');
        countdownElement.textContent = time;
        // C-1) check Reception
        socket.emit('ReceivedCountDown', imOwner? 0 : imChallenger? 1 : -1, roomId, time);

        // 0을 받으면
    });
</script>

</body>
</html>
