<?php
declare(strict_types=1);
namespace Hospital\Controllers;
use Hospital\Core\{Auth, Controller, Request};
use Hospital\Services\AppointmentStatusService;

final class AppointmentController extends Controller
{
    public function cancel(): void
    {
        $actor = Auth::requireRole('patient');
        AppointmentStatusService::update($actor, Request::id(Request::body()['appointment_id'] ?? null), 'Cancelled');
        $this->json(['ok' => true, 'new_status' => 'Cancelled']);
    }

    public function update(string $id): void
    {
        $actor = Auth::requireRole('doctor','admin'); $body = Request::body();
        $status = Request::text($body, 'status');
        AppointmentStatusService::update($actor, Request::id($id), $status, Request::text($body, 'reason'));
        $this->json(['ok' => true, 'new_status' => $status]);
    }
}
