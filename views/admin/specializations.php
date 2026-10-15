<?php use Hospital\Core\Form; ?>
<p class="eyebrow">Administration</p><h1>Specializations</h1>
<div class="split"><section class="card"><h2><?= empty($data['id']) ? 'Add' : 'Edit' ?> specialization</h2><form method="post" action="/admin/specializations"><?php Form::csrf(); ?>
<input type="hidden" name="action" value="<?= empty($data['id']) ? 'create' : 'edit' ?>"><input type="hidden" name="id" value="<?= $e($data['id'] ?? '') ?>">
<?php Form::field('name', 'Specialization name', $data, $errors); ?><button>Save specialization</button></form></section>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Doctors</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= $e($item['name']) ?></td><td><?= (int) $item['doctor_count'] ?></td><td><a href="/admin/specializations?edit=<?= (int) $item['id'] ?>">Edit</a> <form class="inline" method="post" action="/admin/specializations"><?php Form::csrf(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="danger" <?= $item['doctor_count'] ? 'disabled' : '' ?>>Delete</button></form></td></tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="3">No specializations yet.</td></tr><?php endif; ?></tbody></table></div></div>
