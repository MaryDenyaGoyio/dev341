#!/usr/bin/env python3
"""Export Minecraft player inventory data from NBT to JSON cache."""

from __future__ import annotations

import argparse
import gzip
import json
import struct
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

TAG_END = 0
TAG_BYTE = 1
TAG_SHORT = 2
TAG_INT = 3
TAG_LONG = 4
TAG_FLOAT = 5
TAG_DOUBLE = 6
TAG_BYTE_ARRAY = 7
TAG_STRING = 8
TAG_LIST = 9
TAG_COMPOUND = 10
TAG_INT_ARRAY = 11
TAG_LONG_ARRAY = 12

EQUIPMENT_SLOT_MAP = {
    'head': 103,
    'chest': 102,
    'legs': 101,
    'feet': 100,
    'body': 102,
    'offhand': 40,
}


class NBTStream:
    def __init__(self, data: bytes) -> None:
        self._mv = memoryview(data)
        self._pos = 0

    def read(self, size: int) -> bytes:
        if self._pos + size > len(self._mv):
            raise ValueError("Unexpected end of data")
        chunk = self._mv[self._pos : self._pos + size]
        self._pos += size
        return chunk.tobytes()

    def read_fmt(self, fmt: str) -> Tuple[Any, ...]:
        size = struct.calcsize(fmt)
        return struct.unpack(fmt, self.read(size))

    def read_byte(self) -> int:
        return struct.unpack('>b', self.read(1))[0]

    def read_unsigned_byte(self) -> int:
        return struct.unpack('>B', self.read(1))[0]

    def read_short(self) -> int:
        return struct.unpack('>h', self.read(2))[0]

    def read_int(self) -> int:
        return struct.unpack('>i', self.read(4))[0]

    def read_long(self) -> int:
        return struct.unpack('>q', self.read(8))[0]

    def read_float(self) -> float:
        return struct.unpack('>f', self.read(4))[0]

    def read_double(self) -> float:
        return struct.unpack('>d', self.read(8))[0]

    def read_string(self) -> str:
        length = self.read_short()
        if length < 0:
            raise ValueError("Negative string length")
        raw = self.read(length)
        return raw.decode('utf-8')


@dataclass
class NBTTag:
    tag_type: int
    name: Optional[str]
    value: Any


def parse_tag(stream: NBTStream, expect_name: bool = True) -> NBTTag:
    tag_type = stream.read_unsigned_byte()
    if tag_type == TAG_END:
        return NBTTag(tag_type, None, None)

    name = stream.read_string() if expect_name else None
    value = parse_payload(stream, tag_type)
    return NBTTag(tag_type, name, value)


def parse_payload(stream: NBTStream, tag_type: int) -> Any:
    if tag_type == TAG_BYTE:
        return stream.read_byte()
    if tag_type == TAG_SHORT:
        return stream.read_short()
    if tag_type == TAG_INT:
        return stream.read_int()
    if tag_type == TAG_LONG:
        return stream.read_long()
    if tag_type == TAG_FLOAT:
        return stream.read_float()
    if tag_type == TAG_DOUBLE:
        return stream.read_double()
    if tag_type == TAG_BYTE_ARRAY:
        length = stream.read_int()
        return list(stream.read(length))
    if tag_type == TAG_STRING:
        return stream.read_string()
    if tag_type == TAG_LIST:
        item_type = stream.read_unsigned_byte()
        length = stream.read_int()
        return [parse_payload(stream, item_type) for _ in range(length)]
    if tag_type == TAG_COMPOUND:
        obj: Dict[str, Any] = {}
        while True:
            tag = parse_tag(stream)
            if tag.tag_type == TAG_END:
                break
            if tag.name is None:
                continue
            obj[tag.name] = tag.value
        return obj
    if tag_type == TAG_INT_ARRAY:
        length = stream.read_int()
        return list(struct.unpack(f'>{length}i', stream.read(length * 4)))
    if tag_type == TAG_LONG_ARRAY:
        length = stream.read_int()
        return list(struct.unpack(f'>{length}q', stream.read(length * 8)))

    raise ValueError(f"Unsupported tag type: {tag_type}")


def parse_nbt(data: bytes) -> Dict[str, Any]:
    stream = NBTStream(data)
    root_tag = parse_tag(stream)
    if root_tag.tag_type != TAG_COMPOUND:
        raise ValueError(f"Root tag must be compound, got {root_tag.tag_type}")
    if not isinstance(root_tag.value, dict):
        raise ValueError("Root compound did not yield dict")
    return root_tag.value


def simplify_item(entry: Any) -> Optional[Dict[str, Any]]:
    if not isinstance(entry, dict):
        return None

    def normalize_value(value: Any) -> Any:
        if isinstance(value, dict):
            return {k: normalize_value(v) for k, v in value.items()}
        if isinstance(value, list):
            return [normalize_value(v) for v in value]
        return value

    count = entry.get('Count')
    if count is None:
        count = entry.get('count')

    item = {
        'slot': entry.get('Slot'),
        'id': entry.get('id'),
        'count': count,
    }

    tag = entry.get('tag')
    if tag:
        item['nbt'] = normalize_value(tag)

    components = entry.get('components')
    if components:
        item['components'] = normalize_value(components)

    # Remove None entries for tidier JSON.
    return {k: v for k, v in item.items() if v is not None}


def extract_player_inventory(path: Path) -> Dict[str, Any]:
    with gzip.open(path, 'rb') as fh:
        data = fh.read()
    nbt = parse_nbt(data)
    inv_raw = nbt.get('Inventory', [])
    ender_raw = nbt.get('EnderItems', [])
    equipment_raw = nbt.get('equipment', {}) or {}

    inventory = []
    for entry in inv_raw:
        item = simplify_item(entry)
        if not item:
            continue
        item['source'] = 'inventory'
        inventory.append(item)

    equipment: Dict[str, Dict[str, Any]] = {}
    if isinstance(equipment_raw, dict):
        for key, slot_index in EQUIPMENT_SLOT_MAP.items():
            entry = equipment_raw.get(key)
            if not isinstance(entry, dict):
                continue
            item = simplify_item(entry)
            if not item:
                continue
            item['slot'] = slot_index
            item['source'] = 'equipment'
            equipment[key] = item
            inventory.append(item)

    inventory.sort(key=lambda i: (i.get('slot') if i.get('slot') is not None else 999, i.get('id', '')))
    ender = []
    for entry in ender_raw:
        item = simplify_item(entry)
        if not item:
            continue
        item['source'] = 'ender_chest'
        ender.append(item)

    return {
        'inventory': inventory,
        'ender_chest': ender,
        'equipment': equipment,
        'raw': {
            'SelectedItemSlot': nbt.get('SelectedItemSlot'),
            'XpLevel': nbt.get('XpLevel'),
            'Health': nbt.get('Health'),
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Export Minecraft player inventories to JSON cache")
    parser.add_argument('--playerdata', type=Path, default=Path('/app/minecraft/world/playerdata'))
    parser.add_argument('--output', type=Path, default=Path('/app/minecraft/cache/inventories.json'))
    args = parser.parse_args()

    if not args.playerdata.is_dir():
        raise SystemExit(f"playerdata directory not found: {args.playerdata}")

    result: Dict[str, Any] = {}
    for dat_file in sorted(args.playerdata.glob('*.dat')):
        uuid = dat_file.stem
        try:
            result[uuid] = extract_player_inventory(dat_file)
        except Exception as exc:  # noqa: BLE001
            result[uuid] = {'error': str(exc)}

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
