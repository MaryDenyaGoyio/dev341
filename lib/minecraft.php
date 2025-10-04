<?php

/**
 * Helper utilities for interacting with the local Minecraft server runtime.
 */
final class MinecraftServer
{
    private const MCRCON_PATH = '/app/minecraft/mcrcon/mcrcon';
    private const RCON_HOST = '127.0.0.1';
    private const RCON_PORT = 25575;
    private const RCON_PASSWORD = '980655';
    private const USERCACHE_PATH = '/app/minecraft/usercache.json';
    private const STATS_DIR = '/app/minecraft/world/stats';
    private const SKIN_BASE = 'https://crafatar.com/avatars/';
    private const INVENTORY_CACHE = '/app/minecraft/cache/inventories.json';
    private const ITEM_TEXTURE_BASE = 'https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.21.4/assets/minecraft/textures';
    private const INVENTORY_EXPORT_SCRIPT = '/app/scripts/export_inventory.py';
    private const GUI_BASE_WIDTH = 176;
    private const GUI_BASE_HEIGHT = 166;
    private const GUI_TEXTURE_HEIGHT = 111; // current trimmed inventory texture height
    private const GUI_SLOT_SIZE = 16;

    /** @var array<string, array>|null */
    private static ?array $inventoryCache = null;
    /** @var array<int, array{int,int}>|null */
    private static ?array $playerSlotPositions = null;

    /**
     * Execute an RCON command through the bundled mcrcon client.
     *
     * @param string $command RCON command to execute (e.g. 'list').
     * @return array{success: bool, output: string, exit_code: int}
     */
    public static function runRcon(string $command): array
    {
        $binary = self::MCRCON_PATH;
        if (!is_executable($binary)) {
            return [
                'success' => false,
                'output' => 'mcrcon binary not executable',
                'exit_code' => 126,
            ];
        }

        $cmd = sprintf(
            '%s -H %s -P %d -p %s %s 2>&1',
            escapeshellarg($binary),
            escapeshellarg(self::RCON_HOST),
            self::RCON_PORT,
            escapeshellarg(self::RCON_PASSWORD),
            escapeshellarg($command)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $joined = trim(implode("\n", $output));

        return [
            'success' => $exitCode === 0,
            'output' => $joined,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Build a public avatar URL for the player's head (front-facing skin).
     */
    public static function getAvatarUrl(string $uuid, int $size = 64): ?string
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        $size = max(8, min(512, $size));

        // Crafatar expects UUID without dashes.
        $sanitized = str_replace('-', '', $uuid);

        return sprintf('%s%s?size=%d&overlay', self::SKIN_BASE, rawurlencode($sanitized), $size);
    }

    /**
     * Fetch the current online player list via RCON `list` command.
     *
     * @return array{success: bool, online: int|null, max: int|null, players: string[], raw: string, error?: string}
     */
    public static function getOnlinePlayers(): array
    {
        $result = self::runRcon('list');
        $raw = self::normalizeOutput($result['output']);

        if (!$result['success']) {
            return [
                'success' => false,
                'online' => null,
                'max' => null,
                'players' => [],
                'raw' => $raw,
                'error' => 'RCON command failed',
            ];
        }

        $online = null;
        $max = null;
        $players = [];

        $pattern = '/There are (\d+) of a max of (\d+) players online:? ?(.*)/i';
        if (preg_match($pattern, $raw, $matches)) {
            $online = (int) $matches[1];
            $max = (int) $matches[2];
            $list = trim($matches[3] ?? '');

            if ($online > 0 && $list !== '') {
                $players = array_values(array_filter(array_map('trim', explode(',', $list))));
            }
        }

        return [
            'success' => true,
            'online' => $online,
            'max' => $max,
            'players' => $players,
            'raw' => $raw,
        ];
    }

    /**
     * Read usercache.json and return the decoded contents.
     *
     * @return array<int, array{name: string, uuid: string, expiresOn?: string}>
     */
    public static function getUserCache(): array
    {
        $path = self::USERCACHE_PATH;
        if (!is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Return playtime details for the supplied UUID.
     *
     * @param string $uuid
     * @return array{ticks: int, seconds: float, minutes: float, hours: float} | null
     */
    public static function getPlaytime(string $uuid): ?array
    {
        $statsPath = sprintf('%s/%s.json', self::STATS_DIR, $uuid);
        if (!is_readable($statsPath)) {
            return null;
        }

        $json = file_get_contents($statsPath);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $ticks = $data['stats']['minecraft:custom']['minecraft:play_time'] ?? null;
        if (!is_int($ticks)) {
            return null;
        }

        $seconds = $ticks / 20;
        $minutes = $seconds / 60;
        $hours = $minutes / 60;

        return [
            'ticks' => $ticks,
            'seconds' => $seconds,
            'minutes' => $minutes,
            'hours' => $hours,
        ];
    }

    /**
     * Return cached inventory data pre-generated by scripts/export_inventory.py
     *
     * @return array<string, array>
     */
    public static function getInventoryCache(): array
    {
        if (self::$inventoryCache !== null) {
            return self::$inventoryCache;
        }

        $path = self::INVENTORY_CACHE;
        if (!is_readable($path)) {
            self::$inventoryCache = [];
            return self::$inventoryCache;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            self::$inventoryCache = [];
            return self::$inventoryCache;
        }

        $data = json_decode($json, true);
        self::$inventoryCache = is_array($data) ? $data : [];

        return self::$inventoryCache;
    }

    public static function getInventory(string $uuid): ?array
    {
        $cache = self::getInventoryCache();
        return $cache[$uuid] ?? null;
    }

    /**
     * Refresh inventory cache if it is older than the provided threshold.
     */
    public static function refreshInventoryCacheIfStale(int $maxAgeSeconds = 5): bool
    {
        $cachePath = self::INVENTORY_CACHE;
        $script = self::INVENTORY_EXPORT_SCRIPT;

        $mtime = is_readable($cachePath) ? filemtime($cachePath) : false;
        if ($mtime !== false && (time() - $mtime) < $maxAgeSeconds) {
            return true;
        }

        if (!is_readable($script)) {
            return false;
        }

        $command = sprintf('%s %s 2>&1', 'python3', escapeshellarg($script));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            error_log('[MinecraftServer] inventory export failed: ' . implode("
", $output));
            @file_put_contents('/app/minecraft/cache/inventory_refresh.log', date('c') . " failed: " . implode(' | ', $output) . "
", FILE_APPEND);
            return false;
        }

        self::$inventoryCache = null;
        @file_put_contents('/app/minecraft/cache/inventory_refresh.log', date('c') . " refreshed
", FILE_APPEND);

        return true;
    }


    /**
     * Player inventory slot coordinates based on vanilla GUI (176x166).
     *
     * Keys are slot indices (0-35 for main inventory + hotbar).
     * Values are [x, y] pixel coordinates of the slot's top-left corner.
     *
     * @return array<int, array{int, int}>
     */
    public static function getPlayerInventorySlotPositions(): array
    {
        if (self::$playerSlotPositions !== null) {
            return self::$playerSlotPositions;
        }

        $positions = [];

        // Main inventory rows (slots 9-35): 9 columns, 3 rows
        for ($slot = 9; $slot <= 35; $slot++) {
            $offset = $slot - 9;
            $row = intdiv($offset, 9);
            $col = $offset % 9;
            $x = 8 + ($col * 18);
            $y = 84 + ($row * 18);
            $positions[$slot] = [$x, $y];
        }

        // Hotbar (slots 0-8)
        for ($slot = 0; $slot <= 8; $slot++) {
            $x = 8 + ($slot * 18);
            $y = 142;
            $positions[$slot] = [$x, $y];
        }

        $slotSpacing = 18;
        $armorRowY = 62;
        $armorStartX = 8;

        // Custom horizontal armor layout: Head, Chest, Legs, Boots.
        $positions[103] = [$armorStartX + ($slotSpacing * 0), $armorRowY];
        $positions[102] = [$armorStartX + ($slotSpacing * 1), $armorRowY];
        $positions[101] = [$armorStartX + ($slotSpacing * 2), $armorRowY];
        $positions[100] = [$armorStartX + ($slotSpacing * 3), $armorRowY];

        // Offhand slot aligned with the rightmost column of the main inventory row.
        $positions[40] = [$armorStartX + ($slotSpacing * 8), $armorRowY];

        self::$playerSlotPositions = $positions;

        return self::$playerSlotPositions;
    }

    /**
     * Metadata describing the current inventory GUI texture layout.
     *
     * @return array{width:int,height:int,original_height:int,trim_top:int,slot_size:int}
     */
    public static function getInventoryTextureMeta(): array
    {
        $trimTop = max(0, self::GUI_BASE_HEIGHT - self::GUI_TEXTURE_HEIGHT);

        return [
            'width' => self::GUI_BASE_WIDTH,
            'height' => self::GUI_TEXTURE_HEIGHT,
            'original_height' => self::GUI_BASE_HEIGHT,
            'trim_top' => $trimTop,
            'slot_size' => self::GUI_SLOT_SIZE,
        ];
    }

    /**
     * Return URLs for the item's icon (with block fallback).
     *
     * @return array{primary: string, fallback: string}|null
     */
    public static function getItemIconUrl(string $itemId): ?array
    {
        $itemId = trim(strtolower($itemId));
        if ($itemId === '') {
            return null;
        }

        $itemId = str_starts_with($itemId, 'minecraft:')
            ? substr($itemId, strlen('minecraft:'))
            : $itemId;

        $itemPath = sprintf('%s/item/%s.png', self::ITEM_TEXTURE_BASE, $itemId);
        $fallback = sprintf('%s/block/%s.png', self::ITEM_TEXTURE_BASE, $itemId);

        return [
            'primary' => $itemPath,
            'fallback' => $fallback,
        ];
    }

    private static function normalizeOutput(string $output): string
    {
        // Remove ANSI color codes and stray control characters.
        return preg_replace('/\e\[[\d;]*[A-Za-z]/', '', $output) ?? $output;
    }
}
