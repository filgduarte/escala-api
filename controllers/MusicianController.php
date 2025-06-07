<?php
require_once __DIR__ . '/../config/database.php';

class MusicianController
{
    public static function create()
    {
        global $pdo;
        $data = json_decode(file_get_contents('php://input'), true);

        $pdo->beginTransaction();
        try {
            $ids = [];

            foreach ($data as $musician) {
                $stmt = $pdo->prepare("INSERT INTO musicians (name, birthday) VALUES (?, ?)");
                $stmt->execute([
                    $musician['name'],
                    $musician['birthday'] ?? null
                ]);
                $musicianId = $pdo->lastInsertId();
                $ids[] = $musicianId;

                foreach ($musician['instruments'] ?? [] as $relation) {
                    $stmtRel = $pdo->prepare("INSERT INTO musician_instruments (musician_id, instrument_id, priority) VALUES (?, ?, ?)");
                    $stmtRel->execute([$musicianId, $relation[0], $relation[1]]);
                }
            }

            $pdo->commit();
            echo json_encode(['ids' => $ids]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create musician(s)']);
        }
    }

    public static function read($id = null)
    {
        global $pdo;

        if ($id !== null) {
            $stmt = $pdo->prepare("SELECT * FROM musicians WHERE id = ?");
            $stmt->execute([$id]);
            $musician = $stmt->fetch();

            if ($musician) {
                $musician['instruments'] = self::getInstrumentsFor($musician['id']);
            }

            echo json_encode($musician ?: []);
        } else {
            $stmt = $pdo->query("SELECT * FROM musicians");
            $musicians = $stmt->fetchAll();

            foreach ($musicians as &$m) {
                $m['instruments'] = self::getInstrumentsFor($m['id']);
            }

            echo json_encode($musicians);
        }
    }

    public static function update()
    {
        global $pdo;
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No musicians provided']);
            return;
        }

        $pdo->beginTransaction();
        try {
            foreach ($data as $musician) {
                if (!isset($musician['id'])) continue;

                $fields = [];
                $params = [];

                if (isset($musician['name'])) {
                    $fields[] = 'name = ?';
                    $params[] = $musician['name'];
                }

                if (isset($musician['birthday'])) {
                    $fields[] = 'birthday = ?';
                    $params[] = $musician['birthday'];
                }

                if (!empty($fields)) {
                    $params[] = $musician['id'];
                    $sql = "UPDATE musicians SET " . implode(', ', $fields) . " WHERE id = ?";
                    $pdo->prepare($sql)->execute($params);
                }

                // UPSERT nas relações com instrumentos
                if (isset($musician['instruments'])) {
                    foreach ($musician['instruments'] as $relation) {
                        $stmtRel = $pdo->prepare("
                            INSERT INTO musician_instruments (musician_id, instrument_id, priority)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE priority = VALUES(priority)
                        ");
                        $stmtRel->execute([$musician['id'], $relation[0], $relation[1]]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'updated']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update musicians']);
        }
    }



    public static function delete()
    {
        global $pdo;
        $ids = json_decode(file_get_contents('php://input'), true);

        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $pdo->prepare("DELETE FROM musician_instruments WHERE musician_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM availabilities WHERE musician_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM musicians WHERE id = ?")->execute([$id]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'deleted']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete musician(s)']);
        }
    }

    public static function deleteInstrumentRelation()
    {
        global $pdo;
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['musician_id']) || !isset($data['instrument_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing musician_id or instrument_id']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM musician_instruments WHERE musician_id = ? AND instrument_id = ?");
        $stmt->execute([$data['musician_id'], $data['instrument_id']]);

        echo json_encode(['status' => 'relation removed']);
    }


    private static function getInstrumentsFor($musicianId)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT i.id, i.name, mi.priority
            FROM musician_instruments mi
            JOIN instruments i ON i.id = mi.instrument_id
            WHERE mi.musician_id = ?
        ");
        $stmt->execute([$musicianId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
