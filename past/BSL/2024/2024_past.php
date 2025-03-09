<?php
// 데이터베이스 연결 설정
$host = 'localhost';  // 데이터베이스 서버 위치
$username = 'root';  // 데이터베이스 사용자 이름
$password = 'e^ipi=-1';  // 데이터베이스 비밀번호
$dbname = 'GameRecords';  // 데이터베이스 이름

// MySQL 연결
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 플레이어, 맵, 타입 데이터 불러오기
$playersQuery = "SELECT PlayerID, Name FROM Players";
$playersResult = $conn->query($playersQuery);
$players = [];
while ($player = $playersResult->fetch_assoc()) {
    $players[] = $player;
}

$mapsQuery = "SELECT MapID, Name FROM Maps";
$mapsResult = $conn->query($mapsQuery);

$typesQuery = "SELECT TypeID, TypeName FROM Types";
$typesResult = $conn->query($typesQuery);

$K = 50; // ELO rating system constant

// 데이터베이스에서 플레이어의 마지막 레이팅을 가져오는 함수
function getLastRating($playerID, $conn) {
    $query = "SELECT Player1RatingAfter FROM Games2024 WHERE Player1ID = ? ORDER BY GameID DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['Player1RatingAfter'];
    } else {
        return 1000; // 기본 레이팅 값
    }
}

function updateRatings($conn) {
    global $K;
    // 모든 게임을 날짜 순으로 불러옵니다.
    $query = "SELECT * FROM Games2024 ORDER BY Date ASC, GameID ASC";
    $games = $conn->query($query);
    $ratings = [];  // 플레이어의 최신 레이팅을 저장하는 배열

    // 각 게임을 처리합니다.
    while ($game = $games->fetch_assoc()) {
        $player1ID = $game['Player1ID'];
        $player2ID = $game['Player2ID'];

        // 플레이어의 초기 레이팅을 설정합니다.
        if (!isset($ratings[$player1ID])) $ratings[$player1ID] = 1000;
        if (!isset($ratings[$player2ID])) $ratings[$player2ID] = 1000;

        $R1 = $ratings[$player1ID];
        $R2 = $ratings[$player2ID];

        $Q1 = pow(10, $R1 / 400);
        $Q2 = pow(10, $R2 / 400);

        $E1 = $Q1 / ($Q1 + $Q2);
        $E2 = $Q2 / ($Q1 + $Q2);

        $player1RatingAfter = $R1 + $K * (1 - $E1);
        $player2RatingAfter = $R2 - $K * $E2;

        // 업데이트된 레이팅을 배열에 저장합니다.
        $ratings[$player1ID] = $player1RatingAfter;
        $ratings[$player2ID] = $player2RatingAfter;

        // 레이팅 업데이트를 데이터베이스에 반영합니다.
        $updateQuery = "UPDATE Games2024 SET Player1RatingBefore=?, Player1RatingAfter=?, Player2RatingBefore=?, Player2RatingAfter=? WHERE GameID=?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iiiii", $R1, $player1RatingAfter, $R2, $player2RatingAfter, $game['GameID']);
        $stmt->execute();
        $stmt->close();
    }
}


// 폼에서 데이터가 전송된 경우 데이터베이스에 저장
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // POST 데이터 추출
    global $K;

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

    $R1 = getLastRating($player1ID, $conn);
    $R2 = getLastRating($player2ID, $conn);
    $Q1 = pow(10, $R1 / 400);
    $Q2 = pow(10, $R2 / 400);
    $E1 = $Q1 / ($Q1 + $Q2);
    $E2 = $Q2 / ($Q1 + $Q2);
    $player1RatingAfter = $R1 + $K * (1 - $E1); // Player 1 wins
    $player2RatingAfter = $R2 - $K * $E2; // Player 2 loses

    // 데이터베이스에 데이터 삽입 (업데이트된 코드)
    $stmt = $conn->prepare("INSERT INTO Games2024 (Date, Length, Player1ID, Player2ID, Player1Race, Player2Race, Player1Dir, Player2Dir, MapID, TypeID, Player1APM, Player2APM, Player1StartingBuild, Player2StartingBuild, Player1B_GO, Player1RatingBefore, Player1RatingAfter, Player2RatingBefore, Player2RatingAfter) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiissiiiiiisssiidd", $date, $length, $player1ID, $player2ID, $player1Race, $player2Race, $player1Dir, $player2Dir, $mapID, $typeID, $player1APM, $player2APM, $player1StartingBuild, $player2StartingBuild, $player1B_GO, $R1, $player1RatingAfter, $R2, $player2RatingAfter);
    $stmt->execute();
    $stmt->close();

    // echo $_SERVER["PHP_SELF"];

    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// 레이팅 업데이트 버튼이 클릭되었을 때
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ratings'])) {
    updateRatings($conn);
    // 페이지 리로드 또는 다른 작업 수행
    // 여기서는 예시로 페이지 리로드
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}


$games_query = "SELECT Games2024.*, Player1.Name as Player1Name, Player2.Name as Player2Name, Maps.Name as MapName, Types.TypeName, Games2024.Player1RatingBefore, Games2024.Player1RatingAfter, Games2024.Player2RatingBefore, Games2024.Player2RatingAfter FROM Games2024 JOIN Players AS Player1 ON Games2024.Player1ID = Player1.PlayerID JOIN Players AS Player2 ON Games2024.Player2ID = Player2.PlayerID JOIN Maps ON Games2024.MapID = Maps.MapID JOIN Types ON Games2024.TypeID = Types.TypeID ORDER BY Date ASC, GameID ASC ";
$result = $conn->query($games_query);
$result2 = $conn->query($games_query);

$query = "SELECT Players.PlayerID, Name, Wins, GamesPlayed, AvgAPM, Rating, MaxRating, RANK() OVER (ORDER BY Rating DESC) AS Ranking FROM PlayerStats2024 JOIN Players ON PlayerStats2024.PlayerID = Players.PlayerID";
$result3 = $conn->query($query);


$playersQuery = "SELECT DISTINCT Players.PlayerID, Players.Name, PlayerStats2024.Rating FROM Players
                    JOIN PlayerStats2024 ON Players.PlayerID = PlayerStats2024.PlayerID
                    JOIN (SELECT Player1ID FROM Games2024 UNION SELECT Player2ID FROM Games2024) GamePlayers 
                    ON Players.PlayerID = GamePlayers.Player1ID ORDER BY PlayerStats2024.Rating DESC";
$playersResult = $conn->query($playersQuery);
$players_ = [];
while ($player = $playersResult->fetch_assoc()) {
    $players_[$player['PlayerID']] = $player['Name'];
}

// 승률 계산을 위한 데이터 로드
$wins = [];
$winsStr = [];
foreach (array_keys($players_) as $player1ID) {
    foreach (array_keys($players_) as $player2ID) {
        if ($player1ID != $player2ID) {
            $query = "SELECT 
                        (SELECT Wins FROM PlayerMatchups2024 WHERE Player1ID = $player1ID AND Player2ID = $player2ID) AS Wins1,
                        (SELECT Wins FROM PlayerMatchups2024 WHERE Player1ID = $player2ID AND Player2ID = $player1ID) AS Wins2";
            $result = $conn->query($query);
            if ($row = $result->fetch_assoc()) {
                $totalWins = $row['Wins1'] + $row['Wins2'];
                $wins[$players_[$player1ID]][$players_[$player2ID]] = ($totalWins > 0) ? $row['Wins1']/$totalWins : NULL;
                $winsStr[$players_[$player1ID]][$players_[$player2ID]] = ($totalWins > 0) ? "{$row['Wins1']}/$totalWins" : NULL;
            } else {
                $wins[$players_[$player1ID]][$players_[$player2ID]] = NULL;
                $winsStr[$players_[$player1ID]][$players_[$player2ID]] = '';
            }
        }
    }
}

function getColor($percentage, $range = 1) {
    $red = 255 * ( (1/2 - $range * ($percentage-1/2) > 1)? 1 : ( (1/2 - $range * ($percentage-1/2) < 0)? 0 : 1/2 - $range * ($percentage-1/2)) ) ;
    $blue = 255 * ( (1/2 + $range * ($percentage-1/2) > 1)? 1 : ( (1/2 - $range * ($percentage-1/2) < 0)? 0 : 1/2 + $range * ($percentage-1/2)) );
    return sprintf("#%02x%02x%02x", $red, 0, $blue); // 빨강 - 파랑 스펙트럼
}

$races = ['Z' => 'Zerg', 'T' => 'Terran', 'P' => 'Protoss'];

$Racequery = "SELECT Race1, Race2, Wins FROM RaceMatchups2024";
$Raceresult = $conn->query($Racequery);

$Racewins = [];
while ($row = $Raceresult->fetch_assoc()) {
    $Racewins[$row['Race1']][$row['Race2']] = $row['Wins'];
}

// 승률 계산 및 저장
$RacewinRate = [];
$RacewinsStr = [];
foreach ($races as $race1ID => $race1Name) {
    foreach ($races as $race2ID => $race2Name) {
        
            $wins1 = $Racewins[$race1ID][$race2ID] ?? 0;
            $wins2 = $Racewins[$race2ID][$race1ID] ?? 0;
            $totalWins = $wins1 + $wins2;
        
        if ($race1ID != $race2ID) {
            $RacewinRate[$race1Name][$race2Name] = ($totalWins > 0) ? $wins1/$totalWins : NULL;
            $RacewinsStr[$race1Name][$race2Name] = ($totalWins > 0) ? "$wins1/$totalWins" : '0/0';
        }
        else {
            $RacewinRate[$race1Name][$race2Name] = NULL;
            $RacewinsStr[$race1Name][$race2Name] = "-/$totalWins";
        }
    }
}

$raceColorMap = array(
    'Z' => '#4c00a4',
    'T' => '#0C48CC',
    'P' => '#8B8000',
    'Zerg' => '#4c00a4',
    'Terran' => '#0C48CC',
    'Protoss' => '#8B8000',
);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta property="og:title" content="BCK Official 24">
    <meta property="og:description" content=" '블교 스타 리그 24시즌' ">
    <meta property="og:image" content="http://124.50.137.165/images/thumbnail.png">

    <title>Game Records</title>
    <script src="https://code.highcharts.com/highcharts.js"></script>
</head>
<body>
    <h1>Game Records</h1>

    <?php
    // 이전 설정 및 쿼리는 유지하고, 데이터가 제대로 로드되는지 확인합니다.
    $games_query = "SELECT Games2024.*, Player1.Name as Player1Name, Player2.Name as Player2Name, Maps.Name as MapName, Types.TypeName, Games2024.Player1RatingBefore, Games2024.Player1RatingAfter, Games2024.Player2RatingBefore, Games2024.Player2RatingAfter FROM Games2024 JOIN Players AS Player1 ON Games2024.Player1ID = Player1.PlayerID JOIN Players AS Player2 ON Games2024.Player2ID = Player2.PlayerID JOIN Maps ON Games2024.MapID = Maps.MapID JOIN Types ON Games2024.TypeID = Types.TypeID ORDER BY Date ASC, GameID ASC";
    $result = $conn->query($games_query);
    $gamesData = [];
    $initializedPlayers = [];

    while ($game = $result->fetch_assoc()) {
        // 플레이어1에 대해 초기 레이팅 점을 설정
        if (!isset($initializedPlayers[$game['Player1Name']])) {
            $gamesData[$game['Player1Name']] = [['x' => 0, 'y' => 1000]];
            $initializedPlayers[$game['Player1Name']] = true;
        }
        // 플레이어2에 대해 초기 레이팅 점을 설정
        if (!isset($initializedPlayers[$game['Player2Name']])) {
            $gamesData[$game['Player2Name']] = [['x' => 0, 'y' => 1000]];
            $initializedPlayers[$game['Player2Name']] = true;
        }

        // 기존 게임 데이터 추가
        $gamesData[$game['Player1Name']][] = ['x' => (int)$game['GameID'], 'y' => (int)$game['Player1RatingAfter']];
        $gamesData[$game['Player2Name']][] = ['x' => (int)$game['GameID'], 'y' => (int)$game['Player2RatingAfter']];
    }

    // 데이터가 어떻게 로드되는지 디버깅을 위한 로그 출력
    echo "<script>console.log('Debug Objects: " . json_encode($gamesData) . "');</script>";
    ?>


    <div id="container" style="width:100%; height:800px;"></div>
    <br><br>


    <h2>Enter Game Data</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="date">Date:</label>
        <input type="date" id="date" name="date" required><br>

        <label for="hours">Time:</label>
        <input type="number" id="hours" name="hours" min="0" placeholder="HH" style="width:50px;">:
        <input type="number" id="minutes" name="minutes" min="0" max="59" placeholder="MM" style="width:50px;">:
        <input type="number" id="seconds" name="seconds" min="0" max="59" placeholder="SS" style="width:50px;"><br>

        <label for="player1ID">Player 1:</label>
        <select id="player1ID" name="player1ID" required>
            <?php foreach($players as $player) {
                echo "<option value='" . $player['PlayerID'] . "'>" . $player['Name'] . "</option>";
            } ?>
        </select><br>

        <label for="player2ID">Player 2:</label>
        <select id="player2ID" name="player2ID" required>
            <?php foreach($players as $player) {
                echo "<option value='" . $player['PlayerID'] . "'>" . $player['Name'] . "</option>";
            } ?>
        </select><br>

        <label for="player1Race">Player 1 Race:</label>
        <select id="player1Race" name="player1Race">
            <option value="Z">Zerg</option>
            <option value="T">Terran</option>
            <option value="P">Protoss</option>
        </select><br>

        <label for="player1Dir">Player 1 Direction:</label>
        <select id="player1Dir" name="player1Dir">
            <?php for ($i = 1; $i <= 12; $i++) {
                echo "<option value='$i'>$i</option>";
            } ?>
        </select><br>

        <label for="player2Race">Player 2 Race:</label>
        <select id="player2Race" name="player2Race">
            <option value="Z">Zerg</option>
            <option value="T">Terran</option>
            <option value="P">Protoss</option>
        </select><br>

        <label for="player2Dir">Player 2 Direction:</label>
        <select id="player2Dir" name="player2Dir">
            <?php for ($i = 1; $i <= 12; $i++) {
                echo "<option value='$i'>$i</option>";
            } ?>
        </select><br>

        <label for="mapID">Map:</label>
        <select id="mapID" name="mapID">
            <?php while($map = $mapsResult->fetch_assoc()) {
                echo "<option value='" . $map['MapID'] . "'>" . $map['Name'] . "</option>";
            } ?>
        </select><br>

        <label for="typeID">Game Type:</label>
        <select id="typeID" name="typeID">
            <?php while($type = $typesResult->fetch_assoc()) {
                echo "<option value='" . $type['TypeID'] . "'>" . $type['TypeName'] . "</option>";
            } ?>
        </select><br>

        <label for="player1APM">Player 1 APM:</label>
        <input type="number" id="player1APM" name="player1APM" min="-9999" max="9999"><br>

        <label for="player2APM">Player 2 APM:</label>
        <input type="number" id="player2APM" name="player2APM" min="-9999" max="9999"><br>

        <label for="player1StartingBuild">Player 1 Starting Build:</label>
        <input type="text" id="player1StartingBuild" name="player1StartingBuild" maxlength="255"><br>

        <label for="player2StartingBuild">Player 2 Starting Build:</label>
        <input type="text" id="player2StartingBuild" name="player2StartingBuild" maxlength="255"><br>

        <label for="player1B_GO">Player 1 Build Order:</label>
        <input type="text" id="player1B_GO" name="player1B_GO" maxlength="255"><br>

        <label for="player2B_GO">Player 2 Build Order:</label>
        <input type="text" id="player2B_GO" name="player2B_GO" maxlength="255"><br>
        <!-- 추가된 부분 끝 -->

        <input type="submit" name="submit" value="Submit Game Data">
    </form>



    <h1>Player Statistics</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Rating</th>
                <th>ID</th>
                <th>Ranking</th>
                <th>승률</th>
                <th>승수</th>
                <th>판수</th>
                <th>평균 APM</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = $result3->fetch_assoc()) {
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


    <h2>Player Matchups</h2>
    <table border="1">
        <thead>
            <tr>
                <th>W/T</th>
                <?php foreach ($players_ as $playerName) { echo "<th style='font-weight: normal;'>$playerName</th>"; } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players_ as $player1Name) : ?>
                <tr>
                    <td style="font-weight: bold;"><?php echo $player1Name; ?></td>
                    <?php foreach ($players_ as $player2Name) : ?>
                        <td style="color: <?php echo $player1Name !== $player2Name ? getColor($wins[$player1Name][$player2Name]) : '#000'; ?>">
                            <?php echo $player1Name !== $player2Name ? $winsStr[$player1Name][$player2Name] : ''; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



    <h2>Race Matchups</h2>
    <table border="1">
        <thead>
            <tr>
                <th>W/T</th>
                <?php foreach ($races as $raceName): ?>
                    <th style='font-weight: normal;'><?php echo $raceName; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($races as $race1Name): ?>
                <tr>
                    <?php $Race1color = isset($raceColorMap[$race1Name]) ? $raceColorMap[$race1Name] : '#000000'; ?>
                    <td style='font-weight: bold; color: <?php echo $Race1color; ?>;'><?php echo $race1Name; ?></td>
                    <?php foreach ($races as $race2Name): ?>
                        <td>
                            <?php echo $RacewinsStr[$race1Name][$race2Name]; ?>

                            <?php if ($race1Name !== $race2Name): ?>
                                <br>
                                <span style="color: <?php echo getColor($RacewinRate[$race1Name][$race2Name], 2); ?>">
                                    <?php echo round($RacewinRate[$race1Name][$race2Name], 3); ?>
                                </span>
                            <?php endif; ?>

                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



    <h2>Game List</h2>
    <table border="1">
    <thead>
    <tr>
        <th>GameID</th>
        <th>Date</th>
        <th>Time</th>
        <th>Player 1 (Win)</th>
        <th>Player 2 (Lose)</th>
        <th>Map</th>
        <th>Type</th>
        <th>Player 1 APM</th>
        <th>Player 2 APM</th>
        <th>Player 1 Starting Build</th>
        <th>Player 2 Starting Build</th>
        <th>B-GO</th>
        <th>Player 1 (Win) Rating Before</th>
        <th>Player 1 (Win) Rating After</th>
        <th>Player 2 (Lose) Rating Before</th>
        <th>Player 2 (Lose) Rating After</th>
        <th>Actions</th>
    </tr>
        </thead>
        <tbody>
            <?php
            if ($result2->num_rows > 0) {
                while ($row = $result2->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['GameID']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Date']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Length']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1Name']) . " " . htmlspecialchars($row['Player1Race']) . htmlspecialchars($row['Player1Dir']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2Name']) . " " . htmlspecialchars($row['Player2Race']) . htmlspecialchars($row['Player2Dir']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['MapName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['TypeName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1APM']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2APM']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1StartingBuild']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2StartingBuild']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1B_GO']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1RatingBefore']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player1RatingAfter']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2RatingBefore']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Player2RatingAfter']) . "</td>";
                    // Insert delete button
                    echo "<td><button class='delete-btn' data-gameid='" . $row['GameID'] . "'>Delete</button></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='17'>No games found</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                // 사용자에게 확인을 요청하는 경고 창을 표시합니다.
                var confirmed = confirm('정말로 이 게임을 삭제하시겠습니까?');
                if (confirmed) {
                    var gameId = this.getAttribute('data-gameid');
                    var xhr = new XMLHttpRequest();
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                // 성공적으로 삭제되면 페이지를 리로드합니다.
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 제출 버튼을 가져옵니다.
        var submitButton = document.querySelector('input[type="submit"]');

        // 제출 버튼에 클릭 이벤트를 추가합니다.
        submitButton.addEventListener('click', function(event) {
            // 사용자에게 확인을 요청하는 경고 창을 표시합니다.
            var confirmed = confirm('게임을 제출하시겠습니까?');
            if (!confirmed) {
                // 취소를 선택한 경우, 제출을 중지합니다.
                event.preventDefault();
            }
            
            // Player 1과 Player 2의 ID를 가져옵니다.
            var player1ID = document.getElementById('player1ID').value;
            var player2ID = document.getElementById('player2ID').value;
            
            // 두 ID가 같은지 확인합니다.
            if (player1ID === player2ID) {
                // 두 ID가 같은 경우, 경고를 표시하고 제출을 중지합니다.
                alert('Player 1과 Player 2의 ID는 같을 수 없습니다.');
                event.preventDefault();
            }
        });
    });
    </script>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="update_ratings" value="1">
        <input type="submit" value="레이팅 업데이트">
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const seriesData = [];
        <?php foreach ($gamesData as $playerName => $dataPoints) : ?>
            seriesData.push({
                name: '<?= $playerName ?>',
                data: <?= json_encode($dataPoints) ?>
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