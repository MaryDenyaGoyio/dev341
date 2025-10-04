const express = require('express');
const http = require('http');
const socketIo = require("socket.io");

const app = express();
const server = http.createServer(app);
const io = socketIo(server);

const mysql = require('mysql');

const db = mysql.createConnection({
    host: 'db',
    user: 'root',
    password: 'dragonball',
    database: 'DragonBall'
});

db.connect((err) => {
    if (err) {
        console.error('MySQL connection error:', err);
        return;
    }
    console.log('===================================');
    console.log('MySQL connected.');
});

let rooms = {};
let gameRecords = {};
let responses = [false, false];

function roomUpdate(roomId, io) {
    return new Promise((resolve, reject) => {
        db.query('SELECT owner_uuid, challenger_uuid, status, result FROM rooms WHERE id = ?', [roomId], (err, queryResult) => {
            if (err) {
                reject('Error fetching room data: ' + err);
            } else if (queryResult && queryResult.length > 0) {

                const ownerUuid = queryResult[0].owner_uuid;
                const challengerUuid = queryResult[0].challenger_uuid || null;
                const status = queryResult[0].status;
                const result = queryResult[0].result;

                db.query('SELECT uuid, name, win, lose, rating FROM users WHERE uuid IN (?, ?)', [ownerUuid, challengerUuid], (err, usersResult) => {
                    if (err) {
                        reject(err);
                    } else {
                        const ownerData = usersResult.find(user => user.uuid === ownerUuid);
                        const challengerData = challengerUuid ? usersResult.find(user => user.uuid === challengerUuid) : null;


                        if (roomId in rooms) {
                            rooms[roomId].ownerUuid = ownerUuid;
                            rooms[roomId].challengerUuid = challengerUuid;
                            rooms[roomId].status = status;
                            rooms[roomId].result = result;
                        } else {
                            rooms[roomId] = { 
                                ownerUuid,
                                challengerUuid,
                                status: status,
                                result: result,
                                ready : [false, false],
                                etc : [0, 0]
                            };
                            console.log(`R${roomId}: new ${roomId} in Rooms`);
                        }

                        const roomClients = io.sockets.adapter.rooms.get(roomId);
                        if (roomClients) {
                            roomClients.forEach(clientId => {
                                const clientSocket = io.sockets.sockets.get(clientId);
                                const userUuid = clientSocket.userUuid;
                        
                                const isOwner = userUuid === ownerUuid;
                                const isChallenger = userUuid === challengerUuid;
                        
                                const clientData = {
                                    owner: {
                                        name: ownerData.name,
                                        win: ownerData.win,
                                        lose: ownerData.lose,
                                        rating: ownerData.rating
                                    },
                                    challenger: challengerData ? {
                                        name: challengerData.name,
                                        win: challengerData.win,
                                        lose: challengerData.lose,
                                        rating: challengerData.rating
                                    } : null,
                                    status: rooms[roomId].status,
                                    result: rooms[roomId].result,
                                    ready: rooms[roomId].ready,
                                    etc: rooms[roomId].etc,
                                    isOwner,
                                    isChallenger
                                };

                                const clientGameData = (roomId in gameRecords) ? {
                                    now: [isOwner ? gameRecords[roomId].now[0] : null, isChallenger ? gameRecords[roomId].now[1] : null],
                                    past: gameRecords[roomId].past,
                                    cnt: gameRecords[roomId].cnt,
                                    isNow: isOwner ? gameRecords[roomId].now[0] : isChallenger ? gameRecords[roomId].now[1] : null
                                } : null;

                                console.log(`[UPDATE] R${roomId}`);
                                clientSocket.emit('updateRoom', clientData, clientGameData);
                            });
                        
                            resolve();
                        } else {
                            reject('No clients found in room: ' + roomId);
                        }
                        
                    }
                });

            } else {
                io.to(roomId).emit('back');
                reject('No room found with ID: ' + roomId);
            }
        });
    });
}

function loadRooms() {
    return new Promise((resolve, reject) => {
        db.query('SELECT id FROM rooms WHERE status IN (0, 1)', (err, results) => {
            if (err) {
                console.error(err);
                reject(err);
            } else {
                const roomUpdatePromises = results.map(room => roomUpdate(room.id, io));
                Promise.all(roomUpdatePromises)
                    .then(() => {
                        console.log('All rooms loaded.');
                        resolve();
                    })
                    .catch(updateErr => {
                        console.error(updateErr);
                        reject(updateErr);
                    });
            }
        });
    });
}


loadRooms().then(() => {
    console.log('LOAD');
}).catch(err => {
    console.error(err);
});



let countdownIntervals = {}

function startCountdown(roomId, io) {
    console.log(`R${roomId}: startCountdown`);
    let timeRemaining = 3;
    
    countdownIntervals[roomId] = setInterval(() => {

        if (timeRemaining > 0) {
            io.to(roomId).emit('countdown', timeRemaining);
            timeRemaining--;
        } else {
            clearInterval(countdownIntervals[roomId]);

            const gameRecord = gameRecords[roomId];

            for (let i = 0; i < 2; i++) {
                let move = gameRecord.now[i] !== null ? gameRecord.now[i] : 'X';
            
                switch (move) {
                    case '기':
                        gameRecord.cnt[i] += 1;
                        break;
                    case '파':
                        gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 1);
                        break;
                    case '에네':
                        gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 3);
                        break;
                    case '순간':
                        gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 1);
                        break;
                }
            
                gameRecord.past[i].push(move);
            }
            
            // 만약에 여기서 둘 다 null이 아니면?
            endGame(roomId, (gameRecords[roomId].now[1] === null ? 1 : 0) - (gameRecords[roomId].now[0] === null ? 1 : 0), io);
            return;
        }
    }, 1000); 
}


function stopCountdown(roomId) {
    console.log('R${roomId}: stopCountdown');
    if (countdownIntervals[roomId]) {
        clearInterval(countdownIntervals[roomId]);
        delete countdownIntervals[roomId]; 
    }
}


async function endGame(roomId, winnerType, io) {
    const roomData = rooms[roomId];
    console.log(`R${roomId}: END GAME -1`);

    if (roomData.status !== -1) {
        console.log(`R${roomId}: END GAME`);

        // Owner와 Challenger의 현재 Rating 가져오기
        const ownerRating = await new Promise((resolve, reject) => {
            db.query('SELECT rating FROM users WHERE uuid = ?', [roomData.ownerUuid], (err, result) => {
                if (err) {
                    console.error(err);
                    reject(err);
                } else {
                    resolve(result[0].rating);
                }
            });
        });

        const challengerRating = await new Promise((resolve, reject) => {
            db.query('SELECT rating FROM users WHERE uuid = ?', [roomData.challengerUuid], (err, result) => {
                if (err) {
                    console.error(err);
                    reject(err);
                } else {
                    resolve(result[0].rating);
                }
            });
        });

        // E_O = 1 / (1 + 10^((R_C - R_O) / 400))
        const E_O = 1 / (1 + Math.pow(10, (challengerRating - ownerRating) / 400));
        const ownerChange = Math.round(30 * (1 - E_O)); // 승리 시 레이팅 변동
        const challengerChange = Math.round(30 * E_O);  // 패배 시 레이팅 변동

        // owner가 이긴 경우
        if (winnerType === 1) {
            await new Promise((resolve, reject) => {
                db.query('UPDATE rooms SET status = -1, result = 1, winner_uuid = owner_uuid, loser_uuid = challenger_uuid, time_ended = NOW() WHERE id = ?', [roomId], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        console.log(`R${roomId}: Owner won`);
                        resolve();
                    }
                });

                db.query('UPDATE users SET win = win + 1, rating = rating + ? WHERE uuid = ?', [ownerChange, roomData.ownerUuid], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        resolve();
                    }
                });

                db.query('UPDATE users SET lose = lose + 1, rating = rating - ? WHERE uuid = ?', [ownerChange, roomData.challengerUuid], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        resolve();
                    }
                });
            });


        } else if (winnerType === -1) {
            await new Promise((resolve, reject) => {
                db.query('UPDATE rooms SET status = -1, result = -1, winner_uuid = challenger_uuid, loser_uuid = owner_uuid, time_ended = NOW() WHERE id = ?', [roomId], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        console.log(`R${roomId}: Challenger won`);
                        resolve();
                    }
                });

                db.query('UPDATE users SET win = win + 1, rating = rating + ? WHERE uuid = ?', [challengerChange, roomData.challengerUuid], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        resolve();
                    }
                });

                db.query('UPDATE users SET lose = lose + 1, rating = rating - ? WHERE uuid = ?', [challengerChange, roomData.ownerUuid], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        resolve();
                    }
                });
            });
        }

        else if (winnerType === 0) {
            await new Promise((resolve, reject) => {
                db.query('UPDATE rooms SET status = -1, result = 0, time_ended = NOW() WHERE id = ?', [roomId], (err) => {
                    if (err) {
                        console.error(err);
                        reject(err);
                    } else {
                        console.log(`R${roomId}: draw`);
                        resolve();
                    }
                });
            });
        }
    }

    await roomUpdate(roomId, io); // 최종 업데이트
}



io.on('connection', (socket) => {
    console.log('A user connected:', socket.id);

    // A-1) JOIN
    socket.on('joinRoom', async (roomId, userUuid) => {
        socket.join(roomId);
        console.log(`---------------------------------`);
        console.log(`[JOIN] R${roomId}: ${userUuid} joined`);

        socket.userUuid = userUuid;
        socket.roomId = roomId;
    
        try {
            await roomUpdate(roomId, io); 
            let roomData = rooms[roomId];

            const index = (userUuid === roomData.ownerUuid) ? 0 : 
                          (userUuid === roomData.challengerUuid) ? 1 : -1;

            if (index !== -1 && roomData.etc[index] === -1) {
                roomData.etc[index] = 0;
                console.log(`R${roomId}: ${userUuid} reconnected.`);
                await roomUpdate(roomId, io);
                io.to(roomId).emit('eventRoom', 'Resume');
            }

            if (userUuid && !roomData.challengerUuid && roomData.ownerUuid !== userUuid) {
                await new Promise((resolve, reject) => {
                    db.query('UPDATE rooms SET challenger_uuid = ? WHERE id = ?', [userUuid, roomId], (err, result) => {
                        if (err) {
                            console.error(err);
                            reject(err);
                        } else {
                            console.log(`R${roomId}: ${userUuid} as Challenger`);
                            resolve();
                        }
                    });
                });
    
                await roomUpdate(roomId, io);
            }
        } catch (err) {
            console.error(err);
        }
    });

    // A-2) ACTION
    socket.on('gameAction', async (action, roomId, userUuid) => {
        console.log(`[ACTION] R${roomId}: ${userUuid} action ${action}`);

        // A-2-1) READY
        if (action === 'ready') {
            try {
                await roomUpdate(roomId, io);
                const roomData = rooms[roomId];

                if (roomData.status === 0) {

                    const index = (userUuid === roomData.ownerUuid) ? 0 : 
                                (userUuid === roomData.challengerUuid) ? 1 : -1;

                    if (index !== -1) {
                        roomData.ready[index] = !roomData.ready[index];
                        console.log(`[Ready] R${roomId}: ${userUuid} ready ${roomData.ready}`);
                    }

                    if (roomData.ready.every(i => i === true)) {
                        await new Promise((resolve, reject) => {
                            db.query('UPDATE rooms SET status = 1 WHERE id = ?', [roomId], (err) => {
                                if (err) {
                                    console.error(err);
                                    reject(err);
                                } else {
                                    io.to(roomId).emit('eventRoom', 'Start');
                                    resolve();
                                }
                            });
                        });

                        if (!(roomId in gameRecords)) {
                            console.log(`R${roomId}: new ${roomId} in gameRecords`);
                            gameRecords[roomId] = {
                                past: [[], []],
                                cnt: [0, 0],
                                now : [null, null]
                            };
                        }
                        startCountdown(roomId, io);
                    }

                    await roomUpdate(roomId, io);
                }
            } catch (err) {
                console.error(err);
            }
        } 

        // A-2-2) QUIT
        else if (action === 'quit') {
            try {
                const roomData = rooms[roomId];

                if (roomData.status === 0){

                    const index = (userUuid === roomData.ownerUuid) ? 0 : 
                                (userUuid === roomData.challengerUuid) ? 1 : -1;

                    if (index !== -1) {
                        roomData.ready[index] = false;
                    }

                    if (userUuid === roomData.ownerUuid) {
                        await new Promise((resolve, reject) => {
                            db.query('DELETE FROM rooms WHERE id = ?', [roomId], (err) => {
                                if (err) {
                                    console.error(err);
                                    reject(err);
                                } else {
                                    console.log(`R${roomId}: Owner ${userUuid} left`);
                                    io.to(roomId).emit('eventRoom', 'Abort');
                                    resolve();
                                }
                            });
                        });
                        await roomUpdate(roomId, io);
                    } else if (userUuid === roomData.challengerUuid) {
                        await new Promise((resolve, reject) => {
                            db.query('UPDATE rooms SET challenger_uuid = NULL WHERE id = ?', [roomId], (err) => {
                                if (err) {
                                    console.error(err);
                                    reject(err);
                                } else {
                                    console.log(`R${roomId}: Challenger ${userUuid} left`);
                                    resolve();
                                }
                            });
                        });

                        await roomUpdate(roomId, io);
                    }
                }

                else if (roomData.status === 1){
                    endGame(roomId, userUuid === roomData.ownerUuid ? 1 : userUuid === roomData.challengerUuid ? -1 : null, io)
                }
            } catch (err) {
                console.error(err);
                await roomUpdate(roomId, io);
            }
        }
    });


    // B-3) MOVE
    socket.on('getMove', (roomId, playerType, move) => {

        if (rooms[roomId].status === 1) {

            console.log(`[MOVE] R${roomId}: Type ${playerType} selected ${move}`);

            const gameRecord = gameRecords[roomId];
            console.log(`MOVE ${JSON.stringify(gameRecord)}`);
    
            gameRecord.now[playerType] = move;
    
            if (gameRecord.now.every(i => i !== null)) {
                stopCountdown(roomId);
            
                const result = calculateResult(gameRecord.now[0], gameRecord.now[1]);
    
                for (let i = 0; i < 2; i++) {
                    let move = gameRecord.now[i];
                
                    switch (move) {
                        case '기':
                            gameRecord.cnt[i] += 1;
                            break;
                        case '파':
                            gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 1);
                            break;
                        case '에네':
                            gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 3);
                            break;
                        case '순간':
                            gameRecord.cnt[i] = Math.max(0, gameRecord.cnt[i] - 1);
                            break;
                    }
                
                    gameRecord.past[i].push(move);
                }
    
                if (result === 1 || result === -1) {
                    endGame(roomId, result, io);
                } else {
                    responses = [false, false];
                
                    io.to(roomId).emit('gameResult', gameRecord, result);
                }

            }

        }

    });


    socket.on('resultReceived', (roomId, playerType) => {
        if (rooms[roomId].status === 1) {
            const gameRecord = gameRecords[roomId];

            console.log(`received ${playerType}`);
            responses[playerType] = true; 
            gameRecord.now = [null, null];

            if (responses.every(i => i === true)) {
                console.log(`All Received`);
                startCountdown(roomId, io);
            }
        }
    });


    // DISCONNECT
    socket.on('disconnect', async () => {
        console.log('A user disconnected:', socket.id);

        const userUuid = socket.userUuid;
        const roomId = socket.roomId;

        await roomUpdate(roomId, io);
        const roomData = rooms[roomId];
        
        const index = (userUuid === roomData.ownerUuid) ? 0 : 
        (userUuid === roomData.challengerUuid) ? 1 : -1;

        if (roomData.status == 1 && index !== -1) {
            roomData.etc[index] = -1;
            console.log(`[DISCONNECT] R${roomId}: ${userUuid}, etc ${roomData.etc}`);
            await roomUpdate(roomId, io);
            io.to(roomId).emit('eventRoom', 'Pause');
        }
    });
});


function calculateResult(ownerMove, challengerMove) {
    console.log(`O : ${ownerMove}, C : ${challengerMove} `);
    if (ownerMove === '에네') {
        if (challengerMove !== '순간' && challengerMove !== '에네') {
            return 1;
        }
    }

    if (challengerMove === '에네') {
        if (ownerMove !== '순간' && ownerMove !== '에네') {
            return -1;
        }
    }

    if (ownerMove === '파') {
        if (challengerMove === '기') {
            return 1;
        }
    }

    if (challengerMove === '파') {
        if (ownerMove === '기') {
            return -1;
        }
    }

    return 0;
}


server.listen(3000, () => {
    console.log('listening on *:3000');
});
