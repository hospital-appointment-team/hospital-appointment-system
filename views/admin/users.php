<p class="eyebrow">Administration</p><h1>Account control</h1>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access</th><th>Action</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= $e($user['name']) ?></td><td><?= $e($user['email']) ?></td><td><?= $e($user['role']) ?></td><td data-active-label><?= $user['is_active'] ? 'Active' : 'Inactive' ?></td><td><button type="button" class="secondary" data-toggle-user="<?= (int) $user['id'] ?>" <?= (int) $user['id'] === (int) $currentUser['id'] ? 'disabled' : '' ?>><?= $user['is_active'] ? 'Deactivate' : 'Activate' ?></button></td></tr><?php endforeach; ?>
</tbody></table></div>
