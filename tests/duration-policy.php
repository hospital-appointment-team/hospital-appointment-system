<?php
declare(strict_types=1);

return static function (callable $check): void {
    $policy = \Hospital\Services\DurationPolicy::class;
    $empty = $policy::evaluate([], 30, false, false);
    $check($empty === [], 'Duration can be configured before any reservation');
    $baseline = $policy::evaluate([], 30, false, true);
    $check($baseline['minutes'] === 30, 'First reservation freezes the configured duration');
    $check($policy::evaluate($baseline, 30, true, false) === $baseline, 'Existing appointments accept the original duration');
    $check($policy::evaluate($baseline, 30, false, false) === $baseline, 'Baseline stays frozen even if rows are subsequently removed');
    foreach ([[15, true], [60, true], [15, false]] as [$minutes, $exists]) {
        try { $policy::evaluate($baseline, $minutes, $exists, false); $check(false, 'Duration changes fail closed'); }
        catch (\Hospital\Core\HttpException $exception) { $check($exception->status === 503, 'Duration changes fail closed'); }
    }
    try { $policy::evaluate([], 30, true, true); $check(false, 'Legacy appointments require deliberate baseline recovery'); }
    catch (\Hospital\Core\HttpException $exception) { $check($exception->status === 503, 'Legacy appointments require deliberate baseline recovery'); }
    $check(\Hospital\Services\AvailabilityService::overlaps('09:00', '09:15', $baseline['minutes']), 'Original 30-minute appointment still blocks 09:15');
    $check(!\Hospital\Services\AvailabilityService::overlaps('09:00', '09:30', $baseline['minutes']), 'Adjacent original-duration slots remain bookable');
};
