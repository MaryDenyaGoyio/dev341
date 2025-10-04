<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug</title>
</head>
<body>
    <h1>DEBUG</h1>
    <div id="roomData"></div>

    <h1>LOG</h1>
    <div id="logContainer" style="height: 400px; overflow-y: auto;"></div>
    <script src="/socket.io/socket.io.js"></script>

    <script>
        const roomId = "<?php echo $_GET['id']; ?>";
        const apiUrl = `http://124.50.137.165:3000/debug/${roomId}`;

        async function fetchRoomData() {
            try {
                const response = await fetch(apiUrl);
                const data = await response.json();

                if (data.error) {
                    document.getElementById('roomData').innerText = `Error: ${data.error}`;
                    clearInterval(interval);
                } else {
                    document.getElementById('roomData').innerText = JSON.stringify(data, null, 2);
                }
            } catch (error) {
                document.getElementById('roomData').innerText = `Failed to fetch data: ${error}`;
                clearInterval(interval);
            }
        }

        interval = setInterval(fetchRoomData, 100);
        fetchRoomData();


        const logContainer = document.getElementById('logContainer');

        const socket = io('http://124.50.137.165', {
            path: '/socket.io',
            transports: ['websocket']
        });

        socket.emit('debug');

        socket.on('log', (message) => {
            console.log(message);

            const logRoomId = message.slice(0, 6);
            
            if (roomId===logRoomId) {
                const logEntry = document.createElement('div');
                logEntry.textContent = message;
                logContainer.appendChild(logEntry);
                
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });

    </script>
</body>
</html>
