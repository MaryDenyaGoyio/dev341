<div class="game-container">
    
    <!-- Opponent -->
    <div class="opponent">
        <div class="opponent-info">
            <h2 id="opponent-name"></h2>
            <p>W: <span id="opponent-wins"></span> | L: <span id="opponent-losses"></span></p>
            <p>R: <span id="opponent-rating"></span></p>
        </div>
        <div class="choices">
            <div class="choice disabled">기</div>
            <div class="choice disabled">파</div>
            <div class="choice disabled">막</div>
            <div class="choice disabled">에네</div>
            <div class="choice disabled">순간</div>
        </div>
    </div>

    <!-- Count -->
    <div class="game-result">
        <div class="spectator-container">
            <p class="spectator-names" id="spectator-names"></p>
            <p class="spectator-count" id="spectator-count"></p>
        </div>

        <div class="selection-result">
            <span class="opponent-choice" id="opponent-choice">?</span>
            <div class="vs" id="vs-result">VS</div>
            <span class="player-choice" id="player-choice">?</span>
        </div>
        <div class="history" id="history-container">

        </div>
    </div>

    <!-- player -->
    <div class="player">
        <div class="player-info">
            <h2 id="player-name"></h2>
            <p>W: <span id="player-wins"></span> | L: <span id="player-losses"></span></p>
            <p>R: <span id="player-rating"></span></p>
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

<!-- Start, Quit -->
<div class="game-buttons" style="display:none;">
    <div class="start-game">
        <button id="start-game" class="button">READY</button>
    </div>
    <div class="quit-game">
        <button id="quit-game" class="button">LEAVE</button>
    </div>
</div>