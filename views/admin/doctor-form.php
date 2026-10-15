<?php use Hospital\Core\{Form, Validation}; ?>
<p class="eyebrow">Doctor management</p><h1><?= $doctor ? 'Edit doctor' : 'Add doctor' ?></h1>
<section class="card narrow"><form method="post" enctype="multipart/form-data" action="<?= $doctor ? '/admin/doctors/' . (int) $doctor['id'] . '/edit' : '/admin/doctors/create' ?>"><?php
Form::csrf(); Form::field('name', 'Doctor name', $data, $errors); Form::field('email', 'Email', $data, $errors, 'email');
Form::field('password', $doctor ? 'New password (leave blank to keep)' : 'Password', [], $errors, 'password', !$doctor);
Form::select('specialization_id', 'Specialization', array_column($specializations, 'name', 'id'), $data, $errors);
Form::field('bio', 'Biography', $data, $errors, 'textarea'); Form::field('consultation_fee', 'Consultation fee', $data, $errors);
if ($doctor && $doctor['photo_path']): ?><img class="avatar" src="<?= $e($doctor['photo_path']) ?>" alt="Current doctor photo"><?php endif;
Form::field('photo', 'JPEG/PNG photo, up to 2 MB', [], $errors, 'file', !$doctor);
?><fieldset><legend>Available weekdays</legend><?php foreach (Validation::WEEKDAYS as $day): ?><label class="checkbox"><input type="checkbox" name="available_days[]" value="<?= $day ?>" <?= in_array($day, explode(',', $data['available_days']), true) ? 'checked' : '' ?>><?= $day ?></label><?php endforeach; Form::error('available_days', $errors); ?></fieldset>
<button>Save doctor</button></form></section>
