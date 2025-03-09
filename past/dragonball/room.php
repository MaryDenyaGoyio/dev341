<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>의 방</title>
    
    <link rel="stylesheet" href="room/room_style.css">
    <style>

    </style>
</head>
<body>

<div class="game-container">
    <!-- Player1 (Opponent) -->
    <div class="opponent">
        <div class="opponent-info">
            <h2 id="player1-name"></h2>
            <p>W: <span id="player1-wins"></span> | L: <span id="player1-losses"></span></p>
            <p>R: <span id="player1-rating"></span></p>
        </div>
        <div class="choices">
            <div class="choice disabled">기</div>
            <div class="choice disabled">파</div>
            <div class="choice disabled">막</div>
            <div class="choice disabled" >에네</div>
            <div class="choice disabled">순간</div>
        </div>
    </div>

    <!-- Game -->
    <div class="game-result">
        <div class="selection-result">
            <span class="opponent-choice" id="opponent-choice">?</span>
            <div class="vs" id="vs-result">VS</div>
            <span class="player-choice" id="player-choice">?</span>
        </div>
        <div class="history" id="history-container">

        </div>
    </div>

    <!-- Player2 (self) -->
    <div class="player">
        <div class="player-info">
            <h2 id="player2-name"></h2>
            <p>W: <span id="player2-wins"></span> | L: <span id="player2-losses"></span></p>
            <p>R: <span id="player2-rating"></span></p>
        </div>
        <div class="choices">
            <div class="choice disabled">기</div>
            <div class="choice disabled">파</div>
            <div class="choice disabled">막</div>
            <div class="choice disabled">에네</div>
            <div class="choice disabled">순간</div>
        </div>
    </div>
</div>

<!-- Start, Quit Game -->
<div class="game-buttons" style="display:none;">
    <div class="start-game">
        <button id="start-game" class="button">Start Game</button>
    </div>
    <div class="quit-game">
        <button id="quit-game" class="button">Quit Game</button>
    </div>
</div>



<script src="/socket.io/socket.io.js"></script>
<script>
    const socket = io('http://124.50.137.165', {
        path: '/socket.io',
        transports: ['websocket']
    });

    const roomId = '<?= $_GET['room_id'] ?>';
    const userUuid = '<?= $_SESSION['uuid'] ?>';
    let imOwner = false;
    let imChallenger = false;
    let hasChosen = false;
    let nowStatus = 0;

    function adjustFontSize(element, maxFontSize, minFontSize) {
        const textLength = element.textContent.length;
        const maxLength = 7; // 기준이 될 최대 글자 수
        const minLength = 5;  // 기준이 될 최소 글자 수

        // 글자 수에 따라 폰트 크기 계산
        const fontSize = maxFontSize - ((textLength - minLength) / (maxLength - minLength)) * (maxFontSize - minFontSize);
        const clampedFontSize = `clamp(12px, ${Math.max(minFontSize, Math.min(maxFontSize, fontSize))}vw, 30px)`;

        // 스타일에 적용
        element.style.fontSize = clampedFontSize;
        console.log(`이거 : ${textLength}`);
        console.log(`이거라서 : ${fontSize}px`);
        console.log(`이거야 : ${`${Math.max(minFontSize, Math.min(maxFontSize, fontSize))}vw`}`);
    }

    // 적용할 텍스트 요소에 대해 adjustFontSize를 호출하는 예시
    const playerChoiceElement = document.querySelector('#player1-name');
    const opponentChoiceElement = document.querySelector('#player2-name');

    // 텍스트 색상과 굵기를 반영하는 함수
    function setChoiceText(element, choice) {
        element.classList.remove('red-text', 'blue-text', 'green-text', 'purple-text', 'yellow-text'); // 이전 색상 제거
        element.classList.add('bold-text'); // 글씨를 굵게

        switch (choice) {
            case '기':
                element.classList.add('red-text');
                break;
            case '파':
                element.classList.add('blue-text');
                break;
            case '막':
                element.classList.add('green-text');
                break;
            case '에네':
                element.classList.add('purple-text');
                break;
            case '순간':
                element.classList.add('yellow-text');
                break;
        }
        element.textContent = choice;
    }

    // 기록에 맞는 텍스트와 색상을 설정하는 함수 (HTML용)
    function setChoiceTextForHistory(choice) {
        let colorClass = '';
        switch (choice) {
            case '기':
                colorClass = 'red-text';
                break;
            case '파':
                colorClass = 'blue-text';
                break;
            case '막':
                colorClass = 'green-text';
                break;
            case '에네':
                colorClass = 'purple-text';
                break;
            case '순간':
                colorClass = 'yellow-text';
                break;
            default:
                return choice;
        }
        return `<span class="${colorClass}">${choice}</span>`;
    }

    // 최근 다섯 개 기록을 표시하는 함수
    function updateHistory(past, cnt, isOwner) {
        if (past !== undefined) {
            console.log(`UPDATE HISTORY`);

            const historyContainer = document.getElementById('history-container');
            historyContainer.innerHTML = ''; // 기존 기록 삭제

            // past 배열이 정의되지 않거나 비어 있을 경우 기본값 설정
            const opponentMovesArray = (past && past[1]) ? past[1].slice(-5).reverse() : [];
            const myMovesArray = (past && past[0]) ? past[0].slice(-5).reverse() : [];

            // cnt 배열이 정의되지 않았을 경우 기본값 설정
            const opponentCnt = (cnt && cnt[1] !== undefined) ? cnt[1] : 0;
            const myCnt = (cnt && cnt[0] !== undefined) ? cnt[0] : 0;

            // 1) 제일 윗줄에 past의 길이 + 수를 추가하고 굵게 처리
            const totalMoves = Math.max(past[0]?.length || 0, past[1]?.length || 0); // past 배열의 최대 길이 계산
            const entryHeader = document.createElement('div');
            entryHeader.classList.add('entry-header', 'bold-text');
            entryHeader.innerHTML = `<span>${totalMoves} 수</span>`;
            historyContainer.appendChild(entryHeader); // history container에 추가

            // 상대방의 기록 표시
            const opponentMoves = opponentMovesArray.map(setChoiceTextForHistory).join(' ') || '';
            const opponentEntry = document.createElement('div');
            opponentEntry.classList.add('entry');
            opponentEntry.innerHTML = `<span class="cnt bold-text">${opponentCnt}기 | </span> <span class="moves">${opponentMoves}</span>`;

            // 나의 기록 표시
            const myMoves = myMovesArray.map(setChoiceTextForHistory).join(' ') || '';
            const myEntry = document.createElement('div');
            myEntry.classList.add('entry');
            myEntry.innerHTML = `<span class="cnt bold-text">${myCnt}기 | </span> <span class="moves">${myMoves}</span>`;

            // 기록을 history container에 추가 (Owner 여부에 따라 순서 결정)
            if (isOwner) {
                historyContainer.appendChild(opponentEntry);
                historyContainer.appendChild(myEntry);
            } else {
                historyContainer.appendChild(myEntry);
                historyContainer.appendChild(opponentEntry);
            }
        }
    }

    function updateChoice(gameData, result) {
        const { now, past, cnt } = gameData || { now: [null, null], past: [[] , []], cnt: [0, 0] }; // gameRecord 내의 past, cnt, now를 분리

        console.log(`UPDATE CHOICE ${JSON.stringify(gameData)}`);

        // 1) imOwner를 통해 player-choice와 opponent-choice를 업데이트
        const last = now.every(i => i !== null) ? now : (past && past[0] && past[0].length >= 1) ? [past[0][past[0].length - 1], past[1][past[1].length - 1]] : ['?', '?'];
        console.log(`WHYWHY ${last}`);

        if (imOwner) {
            // 내가 owner라면, 내 선택은 player-choice, 상대방의 선택은 opponent-choice에 반영
            setChoiceText(document.querySelector('.player-choice'), last[0]); // owner의 현재 선택
            setChoiceText(document.querySelector('.opponent-choice'), last[1]); // challenger의 현재 선택
        } else {
            // 내가 challenger라면, 내 선택은 opponent-choice, 상대방의 선택은 player-choice에 반영
            setChoiceText(document.querySelector('.player-choice'), last[1]); // challenger의 현재 선택
            setChoiceText(document.querySelector('.opponent-choice'), last[0]); // owner의 현재 선택
        }

        if ((nowStatus !== -1) || (result === 0)){
            if (imOwner || imChallenger) {
                // 2) Count에 따른 버튼 disable 여부 설정
                const myCnt = imOwner ? cnt[0] : imChallenger ? cnt[1] : 0; // owner와 challenger의 카운트 분리

                // 각 선택지 버튼을 카운트에 따라 비활성화
                document.querySelectorAll('.player .choice').forEach(button => {
                    const move = button.textContent.trim();

                    if (move === '파' && myCnt < 1) {
                        button.classList.add('disabled'); // 카운트가 1 이상이어야 '파'를 선택 가능
                    } else if (move === '에네' && myCnt < 3) {
                        button.classList.add('disabled'); // 카운트가 3 이상이어야 '에네' 선택 가능
                    } else if (move === '순간' && myCnt < 1) {
                        button.classList.add('disabled'); // 카운트가 1 이상이어야 '순간' 선택 가능
                    } else {
                        button.classList.remove('disabled'); // 카운트가 충분하면 선택 가능
                    }
                });
            }
        } else {
            document.querySelectorAll('.player .choice').forEach(button => {
                button.classList.add('disabled');
            });
        }
    }


    // A-1) JOIN
    socket.emit('joinRoom', roomId, userUuid);


    // B-1) UPDATE
    socket.on('updateRoom', (roomData, gameData) => {

        const { owner, challenger, status, result, ready, etc, isOwner, isChallenger } = roomData;
        const { now, past, cnt, isNow } = gameData || { now: null, past: undefined, cnt: undefined };
    
        document.title = `${owner.name}의 방`;

        imOwner = isOwner;
        imChallenger = isChallenger;
        hasChosen = isNow !== null;
        nowStatus = status;

        console.log(`UPDATE ${JSON.stringify(roomData)}`);
        console.log(`UPDATE ${JSON.stringify(gameData)}`);

        document.querySelector('.game-buttons').style.display = 'none';
        document.getElementById('player2-name').style.fontWeight = "normal";

        if (status !== 0 && gameData !== undefined) {
            updateHistory(past, cnt, isOwner);
            updateChoice(gameData, null);
        }

        if (isOwner || isChallenger) {
            document.querySelector('.game-buttons').style.display = '';

            if (status !== 0) {
                document.getElementById('start-game').style.display = 'none';
            }

            if (status === -1) {
                document.getElementById('quit-game').textContent = 'Leave Room';
            }
        }

        if (isOwner) {
            document.getElementById('player1-name').textContent = challenger ? challenger.name : "-";
            document.getElementById('player2-name').textContent = owner.name;

            document.getElementById('player1-wins').textContent = challenger ? challenger.win : '-';
            document.getElementById('player1-losses').textContent = challenger ? challenger.lose : '-';
            document.getElementById('player1-rating').textContent = challenger ? challenger.rating : '-';

            document.getElementById('player2-wins').textContent = owner.win;
            document.getElementById('player2-losses').textContent = owner.lose;
            document.getElementById('player2-rating').textContent = owner.rating;

            if (ready[0] && etc[0] === 0) {
                document.getElementById('player2-name').style.fontWeight = "bold";
            } else {
                document.getElementById('player2-name').style.fontWeight = "normal";
            }

            if (ready[1] && etc[1] === 0) {
                document.getElementById('player1-name').style.fontWeight = "bold";
            } else {
                document.getElementById('player1-name').style.fontWeight = "normal";
            }

        } else {
            document.getElementById('player1-name').textContent = owner.name;
            document.getElementById('player2-name').textContent = challenger ? challenger.name : "-";

            document.getElementById('player1-wins').textContent = owner.win;
            document.getElementById('player1-losses').textContent = owner.lose;
            document.getElementById('player1-rating').textContent = owner.rating;

            document.getElementById('player2-wins').textContent = challenger ? challenger.win : '-';
            document.getElementById('player2-losses').textContent = challenger ? challenger.lose : '-';
            document.getElementById('player2-rating').textContent = challenger ? challenger.rating : '-';

            if (ready[0] && etc[0] === 0) {
                console.log(`이거 아닌가`);
                document.getElementById('player1-name').style.fontWeight = "bold";
                console.log(`이거 아닌가`);
            } else {
                document.getElementById('player1-name').style.fontWeight = "normal";
            }

            if (ready[1] && etc[1] === 0) {
                document.getElementById('player2-name').style.fontWeight = "bold";
            } else {
                document.getElementById('player2-name').style.fontWeight = "normal";
            }
        }

        adjustFontSize(playerChoiceElement, 5, 2);
        adjustFontSize(opponentChoiceElement, 5, 2);

        if (status === -1) {
            document.getElementById('player2-name').style.color = "grey";
            document.getElementById('player1-name').style.color = "grey";
            if ((isOwner && result === 1) || (!isOwner && result === -1)) {
                document.getElementById('player2-name').style.color = "blue";
                document.getElementById('player1-name').style.color = "red";
            } else if ((!isOwner && result === 1) || (isOwner && result === -1)) {
                document.getElementById('player1-name').style.color = "blue";
                document.getElementById('player2-name').style.color = "red";
            }
        }
    });



    
    // A-2-1) START
    document.getElementById('start-game').addEventListener('click', () => {
        socket.emit('gameAction', 'ready', roomId, userUuid);
    });

    // A-2-2) QUIT
    document.getElementById('quit-game').addEventListener('click', () => {
        if (nowStatus === -1) {
            window.location.href = '../';
        } else {
            alert('게임을 종료합니다.');
            socket.emit('gameAction', 'quit', roomId, userUuid);
        }
    });



    // B-2) EVENT
    socket.on('eventRoom', (outcome) => {
        if (outcome === 'Abort'){
            console.log(`owner left`);
            alert('소유자가 방을 나갔습니다.');
        }

        if (outcome === 'Start'){
            console.log(`game started`);
        }

        if (outcome === 'Pause'){
            console.log(`opponent left`);
            // const countdownElement = document.querySelector('.vs');
            // countdownElement.textContent = '⏸︎';
        }

        if (outcome === 'Resume'){
            console.log(`opponent rejoined`);
        }
    });


    // A-3) CHOICE
    document.querySelectorAll('.choice').forEach(choice => {
        choice.addEventListener('click', () => {
            if (hasChosen) return;

            const move = choice.textContent.trim();
            
            let playerType = imOwner ? 0 : imChallenger ? 1 : null;

            if (playerType !== null) {
                socket.emit('getMove', roomId, playerType, move);
                hasChosen = true;

                document.querySelectorAll('.player .choice').forEach(button => {
                    button.classList.add('disabled');
                });

                console.log(`CHOICE ${hasChosen} ${move}`);
            }
        });
    });



    // B-3) RESULT
    socket.on('gameResult', (gameData, result) => {
        const { now, past, cnt } = gameData

        console.log(`[RESULT] ${JSON.stringify(gameData)}`);

        updateHistory(past, cnt, imOwner);
        updateChoice(gameData)

        if (!(result === 1 || result === -1)){
            // hasChosen 상태 초기화
            hasChosen = false;

            // 결과 수신 후 서버에 알림
            if (imOwner) {
                socket.emit('resultReceived', roomId, 0);
            } else if (imChallenger) {
                socket.emit('resultReceived', roomId, 1);
            }
        }
    });



    socket.on('countdown', (number) => {
        const countdownElement = document.querySelector('.vs');
        countdownElement.textContent = number;
        console.log(`COUNTDOWN ${number}`);
    });

    socket.on('back', () => {
        window.location.href = '../';
    });
</script>

</body>
</html>
