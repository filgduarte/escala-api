<?php
// Carrega variáveis do arquivo .env
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => '.env file not found']);
    exit;
}

if (!isset($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_CHARSET'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration not found in .env']);
    exit;
}

// Pega valores do .env
$host    = $_ENV['DB_HOST'];
$dbname  = $_ENV['DB_NAME'];
$user    = $_ENV['DB_USER'];
$pass    = $_ENV['DB_PASS'];
$charset = $_ENV['DB_CHARSET'];

// Conexão PDO
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
