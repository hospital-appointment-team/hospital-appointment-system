<?php use Hospital\Core\{Form, Security}; ?>
<section class="card narrow"><p class="eyebrow">Welcome back</p><h1>Sign in</h1><p>Patients, doctors, and administrators sign in here.</p>
<?php if ($errors): ?><p class="error" role="alert"><?= Security::escape($errors['login']) ?></p><?php endif; ?>
<form method="post" action="/login"><?php Form::csrf(); Form::field('email', 'Email', $data, $errors, 'email'); ?>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button>Sign in</button></form><p>New patient? <a href="/register">Create an account</a>.</p></section>
