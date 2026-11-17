<?php

class JsonBody
{
    public static function read(string $shape = 'items'): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false) {
            throw new RuntimeException('Failed to read request body');
        }

        try {
            // Preserve the distinction between JSON objects and JSON arrays.
            $data = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON');
        }

        if ($shape === 'object') {
            if (!($data instanceof stdClass)) {
                throw new InvalidArgumentException('Expected a JSON object');
            }

            return get_object_vars($data);
        }

        if (!in_array($shape, ['items', 'ids'], true)) {
            throw new LogicException('Unknown body shape');
        }

        if (!is_array($data) || count($data) === 0 || count($data) > 100) {
            throw new InvalidArgumentException('Expected a list with 1 to 100 items');
        }

        foreach ($data as $index => $item) {
            if ($shape === 'ids') {
                if (!is_int($item) || $item <= 0) {
                    throw new InvalidArgumentException("Item $index must be a positive integer ID");
                }

                continue;
            }

            if (!($item instanceof stdClass)) {
                throw new InvalidArgumentException("Item $index must be a JSON object");
            }

            $data[$index] = get_object_vars($item);
        }

        return $data;
    }
}
