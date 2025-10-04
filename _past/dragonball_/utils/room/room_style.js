// Adjust Name font size
export function adjustName(element, maxFontSize, minFontSize) {
    const textLength = element.textContent.length;
    const maxLength = 7;
    const minLength = 5;
    const fontSize = maxFontSize - ((textLength - minLength) / (maxLength - minLength)) * (maxFontSize - minFontSize);
    const clampedFontSize = `clamp(12px, ${Math.max(minFontSize, Math.min(maxFontSize, fontSize))}vw, 30px)`;
    element.style.fontSize = clampedFontSize;
}

// color Move
export function colorMove(move, one = false) {
    const colors = {
        '기': 'red-text',
        '파': 'blue-text',
        '막': 'green-text',
        '에네': 'purple-text',
        '순간': 'yellow-text'
    };

    const span = document.createElement('span');
    span.className = `${colors[move] || ''}`;
    span.textContent = one ? (move || '?')[0] : (move || '?');
    return span;
}



export function updateRoomPart(imOwner, imChallenger, roomData) {
    console.log(`updateRoomPart`, {imOwner, imChallenger, roomData});

    const { owner, challenger, status, result, ready, here, spectator } = roomData;

    document.title = `${owner.name}의 방`;

    // Name font size
    adjustName(document.querySelector('#opponent-name'), 5, 2);
    adjustName(document.querySelector('#player-name'), 5, 2);

    // Name, W L R
    document.getElementById('opponent-name').textContent = imOwner ? (challenger ? challenger.name : "-") : (owner ? owner.name : "-");
    document.getElementById('player-name').textContent = imOwner ? (owner ? owner.name : "-") : (challenger ? challenger.name : "-");

    document.getElementById('opponent-wins').textContent = imOwner ? (challenger ? challenger.win : '-') : (owner ? owner.win : "-");
    document.getElementById('opponent-losses').textContent = imOwner ? (challenger ? challenger.lose : '-') : (owner ? owner.lose : "-");
    document.getElementById('opponent-rating').textContent = imOwner ? (challenger ? challenger.rating : '-') : (owner ? owner.rating : "-");

    document.getElementById('player-wins').textContent = imOwner ? (owner ? owner.win : "-") : (challenger ? challenger.win : '-');
    document.getElementById('player-losses').textContent = imOwner ? (owner ? owner.lose : "-") :(challenger ? challenger.lose : '-');
    document.getElementById('player-rating').textContent = imOwner ? (owner ? owner.rating : "-") : (challenger ? challenger.rating : '-');

    // Include update ready, spectators
    updateReadyPart(imOwner, imChallenger, ready, here, result);
    updateSpectatorPart(imOwner, imChallenger, spectator);
    updateActionPart(imOwner, imChallenger, status);
    enableButton(imOwner, imChallenger, [0, 0], status);
}

export function updateReadyPart(imOwner, imChallenger, ready, here, result = null) {
    // 미구현 -> 좀 더 쎄게 & status가 1이면 무조건 bold?

    console.log(`updateReadyPart`, {imOwner, imChallenger, ready, here});

    if (ready[0] === true) {
        document.getElementById(imOwner ? 'player-name' : 'opponent-name').style.color = "white";  
    } else {
        document.getElementById(imOwner ? 'player-name' : 'opponent-name').style.color = "grey"; 
    }

    if (ready[1] === true) {
        document.getElementById(imOwner ? 'opponent-name' : 'player-name').style.color = "white";
    } else {
        document.getElementById(imOwner ? 'opponent-name' : 'player-name').style.color = "grey";    
    }

    if (ready[0] && here[0]) {
        document.getElementById(imOwner ? 'player-name' : 'opponent-name').style.fontWeight = "bold";
    } else {
        document.getElementById(imOwner ? 'player-name' : 'opponent-name').style.fontWeight = "normal";
    }

    if (ready[1] && here[1]) {
        document.getElementById(imOwner ? 'opponent-name' : 'player-name').style.fontWeight = "bold";
    } else {
        document.getElementById(imOwner ? 'opponent-name' : 'player-name').style.fontWeight = "normal";
    }

    if (result === 1 || result === -1) {
        document.getElementById(imOwner ? 'player-name' : 'opponent-name').style.color = result === 1 ? "blue" : result === -1 ? "red" : "grey" ;
        document.getElementById(imOwner ? 'opponent-name' : 'player-name').style.color = result === 1 ? "red" : result === -1 ? "blue" : "grey" ;
    }

}



export function updateActionPart(imOwner, imChallenger, status) {
    console.log(`updateActionPart`, {imOwner, imChallenger, status});

    // default
    document.querySelector('.game-buttons').style.display = 'none';

    // If im joining
    if (imOwner || imChallenger) {
        document.querySelector('.game-buttons').style.display = '';

        // button changes by status
        if (status === 1) {
            document.getElementById('start-game').style.display = 'none';
            document.getElementById('quit-game').style.display = 'none';
        } else if ((status === 0)) {
            document.getElementById('start-game').style.display = '';
            document.getElementById('quit-game').style.display = '';
        }
    }
}



export function updateGamePart(imOwner, imChallenger, gameData, result) {
    console.log(`updateGamePart`, {imOwner, imChallenger, gameData, result});

    const { now, past, cnt } = gameData || { now: [null, null], past: [[] , []], cnt: [0, 0] };

    // Get History Container & remove past History
    const historyContainer = document.getElementById('history-container');
    historyContainer.innerHTML = '';

    // 1) Show Current #Moves
    const num_Moves = Math.max(past[0]?.length || 0, past[1]?.length || 0);

    const entryHeader = document.createElement('div');
    entryHeader.classList.add('entry-header', 'bold-text');
    entryHeader.innerHTML = `<span>${num_Moves} 턴</span>`;
    historyContainer.appendChild(entryHeader);


    // Get 5 Moves, cnt (2 is challenger)
    const MovesArray2 = (past && past[1]) ? past[1].slice(-5).reverse() : [];
    const Moves2 = MovesArray2.slice(0, 5).map((move, index) => index === 0 ? `<b>${colorMove(move, true).outerHTML}</b>` : colorMove(move, true).outerHTML).join('') + (past && past[1] && (past[1].length > 5) ? '...' : '');
    const Cnt2 = (cnt && cnt[1] !== undefined) ? cnt[1] : 0;

    const Entry2 = document.createElement('div');
    Entry2.classList.add('entry');
    Entry2.innerHTML = `<span class="cnt bold-text">${Cnt2}기 | </span> <span class="moves">${Moves2}</span>`;

    // Get 5 Moves, cnt (1 is owner)
    const MovesArray1 = (past && past[0]) ? past[0].slice(-5).reverse() : [];
    const Moves1 = MovesArray1.slice(0, 5).map((move, index) => index === 0 ? `<b>${colorMove(move, true).outerHTML}</b>` : colorMove(move, true).outerHTML).join('') + (past && past[0] && (past[0].length > 5) ? '...' : '');
    const Cnt1 = (cnt && cnt[0] !== undefined) ? cnt[0] : 0;

    const Entry1 = document.createElement('div');
    Entry1.classList.add('entry');
    Entry1.innerHTML = `<span class="cnt bold-text">${Cnt1}기 | </span> <span class="moves">${Moves1}</span>`;
    
    // 2) Show History (opponent goes up)
    historyContainer.appendChild(imOwner? Entry2 : Entry1);
    historyContainer.appendChild(imOwner? Entry1 : Entry2);

    // 3) Show Result
    const last = (past && past[0] && past[0].length >= 1) ? [past[0][past[0].length - 1], past[1][past[1].length - 1]] : ['?', '?'];

    document.querySelector('.player-choice').innerHTML = '';
    document.querySelector('.player-choice').appendChild(colorMove(imOwner ? last[0] : last[1]))
    document.querySelector('.opponent-choice').innerHTML = '';
    document.querySelector('.opponent-choice').appendChild(colorMove(imOwner ? last[1] : last[0]))
}



export function updateSpectatorPart(imOwner, imChallenger, spectators) {
    console.log(`updateSpectatorPart`, {imOwner, imChallenger, spectators});

    document.getElementById('spectator-names').textContent = spectators.join(', ') || '-';
    document.getElementById('spectator-count').textContent = `(${spectators.length}명)`;
}



export function enableButton(imOwner, imChallenger, cnt, status = 1) {
    console.log(`enableButton`, {imOwner, imChallenger, cnt});

    // joining player buttons & (ongoing status)
    if ( (imOwner || imChallenger) && (status === 1) ) {
        const myCnt = imOwner ? cnt[0] : imChallenger ? cnt[1] : -1;

        document.querySelectorAll('.player .choice').forEach(button => {
            const move = button.textContent.trim();

            if (move === '파' && myCnt < 1) {
                button.classList.add('disabled');
            } else if (move === '에네' && myCnt < 3) {
                button.classList.add('disabled');
            } else if (move === '순간' && myCnt < 1) {
                button.classList.add('disabled');
            } else {
                button.classList.remove('disabled');
            }
        });
    } 

    // spectator & opponent buttons
    else {
        console.log('CHECK');
        document.querySelectorAll('.player .choice').forEach(button => {
            button.classList.add('disabled');
        });
    }
}
