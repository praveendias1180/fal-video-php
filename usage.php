<?php
/**
 * Check your fal.ai credit balance and usage/cost — on demand.
 *
 * Reads FAL_ADMIN_KEY from .env (never hardcode it). These endpoints
 * require an ADMIN-scope key, which is different from FAL_KEY.
 *
 *   php usage.php                 # balance + usage for the last 7 days
 *   php usage.php --start 2026-06-01
 *   php usage.php --start 2026-06-01 --end 2026-06-22
 *   php usage.php --endpoint bytedance/seedance-2.0/fast/text-to-video
 *
 * Docs:
 *   https://fal.ai/docs/platform-apis/v1/account/billing
 *   https://fal.ai/docs/platform-apis/v1/models/usage
 */

declare(strict_types=1);
require __DIR__ . '/fal.php';
FalClient::loadEnv();

const API_BASE = 'https://api.fal.ai/v1';

$key = getenv('FAL_ADMIN_KEY') ?: null;
if (!$key) {
    fwrite(STDERR,
        "FAL_ADMIN_KEY not set. Add an admin-scope key to .env:\n" .
        "  FAL_ADMIN_KEY=your-admin-key\n" .
        "Create one at https://fal.ai/dashboard/keys (scope: Admin)\n");
    exit(1);
}

// --- args ---
$opts = ['start' => null, 'end' => null, 'endpoint' => null];
$av = array_slice($argv, 1);
for ($i = 0; $i < count($av); $i++) {
    $name = str_starts_with($av[$i], '--') ? substr($av[$i], 2) : null;
    if ($name === null || !array_key_exists($name, $opts)) {
        fwrite(STDERR, "Unknown option: {$av[$i]}\n");
        exit(1);
    }
    $opts[$name] = $av[++$i] ?? null;
}
$start = $opts['start'] ?? date('Y-m-d', time() - 7 * 86400);
$end   = $opts['end'];

// --- HTTP helper ---
$get = function (string $url) use ($key): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ["Authorization: Key {$key}", 'Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    if ($raw === false) {
        $e = curl_error($ch); curl_close($ch);
        fwrite(STDERR, "curl error: {$e}\n"); exit(1);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $data = json_decode($raw, true) ?? [];
    if ($code >= 400) {
        $msg = $data['detail'] ?? $data['error']['message'] ?? $raw;
        fwrite(STDERR, "HTTP {$code}: " . (is_string($msg) ? $msg : json_encode($msg)) . "\n");
        exit(1);
    }
    return is_array($data) ? $data : [];
};

// --- balance ---
$billing = $get(API_BASE . '/account/billing?expand=credits');
$bal = $billing['credits']['current_balance'] ?? null;
$cur = $billing['credits']['currency'] ?? 'USD';
echo "Account: " . ($billing['username'] ?? '?') . "\n";
echo "Balance: " . ($bal === null ? 'n/a' : number_format((float) $bal, 2) . " {$cur}") . "\n\n";

// --- usage ---
$q = ['start' => $start, 'expand' => 'summary'];
if ($end) {
    $q['end'] = $end;
}
$url = API_BASE . '/models/usage?' . http_build_query($q);
if ($opts['endpoint']) {
    $url .= '&endpoint_id=' . rawurlencode($opts['endpoint']);
}
$usage   = $get($url);
$summary = $usage['summary'] ?? [];

$range = "since {$start}" . ($end ? " until {$end}" : '');
if (!$summary) {
    echo "No usage {$range}.\n";
    exit(0);
}

echo "Usage {$range}:\n";
printf("  %-52s %12s %10s\n", 'endpoint', 'qty', 'cost');
echo "  " . str_repeat('-', 76) . "\n";
$total = 0.0;
foreach ($summary as $row) {
    $cost   = (float) ($row['cost'] ?? 0);
    $total += $cost;
    printf("  %-52s %8s %-3s %9s\n",
        substr((string) ($row['endpoint_id'] ?? '?'), 0, 52),
        rtrim(rtrim(number_format((float) ($row['quantity'] ?? 0), 2), '0'), '.'),
        (string) ($row['unit'] ?? ''),
        '$' . number_format($cost, 4));
}
echo "  " . str_repeat('-', 76) . "\n";
printf("  %-52s %12s %9s\n", 'TOTAL', '', '$' . number_format($total, 4));
