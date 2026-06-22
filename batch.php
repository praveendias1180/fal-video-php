<?php
/**
 * Render a multi-shot storyboard with fal.ai.
 *
 * Reads storyboard.json, renders every shot (image-to-video by default,
 * reusing one reference image so the character stays consistent), saves
 * each clip into the out_dir, and writes a manifest.json.
 *
 *   php batch.php                  # uses storyboard.json
 *   php batch.php my-board.json
 *
 * Each shot in the JSON may provide a start image as either:
 *   "image":      local file path  -> sent as a data URI
 *   "image_url":  hosted URL        -> used directly
 *   "image_prompt": text            -> a FLUX image is generated first
 * ...plus "motion_prompt" (the action+camera) and optional "params".
 *
 * After it finishes, stitch the shots with:
 *   ffmpeg -f concat -safe 0 -i shots/concat.txt -c:v libx264 -pix_fmt yuv420p storyboard.mp4
 */

declare(strict_types=1);
require __DIR__ . '/fal.php';
FalClient::loadEnv();

const IMAGE_MODEL = 'fal-ai/flux/schnell';

$path = $argv[1] ?? __DIR__ . '/storyboard.json';
if (!is_file($path)) {
    fwrite(STDERR, "Storyboard not found: {$path}\n");
    exit(1);
}

$sb = json_decode((string) file_get_contents($path), true);
if (!is_array($sb) || empty($sb['shots'])) {
    fwrite(STDERR, "Invalid storyboard (need a 'shots' array).\n");
    exit(1);
}

$model   = $sb['model'] ?? 'fal-ai/kling-video/v2.6/pro/image-to-video';
$outDir  = ($sb['out_dir'] ?? 'shots');
$defParams = $sb['default_params'] ?? [];
if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create out_dir: {$outDir}\n");
    exit(1);
}

try {
    $client = new FalClient();
} catch (FalException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}

$manifest   = [];
$concatLines = [];
$shots = $sb['shots'];
$n = count($shots);

foreach (array_values($shots) as $i => $shot) {
    $id = $shot['id'] ?? sprintf('shot%02d', $i + 1);
    $tag = sprintf('[%d/%d %s]', $i + 1, $n, $id);
    fwrite(STDERR, "\n{$tag} starting\n");

    try {
        // 1. Resolve the start image (reuse / hosted / generate).
        if (!empty($shot['image_url'])) {
            $imageUrl = $shot['image_url'];
        } elseif (!empty($shot['image'])) {
            $imageUrl = FalClient::fileToDataUri($shot['image']);
            fwrite(STDERR, "{$tag} start image: {$shot['image']}\n");
        } elseif (!empty($shot['image_prompt'])) {
            fwrite(STDERR, "{$tag} generating start image (FLUX)\n");
            $img = $client->run(IMAGE_MODEL, [
                'prompt'     => $shot['image_prompt'],
                'image_size' => $shot['image_size'] ?? 'portrait_16_9',
            ]);
            $imageUrl = $img['images'][0]['url'] ?? null;
            if (!$imageUrl) {
                throw new FalException("FLUX returned no image");
            }
        } else {
            throw new FalException("shot has no image / image_url / image_prompt");
        }

        // 2. Animate it.
        $input = array_merge(
            ['prompt' => $shot['motion_prompt'] ?? '', 'image_url' => $imageUrl],
            $defParams,
            $shot['params'] ?? []
        );
        $result = $client->run($model, $input, function (string $s) use ($tag) {
            fwrite(STDERR, "{$tag} {$s}\n");
        });

        $url = $result['video']['url'] ?? $result['video_url']
            ?? ($result['videos'][0]['url'] ?? null);
        if (!$url) {
            throw new FalException("no video URL in response: " . json_encode($result));
        }

        // 3. Download.
        $file = "{$outDir}/{$id}.mp4";
        $bytes = file_get_contents($url);
        if ($bytes === false) {
            throw new FalException("could not download {$url}");
        }
        file_put_contents($file, $bytes);
        fwrite(STDERR, "{$tag} saved -> {$file} (" . number_format(strlen($bytes)) . " bytes)\n");

        $concatLines[] = "file '" . basename($file) . "'";
        $manifest[] = [
            'id'      => $id,
            'file'    => $file,
            'overlay' => $shot['overlay'] ?? null,
            'motion_prompt' => $shot['motion_prompt'] ?? null,
            'source_url' => $url,
        ];
    } catch (FalException $e) {
        fwrite(STDERR, "{$tag} FAILED: {$e->getMessage()}\n");
        $manifest[] = ['id' => $id, 'error' => $e->getMessage()];
    }
}

// Write manifest + an ffmpeg concat list.
file_put_contents("{$outDir}/manifest.json",
    json_encode(['project' => $sb['project'] ?? null, 'model' => $model, 'shots' => $manifest],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents("{$outDir}/concat.txt", implode("\n", $concatLines) . "\n");

$ok = count(array_filter($manifest, fn($m) => empty($m['error'])));
fwrite(STDERR, "\nDone: {$ok}/{$n} shots rendered into {$outDir}/\n");
fwrite(STDERR, "Stitch: ffmpeg -f concat -safe 0 -i {$outDir}/concat.txt -c:v libx264 -pix_fmt yuv420p storyboard.mp4\n");
