const express = require('express');
const http = require('http');
const socketIo = require("socket.io");
const intercept = require('intercept-stdout');

const app = express();
const server = http.createServer(app);
const io = socketIo(server);
const cors = require('cors');
app.use(cors());

let rooms = {};
let games = {};
let secret = {};
let timers = {};
let interceptors = new Map();

app.get('/debug/:roomId', (req, res) => {
    const roomId = req.params.roomId;

    const roomData = rooms[roomId] || null;
    const gameData = games[roomId] || null;
    const secretData = secret[roomId] || null; 

    if (!roomData) {
        return res.status(404).json({ error: 'Room not found' });
    }

    res.json({
        room: roomData
        ,game: gameData
        //,secret: secretData
    });
});


const mysql = require('mysql');

const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: 'e^ipi=-1',
    database: 'DragonBall'
});

db.connect((err) => {
    if (err) {
        console.error('MySQL connection error:', err);
        return;
    }
    console.log('MySQL connected.');
});

function stopCountdown(roomId) {
    console.log(`${roomId} | stopCountdown`);
    if (timers[roomId]) {
        clearTimeout(timers[roomId]);
        timers[roomId] = null;
    }
}

// make rooms data
function makeRoomData(roomId, io) {
    console.log(`${roomId} | makeRoomData`);

    return new Promise((resolve, reject) => {

        if (!(roomId in rooms)) {
            rooms[roomId] = {};
            rooms[roomId].ready = [false, false];
            rooms[roomId].here = [false, false];
            rooms[roomId].spectator = [];
            rooms[roomId].result = null;
        }

        // Get rooms Data
        db.query('SELECT owner_uuid, challenger_uuid, status FROM rooms WHERE id = ?', [roomId], (err, queryResult) => {
            if (err) {
                reject(err);
            } else if (queryResult && queryResult.length > 0) {

                rooms[roomId].ownerUuid = queryResult[0].owner_uuid || null;
                rooms[roomId].challengerUuid = queryResult[0].challenger_uuid || null;
                
                rooms[roomId].status = queryResult[0].status;

                // Get owner and challenger Data
                db.query('SELECT uuid, name, win, lose, rating FROM users WHERE uuid IN (?, ?)', [rooms[roomId].ownerUuid, rooms[roomId].challengerUuid], (err, usersResult) => {
                    if (err) {
                        reject(err);
                    } else if (usersResult && usersResult.length > 0) {
                        const ownerData = usersResult.find(user => user.uuid === rooms[roomId].ownerUuid);
                        const challengerData = rooms[roomId].challengerUuid !== null ? usersResult.find(user => user.uuid === rooms[roomId].challengerUuid) : null;

                        rooms[roomId].owner = ownerData;
                        rooms[roomId].challenger = challengerData;

                        io.to(roomId).emit('updateRoom', rooms[roomId]);
                        resolve();
                    } else {
                        reject('No user ' + rooms[roomId].ownerUuid + ' | ' + rooms[roomId].challengerUuid);
                    }
                });
            } else {
                io.to(roomId).emit('invalid');
                reject('No room ' + roomId);
            }
        });
    });
}



// update when Game ends
function endGame(roomId, result) {
    console.log(`${roomId} | endGame`);

    return new Promise((resolve, reject) => {

        // update room status 0
        rooms[roomId].ready = [false, false];
        rooms[roomId].status = 0;
        rooms[roomId].result = result;

        db.query('UPDATE rooms SET status = 0 WHERE id = ?', [roomId], (err) => {if (err) {reject(err);} });
        io.to(roomId).emit('setStatus', rooms[roomId].status);


        // update game
        const winnerUuid = result === 1 ? rooms[roomId].ownerUuid : result === -1 ? rooms[roomId].challengerUuid : null;
        const loserUuid = result === 1 ? rooms[roomId].challengerUuid : result === -1 ? rooms[roomId].ownerUuid : null;
        
        const query = `
            UPDATE games 
            SET 
                winner_uuid = ?, 
                loser_uuid = ?, 
                result = ?, 
                time_end = ?,
                game_status = ?
            WHERE id = ?
        `;
        db.query(query, [winnerUuid, loserUuid, result, new Date(), -1, games[roomId].id], (err) => { if (err) {reject(err);} });

        
        // update users stats
        const ownerWinProb = 1 / (1 + Math.pow(10, (rooms[roomId].challenger.rating - rooms[roomId].owner.rating) / 400));
        const ChangeWhenOwnerWin = Math.round(30 * (1 - ownerWinProb));
        const ChangeWhenChallengerWin = Math.round(30 * ownerWinProb); 
        const Change = result === 1 ? ChangeWhenOwnerWin : result === -1 ? ChangeWhenChallengerWin : 0;

        const ownerWin = rooms[roomId].owner.win + ((result < 0) ? 0 : result);
        const ownerLose = rooms[roomId].owner.lose - ((result > 0) ? 0 : result);
        const ownerRating = rooms[roomId].owner.rating + Change * result;
        const challengerWin = rooms[roomId].challenger.win - ((result > 0) ? 0 : result);
        const challengerLose = rooms[roomId].challenger.lose + ((result < 0) ? 0 : result);
        const challengerRating = rooms[roomId].challenger.rating - Change * result;

        const ownerUpdateQuery = `
            UPDATE users 
            SET 
                win = ?, 
                lose = ?, 
                rating = ? 
            WHERE uuid = ?
        `;

        const challengerUpdateQuery = `
            UPDATE users 
            SET 
                win = ?, 
                lose = ?, 
                rating = ? 
            WHERE uuid = ?
        `;

        db.query(ownerUpdateQuery, [ ownerWin, ownerLose, ownerRating, rooms[roomId].ownerUuid], (err) => { if (err) {reject(err);} });
        db.query(challengerUpdateQuery, [ challengerWin, challengerLose, challengerRating, rooms[roomId].challengerUuid], (err) => { if (err) {reject(err);} });
        
        // 미구현 : Game Log 저장
        io.to(roomId).emit('updateGame', games[roomId], result);

        makeRoomData(roomId, io)
        .then(() => resolve())
        .catch(reject);
    });

}



// update when Game starts
function startGame(roomId) {
    console.log(`${roomId} | startGame`);

    return new Promise((resolve, reject) => {

        // update room
        rooms[roomId].status = 1;
        rooms[roomId].result = null;

        db.query('UPDATE rooms SET status = 1 WHERE id = ?', [roomId], (err) => { if (err) { reject(err); }
            else{
                io.to(roomId).emit('setStatus', rooms[roomId].status);

                // make new game data
                db.query(`INSERT INTO games (room_id) VALUES (?)`, [roomId], (err, result) => { if (err) { reject(err); } 
                    else {

                        games[roomId] = {
                            id: result.insertId,
                            past: [[], []],
                            cnt : [0, 0],
                            ready : [false, false],
                            time : 3
                        };

                        secret[roomId] = [null, null];

                        io.to(roomId).emit('updateGame', games[roomId], null);
                        io.to(roomId).emit('countdown', games[roomId].time);
                        resolve();
                    }
                });        
            }
        });

    });
}



function nextResult(roomId) {

    let result = 0;

    // Game Rule about Result
    if ( (secret[roomId][0] === null) || (secret[roomId][1] === null) ) {
        result = (secret[roomId][0] === null ? 0 : 1) - (secret[roomId][1] === null ? 0 : 1);
    }

    if (secret[roomId][0] === '에네') {
        if (secret[roomId][1] !== '순간' && secret[roomId][1] !== '에네') {
            result = 1;
        }
    }
    if (secret[roomId][1] === '에네') {
        if (secret[roomId][0] !== '순간' && secret[roomId][0] !== '에네') {
            result = -1;
        }
    }
    if (secret[roomId][0] === '파') {
        if (secret[roomId][1] === '기') {
            result = 1;
        }
    }
    if (secret[roomId][1] === '파') {
        if (secret[roomId][0] === '기') {
            result = -1;
        }
    }            

    // Game Rule about Count
    for (let i = 0; i < 2; i++) {
        let move = secret[roomId][i];
    
        switch (move) {
            case '기':
                games[roomId].cnt[i] += 1;
                break;
            case '파':
                games[roomId].cnt[i] = Math.max(0, games[roomId].cnt[i] - 1);
                break;
            case '에네':
                games[roomId].cnt[i] = Math.max(0, games[roomId].cnt[i] - 3);
                break;
            case '순간':
                games[roomId].cnt[i] = Math.max(0, games[roomId].cnt[i] - 1);
                break;
        }
    
        games[roomId].past[i].push(move);
        secret[roomId][i] = null;
    }

    return result;
}



io.on('connection', (socket) => {

    // A-1) JOIN
    socket.on('join', async (myType, roomId, userUuid, userName) => {
        console.log(`${roomId} | [JOIN]`, { myType, userUuid, userName});

        socket.join(roomId);
        socket.userUuid = userUuid;
        socket.userName = userName;
        // 생각 - 각 socket마다 고유번호 주고, 해당 index에 관전자 명을 넣는 방식
        socket.roomId = roomId;

        try {
            // Im Onwer or Challenger
            if (myType >= 0) {
                await makeRoomData(roomId, io);
                rooms[roomId].here[myType] = true;
            } 
            // Im Spectator
            else {
                rooms[roomId].spectator.push(userName || '익명');
                socket.emit('updateRoom', rooms[roomId]); // B-1) UPDATE ROOM
                io.to(roomId).emit('updateSpectators', rooms[roomId].spectator);
            }

            if (roomId in games) {
                socket.emit('updateGame', games[roomId], null); // B-3) UPDATE GAME
            }

        } catch (err) {
            console.error(err);
        }
    });


    // A-2-1) GET READY
    socket.on('ready', async (myType, roomId, userUuid) => {
        console.log(`${roomId} | [READY]`, { myType, userUuid });
        rooms[roomId].ready[myType] = !rooms[roomId].ready[myType];
        rooms[roomId].result = null;
        io.to(roomId).emit('ready', rooms[roomId].ready, rooms[roomId].here);

        // if everyone gets ready, start Game
        if (rooms[roomId].ready.every(i => i === true)) {
            await startGame(roomId);
        }

    });

    socket.on('challengerGiveUp', async (roomId, userUuid, userName) => {
        console.log(`${roomId} | [CHALLENGER GIVEUP]`, { userUuid, userName });
        rooms[roomId].ready[1] = false;
        rooms[roomId].here[1] = false;
        rooms[roomId].challengerUuid = null;
        rooms[roomId].challenger = null;
        rooms[roomId].result = null;
        rooms[roomId].spectator.push(userName || '익명');

        await new Promise((resolve, reject) => {
            // UPDATE Challenger
            db.query('UPDATE rooms SET challenger_uuid = NULL WHERE id = ?', [roomId], (err) => { if (err) {reject(err);} else {resolve();} });
        });

        await makeRoomData(roomId, io);
    });

    // A-2-2) QUIT
    socket.on('closeRoom', async (roomId, userUuid) => {
        console.log(`${roomId} | [CLOSE ROOM]`, { userUuid });
        delete rooms[roomId];
        delete games[roomId];
        delete secret[roomId];
        delete timers[roomId];

        await new Promise((resolve, reject) => {

            // CLOSE room
            db.query('UPDATE rooms SET status = -1, time_close = ? WHERE id = ?', [new Date(), roomId], (err) => {
                if (err) {
                    reject(err);
                } else {
                    // Kick everyone
                    io.to(roomId).emit('abandoned'); // B-0-1) ROOM DELETED
                    resolve();
                }
            });
        });
    });


    // A-3) SELECT MOVE
    socket.on('getMove', async (myType, roomId, move) => {
        console.log(`${roomId} | [GET MOVE]`, { myType, move });

        if (rooms[roomId].status === 1) {

            secret[roomId][myType] = move;
    
            // If all moves are done,
            if (secret[roomId].every(i => i !== null)) {

                stopCountdown(roomId);
            
                let result = nextResult(roomId);

                // [GAME SET 1] If game ends, send end
                if (result !== 0) {
                    await endGame(roomId, result);
                }
                // If game goes on, start new countdown
                else {
                    io.to(roomId).emit('updateGame', games[roomId], result);
                    games[roomId].time = 3
                    io.to(roomId).emit('countdown', games[roomId].time);
                }

            }
        }
    });


    // C-1) check Reception
    socket.on('ReceivedCountDown', async (myType, roomId, time) => {
        console.log(`${roomId} | [R COUNT]`, { myType, time });

        if (rooms[roomId].status === 1) {

            games[roomId].ready[myType] = true;

            if (games[roomId].ready.every(i => i === true)) {

                games[roomId].time = time
                games[roomId].ready[0] = false;
                games[roomId].ready[1] = false;

                if (time > 0) {
                    if (timers[roomId]) {
                        clearTimeout(timers[roomId]);
                    }
                    
                    timers[roomId] = setTimeout(() => {
                        io.to(roomId).emit('countdown', time - 1);
                    }, 1000);                    
                } else {
                    // [GAME SET 2] decide result by secret (recieved move)
                    await endGame(roomId, nextResult(roomId));
                }

            }
        }
    });



    // DEBUG
    socket.on('debug', async () => {
        console.log('DEBUG');

        const interceptor = (log) => {
            socket.emit('log', log);
            return log;
        };

        interceptors.set(socket.id, intercept(interceptor));
    });



    // DISCONNECT
    socket.on('disconnect', async () => {

        const userUuid = socket.userUuid;
        const userName = socket.userName;
        const roomId = socket.roomId;

        const myType = (rooms[roomId].ownerUuid && (userUuid === rooms[roomId].ownerUuid)) ? 0 : 
        (rooms[roomId].challengerUuid && (userUuid === rooms[roomId].challengerUuid)) ? 1 : -1;

        console.log(`${roomId} | [DISCONNECT]`, { myType, userUuid });

        if (rooms[roomId].status == 1 && myType !== -1) {
            rooms[roomId].here[myType] = false;

            io.to(roomId).emit('pause', myType);
        }

        // DELETE spectator
        if (myType === -1) {
            const index = rooms[roomId].spectator.indexOf(userName || '익명');

            if (index !== -1) {
                rooms[roomId].spectator.splice(index, 1);
                io.to(roomId).emit('updateSpectators', rooms[roomId].spectator);
            }
        }

        const Intercept = interceptors.get(socket.id);
        if (Intercept) {
            Intercept();
            interceptors.delete(socket.id);
            console.log('Delete DEBUG');
        }
    });
});



server.listen(3000, () => {
    console.log('listening on *:3000');

    // Get all ongoing rooms
    db.query('SELECT id FROM rooms WHERE status IN (0, 1)', (err, results) => {
        if (err) {
            console.error('FAIL GETTING INTIAL ROOMS:', err);
        } else {
            console.log(`LOAD ${results.length} ROOMS...`);
    
            results.forEach(async (row) => {
                const roomId = row.id;
                try {
                    await makeRoomData(roomId, io);
                    console.log(`Room ${roomId} loaded.`);
                } catch (error) {
                    console.error(`FAIL TO LOAD Room ${roomId}:`, error);
                }
            });
        }
    });
    
});
