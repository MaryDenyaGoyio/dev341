<?php
require_once __DIR__ . '/../lib/minecraft.php';

header('Content-Type: application/json; charset=utf-8');

$response = MinecraftServer::getOnlinePlayers();
if (!$response['success']) {
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
