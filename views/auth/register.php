<?php use Hospital\Core\{Form, Validation}; ?>
<section class="card narrow"><p class="eyebrow">Patient registration</p><h1>Create your account</h1><p>Already registered? <a href="/login">Sign in</a>.</p>
<form method="post" action="/register"><?php
Form::csrf(); Form::field('name', 'Full name', $data, $errors); Form::field('email', 'Email', $data, $errors, 'email');
Form::field('password', 'Password (8–72 bytes)', [], $errors, 'password'); Form::field('dob', 'Date of birth', $data, $errors, 'date');
Form::select('blood_group', 'Blood group', array_combine(Validation::BLOOD_GROUPS, Validation::BLOOD_GROUPS), $data, $errors);
Form::field('phone', 'Phone', $data, $errors, 'tel');
?><button>Create patient account</button></form></section>
