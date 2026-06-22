<?php
/**
 * Generate a video from text or an image using fal.ai.
 *
 *   export FAL_KEY=your-key
 *
 *   # text-to-video (default model)
 *   php generate.php "a red panda surfing a wave at sunset"
 *
 *   # image-to-video from a local file
 *   php generate.php --image cat.jpg "the cat slowly turns its head"
 *
 *   # image-to-video from a remote URL
 *   php generate.php --image-url https://example.com/cat.jpg "make it blink"
 *
 *   # pick a specific model (see https://fal.ai/models?categories=text-to-video)
 *   php generate.php --model fal-ai/kling-video/v2/master/text-to-video "neon city"
 *
 * Options:
 *   --image <path>     local image -> image-to-video (sent as a data URI)
 *   --image-url <url>  remote image -> image-to-video
 *   --model <id>       override the model
 *   --out <path>       where to save the downloaded video (default: out.mp4)
 */

declare(strict_types=1);
require __DIR__ . '/fal.php';

// Sensible, currently-available defaults. Override with --model.
// Verify/browse current model IDs at https://fal.ai/models
const DEFAULT_T2V = 'fal-ai/ltx-video';
const DEFAULT_I2V = 'fal-ai/ltx-video/image-to-video';

$opts = [
    'image'     => null,
    'image-url' => null,
    'model'     => null,
    'out'       => 'out.mp4',
];

$prompt = null;
$argv_ = array_slice($argv, 1);
for ($i = 0; $i < count($argv_); $i++) {
    $a = $argv_[$i];
    if (str_starts_with($a, '--')) {
        $name = substr($a, 2);
        if (!array_key_exists($name, $opts)) {
            fwrite(STDERR, "Unknown option: --{$name}\n");
            exit(1);
        }
        $opts[$name] = $argv_[++$i] ?? null;
    } else {
        $prompt = $prompt === null ? $a : $prompt . ' ' . $a;
    }
}

if ($prompt === null || $prompt === '') {
    fwrite(STDERR, "Usage: php generate.php [--image PATH | --image-url URL] \"your prompt\"\n");
    exit(1);
}

try {
    $client = new FalClient();

    // Build input + choose model based on whether an image was supplied.
    $isImage = $opts['image'] !== null || $opts['image-url'] !== null;
    $input = ['prompt' => $prompt];

    if ($isImage) {
        $input['image_url'] = $opts['image-url']
            ?? FalClient::fileToDataUri($opts['image']);
        $model = $opts['model'] ?? DEFAULT_I2V;
    } else {
        $model = $opts['model'] ?? DEFAULT_T2V;
    }

    fwrite(STDERR, "Model:  {$model}\n");
    fwrite(STDERR, "Prompt: {$prompt}\n");

    $result = $client->run($model, $input, function (string $state) {
        fwrite(STDERR, "  status: {$state}\n");
    });

    // Most video models return { "video": { "url": "..." } }; be liberal.
    $url = $result['video']['url']
        ?? $result['video_url']
        ?? ($result['videos'][0]['url'] ?? null);

    if (!$url) {
        fwrite(STDERR, "Done, but couldn't find a video URL. Full response:\n");
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
        exit(0);
    }

    fwrite(STDERR, "Downloading {$url}\n");
    $video = file_get_contents($url);
    if ($video === false) {
        fwrite(STDERR, "Could not download. Video URL: {$url}\n");
        exit(1);
    }
    file_put_contents($opts['out'], $video);
    fwrite(STDERR, "Saved -> {$opts['out']} (" . number_format(strlen($video)) . " bytes)\n");
} catch (FalException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
