<?php
require_once __DIR__ . '/../lib/minecraft.php';

header('Content-Type: application/json; charset=utf-8');

$online = MinecraftServer::getOnlinePlayers();
$onlineLookup = [];
if ($online['success']) {
    $onlineLookup = array_flip(array_map('strtolower', $online['players']));
}

$players = [];
foreach (MinecraftServer::getUserCache() as $player) {
    $uuid = $player['uuid'] ?? null;
    $name = $player['name'] ?? null;

    if (!$uuid || !$name) {
        continue;
    }

    $playtime = MinecraftServer::getPlaytime($uuid);
    $onlineNow = isset($onlineLookup[strtolower($name)]);

    $players[] = [
        'uuid' => $uuid,
        'name' => $name,
        'online' => $onlineNow,
        'playtime' => $playtime,
        'expiresOn' => $player['expiresOn'] ?? null,
    ];
}

$response = [
    'online' => $online,
    'players' => $players,
];

if (!$online['success']) {
    http_response_code(206); // Partial content: players list OK but RCON failed.
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
