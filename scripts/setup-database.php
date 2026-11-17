<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$usage = "Uso: php scripts/setup-database.php [--env=caminho/do/arquivo]\n";

if (($argv[1] ?? null) === '--help' && $argc === 2) {
    fwrite(STDOUT, $usage);
    exit(0);
}

try {
    if ($argc > 2 || ($argc === 2 && !str_starts_with($argv[1], '--env='))) {
        throw new InvalidArgumentException(trim($usage));
    }

    $envPath = $argc === 2 ? substr($argv[1], 6) : __DIR__ . '/../.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new RuntimeException('Arquivo de ambiente nao encontrado ou sem leitura. Use .env.example como modelo.');
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('Nao foi possivel ler o arquivo de ambiente.');
    }

    $config = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'] as $key) {
        if (!array_key_exists($key, $config) || ($key !== 'DB_PASS' && $config[$key] === '')) {
            throw new InvalidArgumentException("Configuracao obrigatoria ausente: $key");
        }
    }

    if (!preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $config['DB_NAME'])) {
        throw new InvalidArgumentException('DB_NAME deve ter de 1 a 64 letras, numeros, underscores ou hifens.');
    }
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $config['DB_CHARSET'])) {
        throw new InvalidArgumentException('DB_CHARSET invalido. Use, por exemplo, utf8mb4.');
    }
    if (strpbrk($config['DB_HOST'], ";\r\n\0") !== false) {
        throw new InvalidArgumentException('DB_HOST invalido. Configure a porta usando DB_PORT.');
    }

    $port = filter_var($config['DB_PORT'] ?? '3306', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new InvalidArgumentException('DB_PORT deve ser um inteiro entre 1 e 65535.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('A extensao PHP pdo_mysql precisa estar habilitada.');
    }

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('Nao foi possivel carregar database/schema.sql.');
    }

    // Connect without selecting a database, since it may not exist yet.
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['DB_HOST'], $port, $config['DB_CHARSET']);
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);

    $database = '`' . $config['DB_NAME'] . '`';
    $charset = '`' . $config['DB_CHARSET'] . '`';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET $charset");
    $pdo->exec("USE $database");

    // The initial schema contains only simple CREATE TABLE statements.
    // MySQL DDL commits implicitly; rerunning completes any missing tables.
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }

    fwrite(STDOUT, "Banco {$config['DB_NAME']} preparado com sucesso.\n");
    fwrite(STDOUT, "Tabelas: musicians, instruments, musician_instruments, availabilities.\n");
    exit(0);
} catch (PDOException $e) {
    $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();
    $driverCode = (int) ($e->errorInfo[1] ?? 0);
    $details = "SQLSTATE $sqlState, MySQL $driverCode";

    if (in_array($driverCode, [2002, 2003], true)) {
        fwrite(STDERR, "Nao foi possivel conectar ao MySQL em {$config['DB_HOST']}:$port ($details).\n");
        fwrite(STDERR, "Inicie o servidor MySQL/MariaDB e confira DB_HOST e DB_PORT no arquivo de ambiente.\n");
    } elseif ($driverCode === 1045) {
        fwrite(STDERR, "Autenticacao recusada pelo MySQL ($details). Confira DB_USER e DB_PASS no arquivo de ambiente.\n");
    } elseif ($driverCode === 1044) {
        fwrite(STDERR, "Acesso ao banco recusado pelo MySQL ($details). Confira as permissoes do usuario configurado.\n");
    } else {
        fwrite(STDERR, "Falha no MySQL ($details). Verifique as permissoes de criacao e a compatibilidade de tabelas existentes.\n");
    }
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
