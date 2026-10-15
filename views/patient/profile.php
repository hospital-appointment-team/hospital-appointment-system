<?php use Hospital\Core\Form; ?>
<div class="page-heading"><div><p class="eyebrow">Your account</p><h1>Patient profile</h1></div><div class="stat"><strong><?= $upcoming ?></strong><span>upcoming appointments</span></div></div>
<section class="card narrow"><form method="post" action="/patient/profile"><?php
Form::csrf(); Form::field('name', 'Full name', $data, $errors); Form::field('phone', 'Phone', $data, $errors, 'tel'); Form::field('dob', 'Date of birth', $data, $errors, 'date');
?><h2>Change password</h2><p>Leave these fields empty to keep your password.</p><?php
Form::field('current_password', 'Current password', [], $errors, 'password', false); Form::field('new_password', 'New password (8–72 bytes)', [], $errors, 'password', false);
?><button>Save profile</button></form></section>
