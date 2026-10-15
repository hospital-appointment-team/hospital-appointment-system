<?php
declare(strict_types=1);

// Uses the actual renderer/templates, an anonymous session, and no database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$standalone = !isset($check);
if ($standalone) {
    putenv('APP_ENV=local'); putenv('SESSION_SECURE=false');
    require dirname(__DIR__) . '/config/bootstrap.php';
    $checks = 0; $failures = [];
    $check = static function (bool $result, string $description) use (&$checks, &$failures): void {
        $checks++;
        if (!$result) $failures[] = $description;
    };
}
\Hospital\Core\Security::startSession();
unset($_SESSION['user_id']);
$renderer = new class extends \Hospital\Core\Controller {
    public function render(string $template, array $variables): string
    {
        ob_start();
        try { $this->view($template, $variables); return ob_get_clean(); }
        catch (\Throwable $exception) { ob_end_clean(); throw $exception; }
    }
};
$profile = ['name' => 'Patient <Name>', 'phone' => '+8801700000000', 'dob' => '1990-02-03'];
$html = $renderer->render('patient/profile', ['data' => $profile, 'errors' => [], 'upcoming' => 2, 'title' => 'Profile']);
$check(str_contains($html, 'value="Patient &lt;Name&gt;"'), 'Profile renders and escapes the actual patient name');
$check(str_contains($html, 'value="+8801700000000"') && str_contains($html, 'value="1990-02-03"'), 'Profile renders phone and DOB');
$doctor = ['id' => 45, 'name' => 'Doctor Example', 'email' => 'doctor@example.test', 'bio' => 'Clinical <experience>', 'consultation_fee' => '450.00', 'specialization_id' => 7, 'available_days' => 'Monday,Wednesday', 'photo_path' => null];
$html = $renderer->render('admin/doctor-form', ['data' => $doctor, 'doctor' => $doctor, 'errors' => [], 'specializations' => [['id' => 7, 'name' => 'Cardiology']], 'title' => 'Edit doctor']);
$check(str_contains($html, 'value="Doctor Example"') && str_contains($html, 'value="doctor@example.test"'), 'Doctor form prefills name and email');
$check(str_contains($html, 'Clinical &lt;experience&gt;') && str_contains($html, 'value="450.00"'), 'Doctor form prefills escaped bio and fee');
$check(str_contains($html, '<option value="7" selected>'), 'Doctor specialization is preselected');
$check(str_contains($html, 'value="Monday" checked') && str_contains($html, 'value="Wednesday" checked') && !str_contains($html, 'value="Tuesday" checked'), 'Doctor weekday checkboxes preserve availability');
$submitted = ['name' => 'Submitted & retained', 'phone' => 'invalid-phone', 'dob' => '1991-03-04'];
$html = $renderer->render('patient/profile', ['data' => $submitted, 'errors' => ['phone' => 'Enter a valid phone.'], 'upcoming' => 0, 'title' => 'Profile']);
$check(str_contains($html, 'value="Submitted &amp; retained"') && str_contains($html, 'value="invalid-phone"'), 'Validation recovery preserves submitted values');
$check(str_contains($html, 'Enter a valid phone.') && str_contains($html, 'aria-invalid="true"'), 'Validation recovery renders the inline error');
$html = $renderer->render('admin/specializations', ['data' => ['id' => 7, 'name' => 'Cardiology'], 'errors' => [], 'items' => [], 'title' => 'Edit specialization']);
$check(str_contains($html, 'name="action" value="edit"') && str_contains($html, 'name="id" value="7"'), 'Specialization edit preserves action and ID');
$check(!str_contains($html, 'name="action" value="create"') && str_contains($html, 'value="Cardiology"'), 'Specialization edit never becomes a create form');
$html = $renderer->render('patient/profile', ['data' => $profile, 'errors' => [], 'upcoming' => 0, 'title' => 'Profile', 'template' => 'missing-template', 'e' => 'invalid-callable', 'currentUser' => ['role' => 'admin']]);
$check(str_contains($html, 'Create account') && str_contains($html, 'value="Patient &lt;Name&gt;"'), 'Renderer locals remain protected from extracted keys');
if ($standalone) {
    session_destroy();
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    echo "$checks rendering checks; " . count($failures) . " failures\n";
    exit($failures ? 1 : 0);
}
