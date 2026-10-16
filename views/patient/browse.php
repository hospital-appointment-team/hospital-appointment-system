<p class="eyebrow">Find your care team</p><h1>Choose a specialist</h1><p>Explore available doctors and choose a time that works for you.</p>
<form id="doctor-filter" class="card filters" method="get" action="/patient"><div class="field"><label for="specialization_id">Specialization</label><select name="specialization_id" id="specialization_id"><option value="">All specializations</option>
<?php foreach ($specializations as $specialization): ?><option value="<?= (int) $specialization['id'] ?>" <?= (string) ($_GET['specialization_id'] ?? '') === (string) $specialization['id'] ? 'selected' : '' ?>><?= $e($specialization['name']) ?></option><?php endforeach; ?>
</select></div><button>Filter doctors</button></form>
<div class="cards" id="doctor-cards" aria-live="polite"><?php foreach ($doctors as $doctor): ?>
<article class="card doctor-card"><?php if ($doctor['photo_path']): ?><img class="avatar" src="<?= $e($doctor['photo_path']) ?>" alt="<?= $e($doctor['name']) ?>"><?php endif; ?><h2><?= $e($doctor['name']) ?></h2><p><?= $e($doctor['specialization']) ?></p><p class="fee">Consultation fee: <?= $e($doctor['consultation_fee']) ?></p><a class="button" href="/doctors/<?= (int) $doctor['id'] ?>">View availability</a></article>
<?php endforeach; if (!$doctors): ?><p class="card">No active doctors match this selection.</p><?php endif; ?></div>
