<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/JsonBody.php';

abstract class AbstractCRUDController
{
    protected static string $table;
    protected static array $fields;

    public static function create()
    {
        global $pdo;
        try {
            $data = JsonBody::read();
            foreach ($data as $item) {
                static::validateItem($item);
            }

            $pdo->beginTransaction();
            $values = [];
            $params = [];

            foreach ($data as $item) {
                $placeholders = [];
                foreach (static::$fields as $field) {
                    $placeholders[] = '?';
                    $params[] = $item[$field] ?? null;
                }
                $values[] = '(' . implode(',', $placeholders) . ')';
            }

            $sql = "INSERT INTO " . static::$table . " (" . implode(',', static::$fields) . ") VALUES " . implode(',', $values);
            $pdo->prepare($sql)->execute($params);

            $firstId = $pdo->lastInsertId();
            $ids = range($firstId, $firstId + count($data) - 1);
            $pdo->commit();

            echo json_encode(['ids' => $ids]);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::jsonError($e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            self::jsonError('Internal server error', 500);
        }
    }

    public static function read($id = null)
    {
        global $pdo;

        if ($id !== null) {
            $stmt = $pdo->prepare("SELECT * FROM " . static::$table . " WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            echo json_encode($result ?: []);
        } else {
            $stmt = $pdo->query("SELECT * FROM " . static::$table);
            echo json_encode($stmt->fetchAll());
        }
    }


    public static function update()
    {
        global $pdo;
        try {
            $data = JsonBody::read();
            foreach ($data as $item) {
                static::validateItem($item, true);
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
            }
            $pdo->commit();
            echo json_encode(['status' => 'updated']);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::jsonError($e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            self::jsonError('Internal server error', 500);
        }
    }

    public static function delete()
    {
        global $pdo;
        try {
            $data = JsonBody::read('ids');
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "DELETE FROM " . static::$table . " WHERE id IN ($placeholders)";
            $pdo->prepare($sql)->execute($data);
            $pdo->commit();
            echo json_encode(['status' => 'deleted']);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::jsonError($e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log((string) $e);
            self::jsonError('Internal server error', 500);
        }
    }

    protected static function validateItem(array $item, bool $update = false): void
    {
        if ($update && (!isset($item['id']) || !is_int($item['id']) || $item['id'] <= 0)) {
            throw new InvalidArgumentException('Item id must be a positive integer');
        }

        foreach (static::$fields as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && !is_scalar($item[$field])) {
                throw new InvalidArgumentException("Invalid type for $field");
            }
        }
    }

    protected static function jsonError($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
