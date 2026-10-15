<?php use Hospital\Core\Form; use Hospital\Models\Appointment; ?>
<p class="eyebrow">Administration</p><h1>Appointment management</h1>
<form method="get" action="/admin/appointments" class="card filters"><?php
Form::select('doctor_id', 'Doctor (blank = all)', array_column($doctors, 'name', 'id'), $filters, $errors, false);
Form::field('date', 'Date (blank = all)', $filters, $errors, 'date', false);
Form::select('status', 'Status (blank = all)', array_combine(Appointment::STATUSES,Appointment::STATUSES), $filters, $errors, false);
?><button>Apply filters</button><a href="/admin/appointments">Reset</a></form>
<div class="table-wrap"><table><thead><tr><th>ID / date</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($appointments as $item): ?><tr data-appointment-id="<?= (int) $item['id'] ?>"><td>#<?= (int) $item['id'] ?><br><?= $e($item['appointment_date']) ?><br><?= $e(substr($item['appointment_time'],0,5)) ?></td><td><?= $e($item['patient_name']) ?></td><td><?= $e($item['doctor_name']) ?></td><td><?= $e($item['reason']) ?></td><td><span class="badge <?= strtolower($item['status']) ?>" data-status-badge><?= $e($item['status']) ?></span></td><td>
<?php if ($item['status'] === 'Pending'): ?><button data-status="Confirmed" data-id="<?= (int) $item['id'] ?>">Confirm</button><?php endif; ?>
<details><summary>Cancel appointment</summary><form class="admin-cancel" data-id="<?= (int) $item['id'] ?>"><label for="cancel-<?= (int) $item['id'] ?>">Required cancellation reason</label><textarea id="cancel-<?= (int) $item['id'] ?>" name="reason" minlength="3" maxlength="4000" required></textarea><button class="danger">Cancel appointment</button></form></details>
</td></tr><?php endforeach; if (!$appointments): ?><tr><td colspan="6">No appointments match these filters.</td></tr><?php endif; ?></tbody></table></div>
