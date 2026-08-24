<?php
/**
 * Tiny shared helpers for the gallery JSON API.
 */

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(200);
    exit;
}

if (!function_exists('json_response')) {
    function json_response($data, int $code = 200): void {
        if (!headers_sent()) {
            http_response_code($code);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($status, $data = null, $message = '', $statusCode = 200) {
        json_response([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}

if (!function_exists('read_json_body')) {
    function read_json_body(): array {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('require_admin')) {
    /**
     * Admins are role_id <= 2 (matches the check used on the public page).
     * Adjust the threshold here if your roles differ.
     */
    function require_admin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $loggedIn = !empty($_SESSION['user_id']);
        $isAdmin  = isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] <= 2;
        if (!$loggedIn || !$isAdmin) {
            json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }
}

if (!function_exists('require_method')) {
    function require_method(string $method): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
            json_response(['success' => false, 'message' => 'Method not allowed'], 405);
        }
    }
}

if (!function_exists('shape_gallery_item')) {
    /** Whitelist the fields we expose publicly (no created_by, no internal joins). */
    function shape_gallery_item(array $i): array {
        return [
            'id'               => (int) ($i['id'] ?? 0),
            'title'            => $i['title'] ?? '',
            'description'      => $i['description'] ?? null,
            'media_type'       => $i['media_type'] ?? 'image',
            'file_path'        => $i['file_path'] ?? null,
            'thumbnail_path'   => $i['thumbnail_path'] ?? null,
            'video_url'        => $i['video_url'] ?? null,
            'video_embed_code' => $i['video_embed_code'] ?? null,
            'category'         => $i['category'] ?? null,
            'tags'             => $i['tags'] ?? null,
            'is_featured'      => !empty($i['is_featured']),
            'view_count'       => (int) ($i['view_count'] ?? 0),
        ];
    }
}