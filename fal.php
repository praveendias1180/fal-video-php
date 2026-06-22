<?php
/**
 * Minimal fal.ai client for PHP (no SDK required — just curl + json).
 *
 * fal.ai runs generative models behind an async "queue" API:
 *   1. POST your input  -> get back a request_id + status/response URLs
 *   2. Poll the status   -> IN_QUEUE / IN_PROGRESS / COMPLETED
 *   3. GET the response  -> the model output (e.g. a video URL)
 *
 * Video models take anywhere from ~20s to a few minutes, so the queue
 * API is the right tool — never block on a single synchronous request.
 *
 * Docs: https://docs.fal.ai/model-endpoints/queue
 */

declare(strict_types=1);

class FalException extends RuntimeException {}

final class FalClient
{
    private string $key;
    private string $base = 'https://queue.fal.run';

    public function __construct(?string $key = null)
    {
        $key = $key ?? getenv('FAL_KEY') ?: null;
        if (!$key) {
            throw new FalException(
                "FAL_KEY not set. Get a key at https://fal.ai/dashboard/keys then:\n" .
                "  export FAL_KEY=your-key-here"
            );
        }
        $this->key = $key;
    }

    /**
     * Submit a job, poll until done, and return the decoded result.
     *
     * @param string   $model    e.g. "fal-ai/ltx-video"
     * @param array    $input    model-specific input (prompt, image_url, ...)
     * @param ?callable $onUpdate called with each status string while polling
     */
    public function run(string $model, array $input, ?callable $onUpdate = null): array
    {
        $submit = $this->request('POST', "{$this->base}/{$model}", $input);

        $requestId  = $submit['request_id']  ?? null;
        $statusUrl  = $submit['status_url']  ?? null;
        $responseUrl = $submit['response_url'] ?? null;
        if (!$requestId || !$statusUrl || !$responseUrl) {
            throw new FalException("Unexpected submit response: " . json_encode($submit));
        }

        $last = null;
        while (true) {
            $status = $this->request('GET', $statusUrl);
            $state  = $status['status'] ?? 'UNKNOWN';

            if ($onUpdate && $state !== $last) {
                $extra = isset($status['queue_position'])
                    ? " (queue position {$status['queue_position']})" : '';
                $onUpdate($state . $extra);
                $last = $state;
            }

            if ($state === 'COMPLETED') {
                return $this->request('GET', $responseUrl);
            }
            if (!in_array($state, ['IN_QUEUE', 'IN_PROGRESS'], true)) {
                throw new FalException("Job failed with status '{$state}': " . json_encode($status));
            }
            sleep(2);
        }
    }

    /** Low-level HTTP helper. Returns the decoded JSON body. */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        $headers = [
            "Authorization: Key {$this->key}",
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw  = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new FalException("curl error: {$err}");
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true) ?? [];
        if ($code >= 400) {
            $detail = $decoded['detail'] ?? $raw;
            throw new FalException("HTTP {$code} from fal: " . (is_string($detail) ? $detail : json_encode($detail)));
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Turn a local image file into a data: URI usable as an `image_url`.
     * Handy for image-to-video without uploading to external storage first.
     */
    public static function fileToDataUri(string $path): string
    {
        if (!is_file($path)) {
            throw new FalException("Image not found: {$path}");
        }
        $mime = mime_content_type($path) ?: 'image/png';
        return "data:{$mime};base64," . base64_encode((string) file_get_contents($path));
    }
}
