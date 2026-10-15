<?php use Hospital\Core\{Form, Security}; $e = [Security::class, 'escape']; ?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= $e(Security::csrfToken()) ?>">
<title><?= $e($title ?? 'Hospital Appointment System') ?> · CareConnect</title>
<link rel="stylesheet" href="/assets/css/app.css"><script src="/assets/js/app.js" defer></script>
</head><body><a class="skip-link" href="#main">Skip to content</a>
<header class="header"><a class="brand" href="/">✚ CareConnect</a><nav aria-label="Main navigation">
<?php if (!$currentUser): ?><a href="/login">Sign in</a><a href="/register">Create account</a>
<?php elseif ($currentUser['role'] === 'patient'): ?><a href="/patient">Find a doctor</a><a href="/patient/profile">Profile</a>
<?php elseif ($currentUser['role'] === 'doctor'): ?><a href="/doctor/dashboard">My schedule</a>
<?php else: ?><a href="/admin">Overview</a><a href="/admin/users">Accounts</a><a href="/admin/specializations">Specializations</a><a href="/admin/doctors">Doctors</a><?php endif; ?>
<?php if ($currentUser): ?><form method="post" action="/logout" class="inline"><?php Form::csrf(); ?><button class="secondary">Sign out</button></form><?php endif; ?>
</nav></header><main id="main" class="container">
<?php if ($flash): ?><p class="notice" role="status"><?= $e($flash) ?></p><?php endif; ?>
<div id="request-feedback" role="status" aria-live="polite"></div><?= $content ?>
</main><footer class="container muted">CareConnect · Hospital appointment portal</footer></body></html>
