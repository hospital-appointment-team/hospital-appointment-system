<?php
declare(strict_types=1);
namespace Hospital\Services;
use Hospital\Core\{Environment, HttpException};

final class CancellationAudit
{
    public static function recordRequest(int $actorId, int $appointmentId, string $reason): void
    {
        $directory = BASE_PATH . '/storage/audit';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new HttpException(503, 'Cancellation audit storage is unavailable.');
        $file = fopen($directory . '/cancellations.jsonl', 'ab');
        if ($file === false) throw new HttpException(503, 'Cancellation audit storage is unavailable.');
        try {
            if (!flock($file, LOCK_EX)) throw new HttpException(503, 'Cancellation audit storage is busy.');
            // Request evidence is retained even if the subsequent database commit fails.
            // This is deliberately not a claim that the cancellation committed.
            $event = json_encode(['event' => 'admin_cancellation_requested','database' => Environment::get('DB_DATABASE'),'actor_id' => $actorId,'appointment_id' => $appointmentId,'reason' => $reason,'at' => date(DATE_ATOM)], JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($file, $event) !== strlen($event) || !fflush($file)) throw new HttpException(503, 'Cancellation reason could not be saved.');
            if (function_exists('fsync') && !fsync($file)) throw new HttpException(503, 'Cancellation reason could not be persisted.');
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
