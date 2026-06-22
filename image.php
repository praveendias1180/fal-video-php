<?php
/**
 * Generate an image with fal.ai (e.g. FLUX) — useful as the reference
 * "start frame" for image-to-video.
 *
 *   php image.php "a fit 38yo man in a home gym, morning light"
 *   php image.php --size portrait_16_9 --out ref.png "..."
 *   php image.php --model fal-ai/flux/dev --param num_inference_steps=28 "..."
 *
 * Options:
 *   --model <id>   default fal-ai/flux/schnell (fast + cheap, ~$0.003/img)
 *   --size <s>     image_size preset (square_hd, portrait_16_9, portrait_4_3,
 *                  landscape_16_9, ...) — default portrait_16_9 (vertical)
 *   --out <path>   where to save (default image.png)
 *   --param k=v    any extra model input
 *
 * Prints the hosted image URL to stdout (so you can pipe it into an
 * image-to-video step), and progress to stderr.
 */

declare(strict_types=1);
require __DIR__ . '/fal.php';
FalClient::loadEnv();

const DEFAULT_IMG_MODEL = 'fal-ai/flux/schnell';

$opts  = ['model' => null, 'size' => 'portrait_16_9', 'out' => 'image.png'];
$extra = [];
$prompt = null;

$av = array_slice($argv, 1);
for ($i = 0; $i < count($av); $i++) {
    $a = $av[$i];
    if ($a === '--param') {
        [$k, $v] = array_pad(explode('=', $av[++$i] ?? '', 2), 2, null);
        if ($k === null || $v === null) { fwrite(STDERR, "Bad --param\n"); exit(1); }
        $d = json_decode($v, true);
        $extra[$k] = $d === null && strtolower($v) !== 'null' ? $v : $d;
    } elseif (str_starts_with($a, '--')) {
        $name = substr($a, 2);
        if (!array_key_exists($name, $opts)) { fwrite(STDERR, "Unknown option: --{$name}\n"); exit(1); }
        $opts[$name] = $av[++$i] ?? null;
    } else {
        $prompt = $prompt === null ? $a : $prompt . ' ' . $a;
    }
}

if (!$prompt) { fwrite(STDERR, "Usage: php image.php [--size portrait_16_9] \"prompt\"\n"); exit(1); }

try {
    $client = new FalClient();
    $model  = $opts['model'] ?? DEFAULT_IMG_MODEL;
    $input  = array_merge(['prompt' => $prompt, 'image_size' => $opts['size']], $extra);

    fwrite(STDERR, "Model:  {$model}\nPrompt: {$prompt}\n");
    $result = $client->run($model, $input, fn(string $s) => fwrite(STDERR, "  status: {$s}\n"));

    $url = $result['images'][0]['url'] ?? $result['image']['url'] ?? null;
    if (!$url) {
        fwrite(STDERR, "No image URL in response:\n");
        echo json_encode($result, JSON_PRETTY_PRINT), "\n";
        exit(1);
    }

    $bytes = file_get_contents($url);
    if ($bytes !== false) {
        file_put_contents($opts['out'], $bytes);
        fwrite(STDERR, "Saved -> {$opts['out']} (" . number_format(strlen($bytes)) . " bytes)\n");
    }
    fwrite(STDERR, "Image URL (use as image-to-video input):\n");
    echo $url, "\n";   // stdout = the URL only, easy to capture
} catch (FalException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
