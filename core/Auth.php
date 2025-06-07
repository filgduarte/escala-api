<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Auth {
    public static function generateToken(int $userId): string {
        $config = require __DIR__ . '/../config/jwt.php';

        $payload = [
            'sub' => $userId,
            'iss' => $config['issuer'],
            'iat' => time(),
            'exp' => time() + $config['expiration'],
        ];

        return JWT::encode($payload, $config['secret'], 'HS256');
    }

    public static function extractToken(): ?int {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) return null;

        $matches = [];
        if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) return null;

        $token = $matches[1];
        $config = require __DIR__ . '/../config/jwt.php';

        try {
            $decoded = JWT::decode($token, new Key($config['secret'], 'HS256'));
            return $decoded->sub ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}