<?php
declare(strict_types=1);
namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller, HttpException, Request};
use Hospital\Models\{Doctor, Model};
use Hospital\Services\AvailabilityService;

final class BookingController extends Controller
{
    private function activeDoctor(string $id): array
    {
        $doctor = Doctor::find(Request::id($id));
        if (!$doctor || !$doctor['is_active']) throw new HttpException(404, 'Active doctor not found.');
        return $doctor;
    }

    public function browse(): void
    {
        Auth::requireRole('patient');
        $specializations = Model::query('SELECT * FROM specializations ORDER BY name')->fetchAll();
        $doctors = $this->doctors();
        $this->view('patient/browse', compact('specializations','doctors') + ['title' => 'Find a doctor']);
    }

    private function doctors(): array
    {
        $params = []; $where = ' WHERE u.is_active=1';
        if (isset($_GET['specialization_id']) && $_GET['specialization_id'] !== '') {
            $where .= ' AND d.specialization_id=?'; $params[] = Request::id($_GET['specialization_id']);
        }
        return Doctor::query(Doctor::SELECT . $where . ' ORDER BY u.name,d.id', $params)->fetchAll();
    }

    public function list(): void
    {
        Auth::requireRole('patient');
        $doctors = array_map(static fn (array $doctor): array => array_intersect_key($doctor, array_flip(['id','name','specialization','consultation_fee','photo_path'])), $this->doctors());
        $this->json(['ok' => true, 'doctors' => $doctors]);
    }

    public function profile(string $id): void
    {
        Auth::requireRole('patient'); $doctor = $this->activeDoctor($id);
        $dates = AvailabilityService::dates($doctor);
        $data = []; $errors = [];
        $this->view('patient/doctor', compact('doctor','dates','data','errors') + ['title' => $doctor['name']]);
    }

    public function slots(string $id): void
    {
        Auth::requireRole('patient');
        $this->json(AvailabilityService::slots($this->activeDoctor($id), Request::text($_GET, 'date')));
    }

}
