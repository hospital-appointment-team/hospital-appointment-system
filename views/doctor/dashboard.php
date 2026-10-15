<p class="eyebrow">Your care schedule</p><h1>Today's appointments</h1><p><?= $e(date('l, F j, Y')) ?></p>
<div class="table-wrap"><table><thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($appointments as $item): ?><tr data-appointment-id="<?= (int) $item['id'] ?>"><td><?= $e(substr($item['appointment_time'],0,5)) ?></td><td><?= $e($item['patient_name']) ?></td><td><?= $e($item['reason']) ?></td><td><span class="badge <?= strtolower($item['status']) ?>" data-status-badge><?= $e($item['status']) ?></span></td><td>
<?php if (in_array($item['status'], ['Pending','Confirmed'], true)): ?><button data-status="Completed" data-id="<?= (int) $item['id'] ?>">Mark Completed</button> <button class="secondary" data-status="No-Show" data-id="<?= (int) $item['id'] ?>">Mark No-Show</button><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$appointments): ?><tr><td colspan="5">No appointments today.</td></tr><?php endif; ?></tbody></table></div>
<h2>This week's schedule</h2><p>Monday–Friday. Select an appointment to expand its details.</p>
<?php if ($scheduleNotice): ?><p class="notice"><?= $e($scheduleNotice) ?> Existing appointments remain visible.</p><?php endif; ?>
<div class="weekly-scroll"><div class="weekly-grid">
<?php foreach ($days as $day): ?><div class="week-heading"><?= $e(date('D, M j',strtotime($day))) ?></div><?php endforeach; ?>
<?php foreach ($times as $time): foreach ($days as $day): ?><div class="week-cell"><small><?= $e($time) ?></small>
<?php foreach ($schedule[$time][$day] ?? [] as $item): ?><details data-week-appointment="<?= (int) $item['id'] ?>" class="week-appointment <?= strtolower($item['status']) ?>"><summary><?= $e($item['patient_name']) ?> <span data-status-badge class="badge <?= strtolower($item['status']) ?>"><?= $e($item['status']) ?></span></summary><p>Appointment #<?= (int) $item['id'] ?></p><p><?= $e($item['reason']) ?></p></details><?php endforeach; ?></div><?php endforeach; endforeach; ?>
</div></div><?php if (!$times): ?><p class="card">No appointments this week. Time rows will appear when clinic hours are configured.</p><?php endif; ?>
