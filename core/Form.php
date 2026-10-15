<?php
declare(strict_types=1);
namespace Hospital\Core;

final class Form
{
    public static function csrf(): void
    {
        echo '<input type="hidden" name="_csrf" value="' . Security::escape(Security::csrfToken()) . '">';
    }

    public static function field(string $name, string $label, array $data, array $errors, string $type = 'text', bool $required = true): void
    {
        $e = [Security::class, 'escape'];
        $value = $type === 'password' || $type === 'file' ? '' : ($data[$name] ?? '');
        echo '<div class="field"><label for="' . $e($name) . '">' . $e($label) . '</label>';
        $attributes = ' id="' . $e($name) . '" name="' . $e($name) . '"' . ($required ? ' required' : '')
            . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . $e($name) . '"' : '');
        if ($type === 'textarea') echo '<textarea rows="4" maxlength="4000"' . $attributes . '>' . $e($value) . '</textarea>';
        else echo '<input type="' . $e($type) . '" value="' . $e($value) . '"' . $attributes
            . ($type === 'file' ? ' accept="image/jpeg,image/png"' : '')
            . ($type === 'password' ? ' autocomplete="' . ($name === 'current_password' ? 'current-password' : 'new-password') . '"' : '') . '>';
        self::error($name, $errors);
        echo '</div>';
    }

    public static function select(string $name, string $label, array $choices, array $data, array $errors, bool $required = true): void
    {
        $e = [Security::class, 'escape'];
        echo '<div class="field"><label for="' . $e($name) . '">' . $e($label) . '</label><select id="' . $e($name) . '" name="' . $e($name) . '"'
            . ($required ? ' required' : '') . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . $e($name) . '"' : '') . '><option value="">Select…</option>';
        foreach ($choices as $value => $text) echo '<option value="' . $e($value) . '"' . ((string) ($data[$name] ?? '') === (string) $value ? ' selected' : '') . '>' . $e($text) . '</option>';
        echo '</select>'; self::error($name, $errors); echo '</div>';
    }

    public static function error(string $name, array $errors): void
    {
        if (isset($errors[$name])) echo '<p class="error" id="error-' . Security::escape($name) . '">' . Security::escape($errors[$name]) . '</p>';
    }
}
