<?php use Hospital\Core\Form; ?>
<div class="page-heading"><div><p class="eyebrow">Administration</p><h1>Doctor management</h1></div><a class="button" href="/admin/doctors/create">Add doctor</a></div>
<div class="table-wrap"><table data-doctor-stats><thead><tr><th>Doctor</th><th>Specialization</th><th>Availability</th><th>Appointments</th><th>Access</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($doctors as $doctor): ?><tr><td><?= $e($doctor['name']) ?></td><td><?= $e($doctor['specialization']) ?></td><td><?= $e($doctor['available_days'] ?: 'No days set') ?></td><td data-stat-id="<?= (int) $doctor['id'] ?>">Loading…</td><td><?= $doctor['is_active'] ? 'Active' : 'Inactive' ?></td><td><a href="/admin/doctors/<?= (int) $doctor['id'] ?>/edit">Edit</a>
<?php if ($doctor['is_active']): ?><form method="post" action="/admin/doctors/<?= (int) $doctor['id'] ?>/deactivate" class="inline"><?php Form::csrf(); ?><button class="danger">Deactivate</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$doctors): ?><tr><td colspan="6">No doctors yet. Add a specialization, then a doctor.</td></tr><?php endif; ?></tbody></table></div>
