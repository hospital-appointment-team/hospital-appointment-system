<?php
declare(strict_types=1);
namespace Hospital\Controllers;
use Hospital\Core\{Auth, Controller, HttpException, Request};
use Hospital\Models\Model;

final class SpecializationController extends Controller
{
    public function index(): void
    {
        Auth::requireRole('admin'); $errors = []; $data = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = Request::body(); $action = Request::text($body, 'action');
            if ($action === 'delete') {
                $id = Request::id($body['id'] ?? null);
                try {
                    $statement = Model::query('DELETE FROM specializations WHERE id=?', [$id]);
                    if ($statement->rowCount() === 0) throw new HttpException(404, 'Specialization not found.');
                    $this->redirect('/admin/specializations', 'Specialization deleted.');
                } catch (\PDOException $exception) {
                    if ($exception->getCode() !== '23000') throw $exception;
                    $errors['name'] = 'Cannot delete: doctors are assigned to this specialization.';
                }
            } elseif (in_array($action, ['create', 'edit'], true)) {
                $data['name'] = Request::text($body, 'name'); $data['id'] = $action === 'edit' ? Request::id($body['id'] ?? null) : '';
                if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 100) $errors['name'] = 'Use 2–100 characters.';
                if (!$errors) {
                    try {
                        if ($action === 'create') Model::query('INSERT INTO specializations (name) VALUES (?)', [$data['name']]);
                        else {
                            if (!Model::query('SELECT id FROM specializations WHERE id=?', [$data['id']])->fetch()) throw new HttpException(404, 'Specialization not found.');
                            Model::query('UPDATE specializations SET name=? WHERE id=?', [$data['name'], $data['id']]);
                        }
                        $this->redirect('/admin/specializations', 'Specialization saved.');
                    } catch (\PDOException $exception) {
                        if ($exception->getCode() !== '23000') throw $exception;
                        $errors['name'] = 'That specialization already exists.';
                    }
                }
            } else throw new HttpException(422, 'Invalid action.');
        } elseif (isset($_GET['edit'])) {
            $data = Model::query('SELECT * FROM specializations WHERE id=?', [Request::id($_GET['edit'])])->fetch();
            if (!$data) throw new HttpException(404, 'Specialization not found.');
        }
        $items = Model::query('SELECT s.*,COUNT(d.id) AS doctor_count FROM specializations s LEFT JOIN doctors d ON d.specialization_id=s.id GROUP BY s.id,s.name ORDER BY s.name')->fetchAll();
        $this->view('admin/specializations', compact('data', 'errors', 'items') + ['title' => 'Specializations'], $errors ? 422 : 200);
    }
}
