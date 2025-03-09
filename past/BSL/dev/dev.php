<?php

/* ==============
    데이터 가져오기
============== */

/* MySQL 연결 */
$host = 'localhost';
$username = 'root';
$password = 'e^ipi=-1'; 
$dbname = 'GameRecords';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Players, Maps, Types 데이터 불러오기 */
$playersQuery = "SELECT * FROM Players";
$playersResult = $conn->query($playersQuery);
$players = [];
while ($player = $playersResult->fetch_assoc()) {
    $players[] = $player;
}
$playersID = [];
foreach($players as $player) {
    $playersID[$player['PlayerID']] = $player;
}

$mapsQuery = "SELECT * FROM Maps";
$mapsResult = $conn->query($mapsQuery);

$typesQuery = "SELECT * FROM Types";
$typesResult = $conn->query($typesQuery);

/* PlayerStats 가져오는 쿼리 */
$PlayerStatsQuery = "SELECT *, 
                        RANK() OVER (ORDER BY Rating DESC) AS Ranking,
                        Players.Name
                    FROM PlayerStats PS
                        JOIN Players ON Players.PlayerID = PS.PlayerID";
$PlayerStatsResult = $conn->query($PlayerStatsQuery);
$PlayerStats = [];
while ($PlayerStat = $PlayerStatsResult->fetch_assoc()) {
    $PlayerStats[$PlayerStat['PlayerID']] = $PlayerStat;
}

/* PlayerMatchups 데이터 불러오기 */
$PlayerMatchupsQuery = "WITH PlayerData AS (
                            SELECT
                                PM.*,
                                (SELECT Wins 
                                    FROM PlayerMatchups PM2 
                                WHERE PM2.Player1ID = PM.Player2ID 
                                    AND PM2.Player2ID = PM.Player1ID) AS Loses
                            FROM PlayerMatchups PM
                        )
                        SELECT
                            *,
                            (Wins + Loses) AS Total,
                            (Wins * 1.0 / (Wins + Loses)) AS WinRate
                        FROM PlayerData";
$PlayerMatchupsResult = $conn->query($PlayerMatchupsQuery);
$PlayerMatchups = [];
while ($row = $PlayerMatchupsResult->fetch_assoc()) {
    $PlayerMatchups[$row['Player1ID']][$row['Player2ID']] = $row;
}

// 종족 Array
$races = ['Z', 'T', 'P'];
$raceName = ['Z' => 'Zerg', 'T' => 'Terran', 'P' => 'Protoss'];

// 종족별 색깔
$raceColorMap = array(
    'Z' => '#4c00a4',
    'T' => '#0C48CC',
    'P' => '#8B8000',
    'Zerg' => '#4c00a4',
    'Terran' => '#0C48CC',
    'Protoss' => '#8B8000',
);

/* RaceMatchups 데이터 불러오기 */
$RaceMatchupsQuery = "WITH RaceData AS (
                            SELECT Race1, Race2, Wins,
                                (SELECT RM2.Wins
                                    FROM RaceMatchups RM2 
                                WHERE RM2.Race1 = RM.Race2
                                    AND RM2.Race2 = RM.Race1) AS Loses
                            FROM RaceMatchups RM
                        )
                        SELECT
                            *,
                            (Wins + Loses) AS Total,
                            (Wins * 1.0 / (Wins + Loses)) AS WinRate
                        FROM RaceData";
$RaceMatchupsResult = $conn->query($RaceMatchupsQuery);
$RaceMatchups = [];
while ($row = $RaceMatchupsResult->fetch_assoc()) {
    $RaceMatchups[$row['Race1']][$row['Race2']] = $row;
}



/* ================================
    Graph용 데이터를 추출하는 PHP코드 
==================================*/

/* Games 데이터에 Players, Maps, Types JOIN해서 불러오는 쿼리 */
$GamesQuery = "SELECT GP.PlayerID,
                    G.*, 
                    Player1.Name as Player1Name, 
                    Player2.Name as Player2Name, 
                    Maps.Name as MapName, 
                    Types.TypeName
                FROM Games G
                    RIGHT JOIN (SELECT Player1.PlayerID as PlayerID, Player1.Name as Name
                        FROM Games G1 JOIN Players AS Player1 ON G1.Player1ID = Player1.PlayerID
                        UNION
                        SELECT Player2.PlayerID as PlayerID, Player2.Name as Name
                        FROM Games G2 JOIN Players AS Player2 ON G2.Player2ID = Player2.PlayerID) AS GP
                        ON (GP.PlayerID = G.Player1ID OR GP.PlayerID = G.Player2ID)
                    JOIN Players AS Player1 ON G.Player1ID = Player1.PlayerID 
                    JOIN Players AS Player2 ON G.Player2ID = Player2.PlayerID 
                    JOIN Maps ON G.MapID = Maps.MapID 
                    JOIN Types ON G.TypeID = Types.TypeID 
                ORDER BY Date ASC, GameID ASC ";
$GamesResult = $conn->query($GamesQuery);
$Games = [];
while ($game = $GamesResult->fetch_assoc()) {
    $Games[] = $game;
}

/* Games에 참여하는 Players만 찾아내는 쿼리 */
$GamePlayersQuery = "SELECT GP.*, PS.*
                        FROM PlayerStats PS
                        RIGHT JOIN (SELECT DISTINCT P.PlayerID, P.Name
                                FROM Games G 
                                JOIN Players P
                                    ON (G.Player1ID = P.PlayerID OR G.Player2ID = P.PlayerID)) AS GP
                            ON PS.PlayerID = GP.PlayerID
                        ORDER BY PS.Rating DESC";
$GamePlayersResult = $conn->query($GamePlayersQuery);
$GamePlayers = [];
while ($row = $GamePlayersResult->fetch_assoc()) {
    $GamePlayers[] = $row;
}

$GraphDatas = []; // Graph용 데이터 추출

$GraphRating = [];
/* 초기 선수들의 Rating은 1000 */
foreach ($GamePlayers as $game) {
    $GraphRating[$game['PlayerID']] = 1000;
    $GraphDatas[$game['Name']] = [['x' => 0, 'y' => (int)$GraphRating[$game['PlayerID']]]];
}

/* 이후 선수들의 Rating도 등록 */

foreach ($Games as $t => $game) {
    $GraphRating[$game['Player1ID']] = (int)$game['Player1RatingAfter'];
    $GraphRating[$game['Player2ID']] = (int)$game['Player2RatingAfter'];

    foreach ($GamePlayers as $row) {
        $GraphDatas[$row['Name']][] = [ 'x' => (int)($t+1), 'y' => (int)$GraphRating[$row['PlayerID']] ];
    }
}



/* ==============
    각종 함수들
============== */

$K = 50;  // ELO Rating - K값

/* ELO Rating 계산 함수 */
function ELO($R1, $R2) {
    global $K;

    $Q1 = pow(10, $R1 / 400);
    $Q2 = pow(10, $R2 / 400);

    $E1 = $Q1 / ($Q1 + $Q2);
    $E2 = $Q2 / ($Q1 + $Q2);

    $R1_After = $R1 + round($K * $E2);
    $R2_After = $R2 - round($K * $E2);

    return [$R1_After, $R2_After];
}

/* Games의 모든 ELO Rating을 처음부터 재계산 하는 함수 */
function updateRatings($conn) {

    // 모든 게임을 날짜 순으로 불러오는 쿼리
    $query = "SELECT * FROM Games ORDER BY Date ASC, GameID ASC";
    $games = $conn->query($query);

    $ratings = [];
    while ($game = $games->fetch_assoc()) {
        $player1ID = $game['Player1ID'];
        $player2ID = $game['Player2ID'];

        if (!isset($ratings[$player1ID])) $ratings[$player1ID] = 1000;
        if (!isset($ratings[$player2ID])) $ratings[$player2ID] = 1000;

        $R1 = $ratings[$player1ID];
        $R2 = $ratings[$player2ID];

        list($R1_After, $R2_After) = ELO($R1, $R2);

        $ratings[$player1ID] = $R1_After;
        $ratings[$player2ID] = $R2_After;

        $R_inc = $R1_After - $R1;
        $Upset_01 = ($R1<$R2) ? 1 : 0 ;

        // 레이팅 업데이트를 다시 반영하는 쿼리
        $updateQuery = "UPDATE Games SET Player1RatingBefore=?, Player1RatingAfter=?, Player2RatingBefore=?, Player2RatingAfter=?, RatingInc=?, Upset_yn=? WHERE GameID=?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iiiiiii", $R1, $R1_After, $R2, $R2_After, $R_inc, $Upset_01, $game['GameID']);
        $stmt->execute();
        $stmt->close();
    } 
}

/* Player1의 Rating을 PlayerStats에서 찾는 함수 */
function getRating($playerID) {
    global $PlayerStats;

    $playerRating = array_key_exists($playerID, $PlayerStats) ? $PlayerStats[$playerID]['Rating'] : 1000;
    return $playerRating;
}

/* 승률에 따라 글씨 색깔 바꾸는 함수 */
function getColor($percentage, $range = 1) {
    $red = 255 * ( (1/2 - $range * ($percentage-1/2) > 1)? 1 : ( (1/2 - $range * ($percentage-1/2) < 0)? 0 : 1/2 - $range * ($percentage-1/2)) ) ;
    $blue = 255 * ( (1/2 + $range * ($percentage-1/2) > 1)? 1 : ( (1/2 - $range * ($percentage-1/2) < 0)? 0 : 1/2 + $range * ($percentage-1/2)) );
    return sprintf("#%02x%02x%02x", $red, 0, $blue); 
}

/* 두 날짜 차이 함수 */
function dateDiff($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);

    return $interval->days;
}

/* 폼에서 데이터가 전송된 경우 게임에 저장하는 기능 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {

    $date = $_POST['date'];
    $hours = $_POST['hours'] ?? "00";
    $minutes = $_POST['minutes'] ?? "00";
    $seconds = $_POST['seconds'] ?? "00";
    $length = sprintf("%02d:%02d:%02d", intval($hours), intval($minutes), intval($seconds));
    
    $player1ID = $_POST['player1ID'];
    $player2ID = $_POST['player2ID'];
    $player1Race = $_POST['player1Race'];
    $player2Race = $_POST['player2Race'];
    $player1Dir = $_POST['player1Dir'];
    $player2Dir = $_POST['player2Dir'];
    $mapID = $_POST['mapID'];
    $typeID = $_POST['typeID'];
    $player1APM = $_POST['player1APM'] ?: NULL;
    $player2APM = $_POST['player2APM'] ?: NULL;
    $player1StartingBuild = $_POST['player1StartingBuild'] ?: NULL;
    $player2StartingBuild = $_POST['player2StartingBuild'] ?: NULL;
    $player1B_GO = $_POST['player1B_GO'] ?: NULL;

    $R1 = getRating($player1ID);
    $R2 = getRating($player2ID);

    list($R1_After, $R2_After) = ELO($R1, $R2);

    $RatingInc = $R1_After - $R1;
    $Upset_yn = ($R2 > $R1) ? 1 : 0;
    // Games에 데이터 삽입
    $stmt = $conn->prepare("INSERT INTO Games (Date, Length, Player1ID, Player2ID, Player1Race, Player2Race, Player1Dir, Player2Dir, MapID, TypeID, Player1APM, Player2APM, Player1StartingBuild, Player2StartingBuild, Player1B_GO, Player1RatingBefore, Player1RatingAfter, Player2RatingBefore, Player2RatingAfter, RatingInc, Upset_yn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiissiiiiiisssiiiiii", $date, $length, $player1ID, $player2ID, $player1Race, $player2Race, $player1Dir, $player2Dir, $mapID, $typeID, $player1APM, $player2APM, $player1StartingBuild, $player2StartingBuild, $player1B_GO, $R1, $R1_After, $R2, $R2_After, $RatingInc, $Upset_yn);
    $stmt->execute();
    $stmt->close();

    // page Reload
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

/* 레이팅 업데이트 버튼 클릭 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ratings'])) {
    updateRatings($conn);
    
    // page Reload
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}



/* ===============================
수상 부문에 해당하는 값과 ID 찾기 
================================ */

/* Players별 연승 연패 계산 PHP */
$streaks = [];
foreach ($Games as $game) {
    $playerID = $game['PlayerID'];
    $date = $game['Date'];

    if (!isset($streaks[$playerID])) {
        $streaks[$playerID] = [
            'max_win_streak' => 0, 
            'current_win_streak' => 0, 
            'max_lose_streak' => 0, 
            'current_lose_streak' => 0,
            'current_win_DateStart' => $date,
            'max_win_DateStart' => $date,
            'max_win_DateEnd' => $date,
            'current_lose_DateStart' => $date,
            'max_lose_DateStart' => $date,
            'max_lose_DateEnd' => $date
        ];
    }
    if ($game['Player1ID'] == $playerID) {
        if ($streaks[$playerID]['current_win_streak']==0){
            $streaks[$playerID]['current_win_DateStart'] = $date;
        }
        $streaks[$playerID]['current_win_streak']++;
        $streaks[$playerID]['current_lose_streak'] = 0;
    } else {
        if ($streaks[$playerID]['current_lose_streak']==0){
            $streaks[$playerID]['current_lose_DateStart'] = $date;
        }
        $streaks[$playerID]['current_lose_streak']++;
        $streaks[$playerID]['current_win_streak'] = 0;
    }
    if ($streaks[$playerID]['current_win_streak'] > $streaks[$playerID]['max_win_streak']) {
        $streaks[$playerID]['max_win_streak'] = $streaks[$playerID]['current_win_streak'];

        $streaks[$playerID]['max_win_DateStart'] = $streaks[$playerID]['current_win_DateStart'];
        $streaks[$playerID]['max_win_DateEnd'] = $date;
    }
    if ($streaks[$playerID]['current_lose_streak'] > $streaks[$playerID]['max_lose_streak']) {
        $streaks[$playerID]['max_lose_streak'] = $streaks[$playerID]['current_lose_streak'];

        $streaks[$playerID]['max_lose_DateStart'] = $streaks[$playerID]['current_lose_DateStart'];
        $streaks[$playerID]['max_lose_DateEnd'] = $date;
    }
}

// 최장연승
$MAXWINStreaks = array_map(function ($item) {return $item['max_win_streak'];}, $streaks);
$MAXWINStreakMAX = max($MAXWINStreaks); 
$MAXWINStreakIndex = array_search($MAXWINStreakMAX, $MAXWINStreaks); 
$MAXWINStreakDateStart = $streaks[$MAXWINStreakIndex]['max_win_DateStart'];
$MAXWINStreakDateEnd = $streaks[$MAXWINStreakIndex]['max_win_DateEnd'];

// 최장연패
$MAXLOSEStreaks = array_map(function ($item) {return $item['max_lose_streak'];}, $streaks);
$MAXLOSEStreakMAX = max($MAXLOSEStreaks); 
$MAXLOSEStreakIndex = array_search($MAXLOSEStreakMAX, $MAXLOSEStreaks); 
$MAXLOSEStreakDateStart = $streaks[$MAXLOSEStreakIndex]['max_lose_DateStart'];
$MAXLOSEStreakDateEnd = $streaks[$MAXLOSEStreakIndex]['max_lose_DateEnd'];

/* Rating 변동폭 관련 */
$tracking = [];

foreach ($Games as $game) {
    $playerID = $game['PlayerID'];
    $currentRating = ($playerID == $game['Player1ID']) ? $game['Player1RatingAfter'] : $game['Player2RatingAfter'];
    $gameID = $game['GameID'];
    $date = $game['Date'];

    if (!isset($tracking[$playerID])) {
        $tracking[$playerID] = [
            'minRating' => $currentRating,
            'maxRating' => $currentRating,
            'minGameID' => $gameID,
            'maxGameID' => $gameID,
            'minDate' => $date,
            'maxDate' => $date,
            'maxDiffMinRating' => 0,
            'maxDiffMaxRating' => 0,
            'maxDiffMaxDate' => $date,
            'maxDiffMinDate' => $date,
            'maxDiffMaxDateNow' => $date,
            'maxDiffMinDateNow' => $date,
            'maxNumGames' => 0,
            'minNumGames' => 0,
            'maxDiffMaxNumGames' => 0,
            'maxDiffMinNumGames' => 0
        ];
    } else {
        if ($currentRating < $tracking[$playerID]['minRating']) {
            $tracking[$playerID]['minRating'] = $currentRating;
            $tracking[$playerID]['minGameID'] = $gameID;
            $tracking[$playerID]['minDate'] = $date;
            $tracking[$playerID]['minNumGames'] = 0;
        } else{
            $tracking[$playerID]['minNumGames']++;
        }
        if ($currentRating > $tracking[$playerID]['maxRating']) {
            $tracking[$playerID]['maxRating'] = $currentRating;
            $tracking[$playerID]['maxGameID'] = $gameID;
            $tracking[$playerID]['maxDate'] = $date;
            $tracking[$playerID]['maxNumGames'] = 0;
        } else{
            $tracking[$playerID]['maxNumGames']++;
        }

        // MIN, MAX Rating과 현재 Rating의 차이
        $diffMinRating = $currentRating - $tracking[$playerID]['minRating'];
        $diffMaxRating = $tracking[$playerID]['maxRating'] - $currentRating;

        if ($diffMinRating > $tracking[$playerID]['maxDiffMinRating']) {
            $tracking[$playerID]['maxDiffMinRating'] = $diffMinRating;
            $tracking[$playerID]['maxDiffMinGameID'] = $gameID;
            $tracking[$playerID]['maxDiffMinDate'] = $tracking[$playerID]['minDate'];
            $tracking[$playerID]['maxDiffMinDateNow'] = $date;
            $tracking[$playerID]['maxDiffMinNumGames'] = $tracking[$playerID]['minNumGames'];
        }
        if ($diffMaxRating > $tracking[$playerID]['maxDiffMaxRating']) {
            $tracking[$playerID]['maxDiffMaxRating'] = $diffMaxRating;
            $tracking[$playerID]['maxDiffMaxGameID'] = $gameID;
            $tracking[$playerID]['maxDiffMaxDate'] = $tracking[$playerID]['maxDate'];
            $tracking[$playerID]['maxDiffMaxDateNow'] = $date;
            $tracking[$playerID]['maxDiffMaxNumGames'] = $tracking[$playerID]['maxNumGames'];
        }
    }
}

// 최고상승
$MAXDiffMin = array_map(function ($item) {return $item['maxDiffMinRating'];}, $tracking);
$MAXDiffMinMAX = max($MAXDiffMin); 
$MAXDiffMinIndex = array_search($MAXDiffMinMAX, $MAXDiffMin); 
$MAXDiffMinDate = $tracking[$MAXDiffMinIndex]['maxDiffMinDate'];
$MAXDiffMinDateNow = $tracking[$MAXDiffMinIndex]['maxDiffMinDateNow'];
$MAXDiffMinNumGames = $tracking[$MAXDiffMinIndex]['maxDiffMinNumGames'];

// 최고하락
$MAXDiffMax = array_map(function ($item) {return $item['maxDiffMaxRating'];}, $tracking);
$MAXDiffMaxMAX = max($MAXDiffMax); 
$MAXDiffMaxIndex = array_search($MAXDiffMaxMAX, $MAXDiffMax); 
$MAXDiffMaxDate = $tracking[$MAXDiffMaxIndex]['maxDiffMaxDate'];
$MAXDiffMaxDateNow = $tracking[$MAXDiffMaxIndex]['maxDiffMaxDateNow'];
$MAXDiffMaxNumGames = $tracking[$MAXDiffMaxIndex]['maxDiffMaxNumGames'];

/* 나머지 기록들 */
$highestRating = 0;
$highestRatingPlayerID = null;
$highestRatingDate = null;

$lowestRating = PHP_INT_MAX;
$lowestRatingPlayerID = null;
$lowestRatingDate = null;

$highestInc = PHP_INT_MIN;
$highestIncPlayerID = null;
$highestIncDate = null;

$lowestInc = PHP_INT_MAX;
$lowestIncPlayerID = null;
$lowestIncDate = null;

$longestGame = 0;
$longestPlayerID = null;
$longestGameDate = null;

$shortestGame = PHP_INT_MAX;
$shortestPlayerID = null;
$shortestGameDate = null;

$highestAPM = 0;
$highestAPMPlayerID = null;
$highestAPMDate = null;

$lowestWinningAPM = PHP_INT_MAX;
$lowestWinningAPMPlayerID = null;
$lowestWinningAPMDate = null;

// 결과를 반복 처리합니다.
foreach($Games as $row) {
    // 최고 레이팅과 최저 레이팅
    if ($row['Player1RatingAfter'] > $highestRating) {
        $highestRating = $row['Player1RatingAfter'];
        $highestRatingPlayerID = $row['Player1ID'];
        $highestRatingDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }

    if ($row['Player2RatingAfter'] < $lowestRating) {
        $lowestRating = $row['Player2RatingAfter'];
        $lowestRatingPlayerID = $row['Player2ID'];
        $lowestRatingDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }

    // 최고와 최저 변동폭
    if ($row['RatingInc'] > $highestInc) {
        $highestInc = $row['RatingInc'];
        $highestIncPlayerID = $row['Player1ID'];
        $highestIncDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }
    if ($row['RatingInc'] < $lowestInc) {
        $lowestInc = $row['RatingInc'];
        $lowestIncPlayerID = $row['Player1ID'];
        $lowestIncDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }

    // 최장과 최단 게임
    if ($row['Length'] > $longestGame) {
        $longestGame = $row['Length'];
        $longestPlayerID = $row['Player1ID'];
        $longestGameDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }
    if ($row['Length'] < $shortestGame and $row['Length'] != NULL) {
        $shortestGame = $row['Length'];
        $shortestPlayerID = $row['Player1ID'];
        $shortestGameDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }

    // 최고와 최저 APM
    if ($row['Player1APM'] > $highestAPM) {
        $highestAPM = $row['Player1APM'];
        $highestAPMPlayerID = $row['Player1ID'];
        $highestAPMDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }
    if ($row['Player2APM'] > $highestAPM) {
        $highestAPM = $row['Player2APM'];
        $highestAPMPlayerID = $row['Player2ID'];
        $highestAPMDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }

    if ($row['Player1APM'] < $lowestWinningAPM and $row['Player1APM'] != NULL) {
        $lowestWinningAPM = $row['Player1APM'];
        $lowestWinningAPMPlayerID = $row['Player1ID'];
        $lowestWinningAPMDate = $row['Date'] . ' : ' . $playersID[$row['Player1ID']]['Name'] . ' vs ' . $playersID[$row['Player2ID']]['Name'];
    }
}

// 최다업셋
$MAXUpset = PHP_INT_MIN;
$MAXUpsetPlayerID = null;
$MAXUpsetDate = null;

foreach($GamePlayers as $row) {
    if ($row['DoUpset'] > $MAXUpset) {
        $MAXUpset = $row['DoUpset'];
        $MAXUpsetPlayerID = $row['PlayerID'];
        $MAXUpsetDate = $row['DoUpset']. '회 / ' . $row['TryUpset'] . '회' ;
    }
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title> BCK Official DEV </title> <!-- 브라우져 탭 제목 -->
    <meta property="og:title" content="BCK Official DEV"> <!-- 링크 공유시 페이지 이름 -->
    <meta property="og:description" content=" '블교 스타 리그' "> <!-- 링크 공유시 페이지 설명 -->
    <meta property="og:image" content="http://124.50.137.165/images/thumbnail.png"> <!-- 링크 공유시 썸네일 이미지 -->

    <script src="https://code.highcharts.com/highcharts.js"></script> <!-- Graph 그리는 JS -->
    
</head>
<body>

    <!-- Graph -->
    <h1>Game Records</h1>
    <div id="container" style="width:100%; height:800px;"></div> 
    <br><br>



    <!-- 순위표 -->
    <h1>Player Statistics</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Rank</th>
                <th>ID</th>
                <th>Rating</th>
                <th>Win Rate</th>
                <th>Wins</th>
                <th>Total</th>
                <th>AVG APM</th>
            </tr>
        </thead>
        <tbody>
            <?php
                foreach ($PlayerStats as $row) {
                    $winRate = $row['GamesPlayed'] > 0 ? round(($row['Wins'] / $row['GamesPlayed']) * 100, 2) . '%' : 'N/A';
                    echo "<tr>";
                    echo "<td>" . $row['Ranking'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['Name']) . "</td>";
                    echo "<td>" . $row['Rating'] . "</td>";
                    echo "<td>" . $winRate . "</td>";
                    echo "<td>" . $row['Wins'] . "</td>";
                    echo "<td>" . $row['GamesPlayed'] . "</td>";
                    echo "<td>" . round($row['AvgAPM'], 2) . "</td>";
                    echo "</tr>";
                }
            ?>
        </tbody>
    </table>

    

    <!-- 명예의 전당 -->
    <h1>The Hall of Fame</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Awards</th>
                <th>ID</th>
                <th>Value</th>
                <th>TIME</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold;"> Max Win Streak </td>
                <td><?php echo $playersID[$MAXWINStreakIndex]['Name']; ?></td>
                <td><?php echo $MAXWINStreakMAX; ?></td>
                <td><?php echo $MAXWINStreakDateStart . ' ~ ' . $MAXWINStreakDateEnd . " (" . dateDiff($MAXWINStreakDateStart, $MAXWINStreakDateEnd) . ")일"; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;"> Max Lose Streak </td>
                <td><?php echo $playersID[$MAXLOSEStreakIndex]['Name']; ?></td>
                <td><?php echo $MAXLOSEStreakMAX; ?></td>
                <td><?php echo $MAXLOSEStreakDateStart . ' ~ ' . $MAXLOSEStreakDateEnd . " (" . dateDiff($MAXLOSEStreakDateStart, $MAXLOSEStreakDateEnd) . ")일"; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;"> Max Rating Increase (n Games)</td>
                <td><?php echo $playersID[$MAXDiffMinIndex]['Name']; ?></td>
                <td><?php echo $MAXDiffMinMAX; ?></td>
                <td><?php echo $MAXDiffMinDate . ' ~ ' . $MAXDiffMinDateNow . " (" . dateDiff($MAXDiffMinDate, $MAXDiffMinDateNow) . ")일 : " . $MAXDiffMinNumGames . "게임"; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;"> Max Rating Decrease (n Games)</td>
                <td><?php echo $playersID[$MAXDiffMaxIndex]['Name']; ?></td>
                <td><?php echo $MAXDiffMaxMAX; ?></td>
                <td><?php echo $MAXDiffMaxDate . ' ~ ' . $MAXDiffMaxDateNow . " (" . dateDiff($MAXDiffMaxDate, $MAXDiffMaxDateNow) . ")일 : " . $MAXDiffMaxNumGames . "게임"; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;"> Highest Rating </td>
                <td><?php echo $playersID[$highestRatingPlayerID]['Name']; ?></td>
                <td><?php echo $highestRating; ?></td>
                <td><?php echo $highestRatingDate; ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Lowest Rating</td>
                <td><?php echo $playersID[$lowestRatingPlayerID]['Name']; ?></td>
                <td><?php echo $lowestRating; ?></td>
                <td><?php echo $lowestRatingDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">MAX Rating Inc (1 Game)</td>
                <td><?php echo $playersID[$highestIncPlayerID]['Name']; ?></td>
                <td><?php echo $highestInc; ?></td>
                <td><?php echo $highestIncDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">MIN Rating Inc (1 Game)</td>
                <td><?php echo $playersID[$lowestIncPlayerID]['Name']; ?></td>
                <td><?php echo $lowestInc; ?></td>
                <td><?php echo $lowestIncDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">Longest Win</td>
                <td><?php echo $playersID[$longestPlayerID]['Name']; ?></td>
                <td><?php echo $longestGame; ?> minutes</td>
                <td><?php echo $longestGameDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">Shortest Win</td>
                <td><?php echo $playersID[$shortestPlayerID]['Name']; ?></td>
                <td><?php echo $shortestGame; ?> minutes</td>
                <td><?php echo $shortestGameDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">Highest APM</td>
                <td><?php echo $playersID[$highestAPMPlayerID]['Name']; ?></td>
                <td><?php echo $highestAPM; ?></td>
                <td><?php echo $highestAPMDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">Lowest Winning APM</td>
                <td><?php echo $playersID[$lowestWinningAPMPlayerID]['Name']; ?></td>
                <td><?php echo $lowestWinningAPM; ?></td>
                <td><?php echo $lowestWinningAPMDate; ?></td>
            </tr>

            <tr>
                <td style="font-weight: bold;">MAX Upset</td>
                <td><?php echo $playersID[$MAXUpsetPlayerID]['Name']; ?></td>
                <td><?php echo $MAXUpset; ?></td>
                <td><?php echo $MAXUpsetDate; ?></td>
            </tr>
        </tbody>
    </table>
    


    <!-- 라크쉬르표 -->
    <h2>Rak'shir Statistics</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Rating</th>
                <th>ID</th>
                
                <th>Success rate</th>
                <th>Upset</th>
                <th>Try Rak'shir</th>

                <th>Defend rate</th>
                <th>Fail</th>
                <th>Accept Rak'shir</th>
            </tr>
        </thead>
        <tbody>
            <?php
                foreach ($PlayerStats as $row) {
                    echo "<tr>";
                    echo "<td>" . $row['Rating'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['Name']) . "</td>";

                    $SuccessRate = $row['TryUpset'] > 0 ? round(($row['DoUpset'] / $row['TryUpset']) * 100, 2) . '%' : 'N/A';
                    echo "<td>" . $SuccessRate . "</td>";
                    echo "<td>" . $row['DoUpset'] . "</td>";
                    echo "<td>" . $row['TryUpset'] . "</td>";
                    
                    $DefendRate = $row['ReceiveUpset'] > 0 ? round(100 - ($row['GetUpset'] / $row['ReceiveUpset']) * 100, 2) . '%' : 'N/A';
                    echo "<td>" . $DefendRate . "</td>";
                    echo "<td>" . $row['GetUpset'] . "</td>";
                    echo "<td>" . $row['ReceiveUpset'] . "</td>";
                    echo "</tr>";
                }
            ?>
        </tbody>
    </table>        



    <!-- 연승 연패 기록표 -->
    <h2>Player Streak Statistics</h2>
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>MAX Win</th>
                <th>MAX Lose</th>
                <th>Win NOW</th>
                <th>Lose NOW</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($PlayerStats as $row) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Name']); ?></td>
                    <td><?php echo $streaks[$row['PlayerID']]['max_win_streak']; ?></td>
                    <td><?php echo $streaks[$row['PlayerID']]['max_lose_streak']; ?></td>
                    <td><?php echo $streaks[$row['PlayerID']]['current_win_streak']; ?></td>
                    <td><?php echo $streaks[$row['PlayerID']]['current_lose_streak']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



    <!-- 선수간 상성표 -->
    <h2>Player Matchups</h2>
    <table border="1">
        <thead>
            <tr>
                <th>W/T</th>
                <?php foreach ($GamePlayers as $player) : ?>
                    <th style='font-weight: normal;'><?php echo $player['Name']; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($GamePlayers as $player1) : ?>
                <tr>
                    <td style="font-weight: bold;"><?php echo $player1['Name']; ?></td>
                    <?php foreach ($GamePlayers as $player2) : ?>
                        <?php $row = $PlayerMatchups[$player1['PlayerID']][$player2['PlayerID']]; ?>
                        <?php $notsame = ($player1['PlayerID'] !== $player2['PlayerID']); ?>
                        <td style="color: <?php echo $notsame ? getColor($row['WinRate']) : '#000'; ?>">
                            <?php echo ( $notsame and ($row['Total']>0) ) ? "{$row['Wins']}/{$row['Total']}" : ''; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



    <!-- 종족간 상성표 -->
    <h2>Race Matchups</h2>
    <table border="1">
        <thead>
            <tr>
                <th>W/T</th>
                <?php foreach ($races as $Race): ?>
                    <th style='font-weight: normal;'><?php echo $raceName[$Race]; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($races as $Race1): ?>
                <tr>
                    <?php $Race1color = isset($raceColorMap[$Race1]) ? $raceColorMap[$Race1] : '#000000'; ?>
                    <td style='font-weight: bold; color: <?php echo $Race1color; ?>;'><?php echo $raceName[$Race1]; ?></td>
                    <?php foreach ($races as $Race2): ?>
                        <td>
                            <?php $row = $RaceMatchups[$Race1][$Race2]; ?>
                            <?php $notsame = ($Race1 !== $Race2); ?>
                            <?php echo ( $notsame ) ? "{$row['Wins']}/{$row['Total']}" : "-/{$row['Total']}"; ?>
                            
                            <?php if ($notsame): ?>
                                <br>
                                <span style="color: <?php echo getColor($row['WinRate'], 2); ?>">
                                    <?php echo round($row['WinRate'], 3); ?>
                                </span>
                            <?php endif; ?>

                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    

    <!-- 게임 기록 -->
    <h2>Game List</h2>
    <table border="1">
    <thead>
    <tr>
        <th>GameID</th>
        <th>Date</th>
        <th>Time</th>
        <th>Player 1 (Win)</th>
        <th>Player 2 (Lose)</th>
        <th>Upset</th>
        <th>Map</th>
        <th>Type</th>
        <th>Player 1 APM</th>
        <th>Player 2 APM</th>
        <th>Player 1 Starting Build</th>
        <th>Player 2 Starting Build</th>
        <th>B-GO</th>
        <th>Rating increase</th>
        <th>Player 1 (Win) Rating After</th>
        <th>Player 2 (Lose) Rating After</th>
        <th>Rating difference</th>
    </tr>
        </thead>
        <tbody>
            <?php
            $ListQuery = "SELECT G.*, 
                                Player1.Name as Player1Name, 
                                Player2.Name as Player2Name, 
                                Maps.Name as MapName, 
                                Types.TypeName
                            FROM Games G
                                JOIN Players AS Player1 ON G.Player1ID = Player1.PlayerID 
                                JOIN Players AS Player2 ON G.Player2ID = Player2.PlayerID 
                                JOIN Maps ON G.MapID = Maps.MapID 
                                JOIN Types ON G.TypeID = Types.TypeID 
                            ORDER BY Date ASC, GameID ASC";
            $ListResult = $conn->query($ListQuery);
            $List = [];
            while ($row = $ListResult->fetch_assoc()) {
            $List[] = $row;
            }

            if (count($List) > 0) {
                foreach ($List as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['GameID']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Date']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Length']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1Name']) . " " . htmlspecialchars($row['Player1Race']) . htmlspecialchars($row['Player1Dir']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2Name']) . " " . htmlspecialchars($row['Player2Race']) . htmlspecialchars($row['Player2Dir']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Upset_yn']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['MapName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['TypeName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1APM']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2APM']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1StartingBuild']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2StartingBuild']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1B_GO']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['RatingInc']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1RatingAfter']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2RatingAfter']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1RatingBefore'] - $row['Player2RatingBefore']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='17'>No games found</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <!-- 레이팅 업데이트 버튼 -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="update_ratings" value="1">
        <input type="submit" value="레이팅 업데이트">
    </form>



    <!-- =============
        JavaSciprts 
    =============== -->

    <!-- Delete 버튼 -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function() {

                var confirmed = confirm('정말로 이 게임을 삭제하시겠습니까?');
                if (confirmed) {
                    var gameId = this.getAttribute('data-gameid');
                    var xhr = new XMLHttpRequest();
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                // 페이지 리로드
                                window.location.reload();
                            } else {
                                // 오류 처리
                                console.error('Delete request failed');
                            }
                        }
                    };
                    xhr.open('POST', 'delete_game.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('game_id=' + gameId);
                }
            });
        });
    });
    </script>



    <!-- Submit 버튼 -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var submitButton = document.querySelector('input[type="submit"]');

        submitButton.addEventListener('click', function(event) {

            var confirmed = confirm('게임을 제출하시겠습니까?');
            if (!confirmed) {
                event.preventDefault();
            }
            
            var player1ID = document.getElementById('player1ID').value;
            var player2ID = document.getElementById('player2ID').value;
            
            if (player1ID === player2ID) {
                // 두 ID가 같은 경우 제출 중지
                alert('Player 1과 Player 2의 ID는 같을 수 없습니다.');
                event.preventDefault();
            }
        });
    });
    </script>



    <!-- Graph JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const seriesData = [];
        <?php foreach ($GraphDatas as $playerName => $dataPoints) : ?>
            seriesData.push({
                name: '<?= $playerName ?>',
                data: <?= json_encode($dataPoints) ?>,
                marker: { enabled: false }
            });
        <?php endforeach; ?>

        Highcharts.chart('container', {
            chart: { type: 'line' },
            title: { text: 'Player Ratings Over Time' },
            xAxis: { type: 'linear', title: { text: 'Game' } },
            yAxis: { title: { text: 'Rating' } },
            series: seriesData
        });
    });
    </script>

</body>
</html>

<?php
$conn->close();
?>