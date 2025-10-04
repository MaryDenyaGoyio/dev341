<?php
require_once __DIR__ . '/lib/minecraft.php';

MinecraftServer::refreshInventoryCacheIfStale();

$onlineInfo = MinecraftServer::getOnlinePlayers();
$players = MinecraftServer::getUserCache();

// Sort players by name for a consistent listing.
usort($players, static function ($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

function formatPlaytime(?array $playtime): string
{
    if (!$playtime) {
        return 'None';
    }

    $hours = floor($playtime['hours']);
    $minutes = floor(($playtime['hours'] - $hours) * 60);
    $seconds = floor(($playtime['minutes'] - floor($playtime['minutes'])) * 60);

    $parts = [];
    if ($hours > 0) {
        $parts[] = sprintf('%dh ', $hours);
    }
    if ($minutes > 0) {
        $parts[] = sprintf('%dm ', $minutes);
    }
    if ($seconds > 0 || empty($parts)) {
        $parts[] = sprintf('%ds', $seconds);
    }

    return implode(' ', $parts);
}

function formatItemName(?string $id): string
{
    if (!$id) {
        return 'unknown';
    }

    $id = str_starts_with($id, 'minecraft:') ? substr($id, strlen('minecraft:')) : $id;
    $id = str_replace('_', ' ', $id);

    return ucwords($id);
}

function getSlotLabel(int $slot): string
{
    return match ($slot) {
        103 => '머리 슬롯',
        102 => '가슴 슬롯',
        101 => '다리 슬롯',
        100 => '신발 슬롯',
        40 => '왼손 슬롯',
        default => sprintf('슬롯 %d', $slot),
    };
}

function getSlotCategory(int $slot): ?string
{
    return match ($slot) {
        103, 102, 101, 100 => 'armor',
        40 => 'offhand',
        default => null,
    };
}

$onlineLookup = [];
if ($onlineInfo['success']) {
    $onlineLookup = array_flip(array_map('strtolower', $onlineInfo['players']));
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>Blizzard Minecraft</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #111; color: #f5f5f5; }
        h1, h2 { margin-bottom: 0.5rem; }
        .players { margin-top: 1.5rem; }
        .player-card { background: #1b1b1b; border: 1px solid #222; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .player-card.online { border-color: #2ecc71; }
        .player-header { display: flex; align-items: center; gap: 0.75rem; }
        .player-head { width: 48px; height: 48px; border-radius: 6px; image-rendering: pixelated; background: #333; }
        .player-name { font-size: 1.25rem; margin: 0; }
        .status { font-weight: bold; }
        .status.online { color: #2ecc71; }
        .status.offline { color: #e74c3c; }
        .inventory { margin-top: 0.75rem; }
        .inventory h3 { font-size: 1.05rem; margin: 0 0 0.5rem; }
        .inventory-ui { position: relative; background: url('/static/gui/inventory.png') no-repeat top center; background-size: 100% auto; image-rendering: pixelated; margin: 0.75rem auto 0; --scale: 2; }
        .inventory-ui .slot { position: absolute; width: calc(16px * var(--scale)); height: calc(16px * var(--scale)); image-rendering: pixelated; display: flex; align-items: flex-end; justify-content: flex-end; cursor: default; }
        .inventory-ui .slot.selected { outline: calc(1px * var(--scale)) solid rgba(241, 196, 15, 0.75); outline-offset: calc(-1px * var(--scale)); }
        .inventory-ui .slot.slot-armor::after,
        .inventory-ui .slot.slot-offhand::after { content: ''; position: absolute; inset: 0; border: calc(1px * var(--scale)) solid rgba(255, 255, 255, 0.2); pointer-events: none; }
        .inventory-ui .slot img { width: 100%; height: 100%; image-rendering: pixelated; filter: drop-shadow(1px 1px 0 rgba(0,0,0,0.75)); }
        .inventory-ui .slot .count { position: absolute; right: calc(2px * var(--scale)); bottom: calc(1px * var(--scale)); font-size: calc(8px * var(--scale)); font-weight: 600; color: #fff; text-shadow: 1px 1px 0 #000, -1px -1px 0 #000; pointer-events: none; }
        header { margin-bottom: 2rem; }
        .error { color: #e67e22; }
    </style>
</head>
<body>
<header>
    <h1>Minecraft player list</h1>
<?php if (!$onlineInfo['success']): ?>
        <p class="error">※ Failed to connect to RCON. (<?php echo htmlspecialchars($onlineInfo['raw'], ENT_QUOTES, 'UTF-8'); ?>)</p>
<?php else: ?>
        <?php
        $onlineCount = $onlineInfo['online'] ?? 0;
        $knownPlayers = count($players);
        ?>
        <p>Currently online: <strong><?php echo $onlineCount; ?></strong> / <?php echo $knownPlayers; ?> players</p>
<?php endif; ?>
</header>
<section class="players">
    <?php if (empty($players)): ?>
        <p>No registered player information.</p>
    <?php else: ?>
        <?php foreach ($players as $player):
            $name = $player['name'] ?? 'Unknown';
            $uuid = $player['uuid'] ?? '';
            $isOnline = isset($onlineLookup[strtolower($name)]);
            $playtime = MinecraftServer::getPlaytime($uuid);
            $avatarUrl = MinecraftServer::getAvatarUrl($uuid, 64);
            $inventoryData = MinecraftServer::getInventory($uuid);
            $inventoryItems = $inventoryData['inventory'] ?? [];
            $slotPositions = MinecraftServer::getPlayerInventorySlotPositions();
            $textureMeta = MinecraftServer::getInventoryTextureMeta();
            $inventoryScale = 2;
            $inventoryWidth = $textureMeta['width'] * $inventoryScale;
            $inventoryHeight = $textureMeta['height'] * $inventoryScale;
            $trimTop = $textureMeta['trim_top'];
            $itemsBySlot = [];
            foreach ($inventoryItems as $entry) {
                if (!isset($entry['slot'])) {
                    continue;
                }
                $slotIndex = (int) $entry['slot'];
                if (!array_key_exists($slotIndex, $slotPositions)) {
                    continue;
                }
                $itemsBySlot[$slotIndex] = $entry;
            }
            $selectedSlot = isset($inventoryData['raw']['SelectedItemSlot']) ? (int) $inventoryData['raw']['SelectedItemSlot'] : null;
        ?>
            <article class="player-card <?php echo $isOnline ? 'online' : ''; ?>">
                <div class="player-header">
                    <?php if ($avatarUrl): ?>
                        <img class="player-head" src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>의 스킨" />
                    <?php endif; ?>
                    <h2 class="player-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <p class="status <?php echo $isOnline ? 'online' : 'offline'; ?>">
                    <?php echo $isOnline ? 'online' : 'offline'; ?>
                </p>
                <p>playtime: <?php echo htmlspecialchars(formatPlaytime($playtime), ENT_QUOTES, 'UTF-8'); ?></p>
                <section class="inventory">
                    <h3>Inventory</h3>
                    <div class="inventory-ui" style="--scale: <?php echo $inventoryScale; ?>; width: <?php echo $inventoryWidth; ?>px; height: <?php echo $inventoryHeight; ?>px;">
                        <?php foreach ($slotPositions as $slotIndex => $coords):
                            [$slotX, $slotY] = $coords;
                            $left = $slotX * $inventoryScale;
                            $top = max(0, $slotY - $trimTop) * $inventoryScale;
                            $item = $itemsBySlot[$slotIndex] ?? null;
                            $itemName = $item ? formatItemName($item['id'] ?? '') : '빈 슬롯';
                            $count = (int) ($item['count'] ?? 0);
                            $iconData = ($item && isset($item['id'])) ? MinecraftServer::getItemIconUrl($item['id']) : null;
                            $slotClasses = 'slot';
                            if ($selectedSlot !== null && $slotIndex === $selectedSlot) {
                                $slotClasses .= ' selected';
                            }
                            $slotCategory = getSlotCategory($slotIndex);
                            if ($slotCategory) {
                                $slotClasses .= ' slot-' . $slotCategory;
                            }
                            $slotLabel = getSlotLabel($slotIndex);
                            $title = $item
                                ? sprintf('%s ×%d (%s)', $itemName, max($count, 1), $slotLabel)
                                : sprintf('%s (비어 있음)', $slotLabel);
                            ?>
                            <div class="<?php echo $slotClasses; ?>" style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px;" title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($iconData): ?>
                                    <img src="<?php echo htmlspecialchars($iconData['primary'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-fallback="<?php echo htmlspecialchars($iconData['fallback'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'); ?>"
                                        onerror="if (this.dataset.fallback && this.src !== this.dataset.fallback) { this.src = this.dataset.fallback; } else { this.remove(); }" />
                                <?php endif; ?>
                                <?php if ($item && $count > 1): ?>
                                    <span class="count"><?php echo $count; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
</body>
</html>
