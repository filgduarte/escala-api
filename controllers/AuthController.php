<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/JsonBody.php';

class AuthController {
    public static function login() {
        try {
            $data = JsonBody::read('object');
            foreach (['user', 'pass'] as $field) {
                if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("$field is required and must be a string");
                }
            }

            $expectedUser = getenv('AUTH_USER');
            $expectedPass = getenv('AUTH_PASS');

            if ($data['user'] === $expectedUser && $data['pass'] === $expectedPass) {
                $token = Auth::generateToken(1);
                echo json_encode(['token' => $token]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
