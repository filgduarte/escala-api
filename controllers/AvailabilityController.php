<?php
require_once __DIR__ . '/../core/AbstractCrudController.php';

class AvailabilityController extends AbstractCrudController
{
    protected static string $table = 'availabilities';
    protected static array $fields = ['musician_id', 'date'];

    public static function readByDate(string $date)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM availabilities WHERE date = ?");
        $stmt->execute([$date]);

        echo json_encode($stmt->fetchAll());
    }
}
