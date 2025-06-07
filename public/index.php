<?php
header('Content-Type: application/json');

$envPath = realpath(__DIR__ . '/../.env');
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;

        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Router.php';

// Controllers
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/MusicianController.php';
require_once __DIR__ . '/../controllers/InstrumentController.php';
require_once __DIR__ . '/../controllers/AvailabilityController.php';

// Auth
Router::add('POST', '/login', ['AuthController', 'login']);

// Musicians
Router::add('POST',   '/musicos',                 ['MusicianController', 'create'], auth: true);
Router::add('GET',    '/musicos',                 ['MusicianController', 'read'], auth: true);
Router::add('GET',    '/musicos/{id}',            ['MusicianController', 'read'], auth: true);
Router::add('PATCH',  '/musicos',                 ['MusicianController', 'update'], auth: true);
Router::add('DELETE', '/musicos',                 ['MusicianController', 'delete'], auth: true);
Router::add('DELETE', '/musicos/instrumentos',    ['MusicianController', 'deleteInstrumentRelation'], auth: true);

// Instruments
Router::add('POST',   '/instrumentos',            ['InstrumentController', 'create'], auth: true);
Router::add('GET',    '/instrumentos',            ['InstrumentController', 'read'], auth: true);
Router::add('GET',    '/instrumentos/{id}',       ['InstrumentController', 'read'], auth: true);
Router::add('PATCH',  '/instrumentos',            ['InstrumentController', 'update'], auth: true);
Router::add('DELETE', '/instrumentos',            ['InstrumentController', 'delete'], auth: true);

// Availabilities
Router::add('POST',   '/disponibilidades',        ['AvailabilityController', 'create'],      auth: true);
Router::add('GET',    '/disponibilidades',        ['AvailabilityController', 'read'],        auth: true);
Router::add('GET',    '/disponibilidades/{id}',   ['AvailabilityController', 'read'],        auth: true);
Router::add('GET',    '/disponibilidades/{date}', ['AvailabilityController', 'readByDate'],  auth: true);
Router::add('DELETE', '/disponibilidades',        ['AvailabilityController', 'delete'],      auth: true);

// Dispatch
Router::dispatch();