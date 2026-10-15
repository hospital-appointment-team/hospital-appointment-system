<?php
declare(strict_types=1);
namespace Hospital\Core;

final class Request
{
    private static ?array $body = null;

    public static function body(): array
    {
        if (self::$body !== null) {
            return self::$body;
        }
        $type = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
        if ($type !== 'application/json') {
            return self::$body = $_POST;
        }
        try {
            $body = json_decode(file_get_contents('php://input'), false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'Invalid JSON body.');
        }
        if (!$body instanceof \stdClass) {
            throw new HttpException(400, 'Expected a JSON object.');
        }
        return self::$body = (array) $body;
    }

    public static function text(array $data, string $key): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
    }

    public static function id(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) throw new HttpException(422, 'A valid record ID is required.');
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(422, 'A valid record ID is required.');
        }
        return $id;
    }

    public static function csrf(): void
    {
        $body = self::body();
        if (!Security::validCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['_csrf'] ?? null)) {
            throw new HttpException(419, 'Your session expired. Reload the page and try again.');
        }
    }
}
