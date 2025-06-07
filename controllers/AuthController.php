<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

class AuthController {
    public static function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = $data['user'];
        $pass = $data['pass'];

        $expectedUser = getenv('AUTH_USER');
        $expectedPass = getenv('AUTH_PASS');

        if ($user === $expectedUser && $pass === $expectedPass) {
            $token = Auth::generateToken(1);
            echo json_encode(['token' => $token]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }
}
