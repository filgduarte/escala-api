<?php
require_once __DIR__ . '/../config/database.php';

abstract class AbstractCRUDController
{
    protected static string $table;
    protected static array $fields;

    public static function create()
    {
        global $pdo;
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) return self::jsonError('Empty input');

        $pdo->beginTransaction();
        try {
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
        } catch (Exception $e) {
            $pdo->rollBack();
            self::jsonError('Insert failed:' . $e, 500);
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
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) return self::jsonError('Empty input');

        $pdo->beginTransaction();
        try {
            foreach ($data as $item) {
                $setClause = [];
                $params = [];

                foreach (static::$fields as $field) {
                    $setClause[] = "$field = ?";
                    $params[] = $item[$field] ?? null;
                }

                $params[] = $item['id'];
                $sql = "UPDATE " . static::$table . " SET " . implode(', ', $setClause) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($params);
            }
            $pdo->commit();
            echo json_encode(['status' => 'updated']);
        } catch (Exception $e) {
            $pdo->rollBack();
            self::jsonError('Update failed', 500);
        }
    }

    public static function delete()
    {
        global $pdo;
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) return self::jsonError('Empty input');

        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "DELETE FROM " . static::$table . " WHERE id IN ($placeholders)";
            $pdo->prepare($sql)->execute($data);
            $pdo->commit();
            echo json_encode(['status' => 'deleted']);
        } catch (Exception $e) {
            $pdo->rollBack();
            self::jsonError('Delete failed', 500);
        }
    }

    protected static function jsonError($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
