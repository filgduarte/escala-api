<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/JsonBody.php';

class MusicianController
{
    protected static string $table = 'musicians';
    protected static array $fields = ['name', 'phone', 'email', 'birthday'];

    public static function create()
    {
        global $pdo;

        try {
            $data = JsonBody::read();
            foreach ($data as $item) {
                self::validateItem($item);
            }

            $pdo->beginTransaction();
            $ids = [];

            foreach ($data as $item) {
                $stmt = $pdo->prepare("INSERT INTO " . static::$table . " (" . implode(',', static::$fields) . ") VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $item['name'],
                    $item['phone']    ?? null,
                    $item['email']    ?? null,
                    $item['birthday'] ?? null
                ]);
                $itemId = $pdo->lastInsertId();
                $ids[] = $itemId;

                foreach ($item['instruments'] ?? [] as $relation) {
                    $stmtRel = $pdo->prepare("INSERT INTO musician_instruments (musician_id, instrument_id, priority) VALUES (?, ?, ?)");
                    $stmtRel->execute([$itemId, $relation[0], $relation[1]]);
                }
            }

            $pdo->commit();
            echo json_encode(['ids' => $ids]);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public static function read($id = null)
    {
        global $pdo;

        if ($id !== null) {
            $stmt = $pdo->prepare("SELECT * FROM " . static::$table . " WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if ($item) {
                $item['instruments'] = self::getInstrumentsFor($item['id']);
            }

            echo json_encode($item ?: []);
        } else {
            $stmt = $pdo->query("SELECT * FROM " . static::$table);
            $items = $stmt->fetchAll();

            foreach ($items as $index => $musician) {
                $items[$index]['instruments'] = self::getInstrumentsFor($musician['id']);
            }

            echo json_encode($items);
        }
    }

    public static function update()
    {
        global $pdo;
        try {
            $data = JsonBody::read();
            foreach ($data as $item) {
                self::validateItem($item, true);
            }

            $pdo->beginTransaction();
            foreach ($data as $item) {
                $fieldsToUpdate = [];
                $params = [];
                foreach (static::$fields as $field) {
                    if (!array_key_exists($field, $item)) continue;

                    $fieldsToUpdate[] = "$field = ?";
                    $params[] = $item[$field];
                }

                if (!empty($fieldsToUpdate)) {
                    $params[] = $item['id'];
                    $sql = "UPDATE " . static::$table . " SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?";
                    $pdo->prepare($sql)->execute($params);
                }

                // UPSERT nas relações com instrumentos
                if (isset($item['instruments'])) {
                    foreach ($item['instruments'] as $relation) {
                        $stmtRel = $pdo->prepare("
                            INSERT INTO musician_instruments (musician_id, instrument_id, priority)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE priority = VALUES(priority)
                        ");
                        $stmtRel->execute([$item['id'], $relation[0], $relation[1]]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'updated']);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public static function delete()
    {
        global $pdo;
        try {
            $ids = JsonBody::read('ids');
            $pdo->beginTransaction();
            foreach ($ids as $id) {
                $pdo->prepare("DELETE FROM musician_instruments WHERE musician_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM availabilities WHERE musician_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM " . static::$table . " WHERE id = ?")->execute([$id]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'deleted']);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public static function deleteInstrumentRelation()
    {
        global $pdo;
        try {
            $data = JsonBody::read('object');
            foreach (['musician_id', 'instrument_id'] as $field) {
                if (!isset($data[$field]) || !is_int($data[$field]) || $data[$field] <= 0) {
                    throw new InvalidArgumentException("$field must be a positive integer");
                }
            }

            $stmt = $pdo->prepare("DELETE FROM musician_instruments WHERE musician_id = ? AND instrument_id = ?");
            $stmt->execute([$data['musician_id'], $data['instrument_id']]);
            echo json_encode(['status' => 'relation removed']);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    private static function validateItem(array $item, bool $update = false): void
    {
        if ($update && (!isset($item['id']) || !is_int($item['id']) || $item['id'] <= 0)) {
            throw new InvalidArgumentException('Item id must be a positive integer');
        }

        foreach (static::$fields as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && !is_string($item[$field])) {
                throw new InvalidArgumentException("$field must be a string or null");
            }
        }

        if ((!$update || array_key_exists('name', $item)) &&
            (!isset($item['name']) || trim($item['name']) === '')) {
            throw new InvalidArgumentException('name is required and must be a string');
        }

        if (isset($item['email']) && $item['email'] !== '' &&
            !filter_var($item['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        self::validateInstruments($item);
    }

    private static function validateInstruments(array $item): void
    {
        if (!array_key_exists('instruments', $item)) {
            return;
        }

        if (!is_array($item['instruments'])) {
            throw new InvalidArgumentException('instruments must be an array');
        }

        foreach ($item['instruments'] as $relation) {
            if (!is_array($relation) || array_keys($relation) !== [0, 1] ||
                !is_int($relation[0]) || $relation[0] <= 0 || !is_int($relation[1])) {
                throw new InvalidArgumentException(
                    'Each instrument must be [positive integer ID, integer priority]'
                );
            }
        }
    }

    private static function getInstrumentsFor($itemId)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT i.id, i.name, mi.priority
            FROM musician_instruments mi
            JOIN instruments i ON i.id = mi.instrument_id
            WHERE mi.musician_id = ?
        ");
        $stmt->execute([$itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
