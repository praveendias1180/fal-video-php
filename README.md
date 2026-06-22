# fal.ai video experiments (PHP)

Minimal PHP scaffold for text-to-video and image-to-video on [fal.ai](https://fal.ai),
using the async **queue API** directly over curl — no SDK needed.

## Files

- `fal.php` — tiny `FalClient` (submit → poll → fetch result).
- `generate.php` — CLI for text-to-video and image-to-video.

## 1. Get an API key

Create one at <https://fal.ai/dashboard/keys>, then:

```bash
export FAL_KEY=your-key-here
```

(fal bills per generation — video models cost more than images. Check pricing on each model's page.)

## 2. Run it

```bash
# text-to-video
php generate.php "a red panda surfing a wave at sunset"

# image-to-video from a local file
php generate.php --image cat.jpg "the cat slowly turns its head"

# image-to-video from a remote URL
php generate.php --image-url https://example.com/cat.jpg "make it blink"

# choose a specific model
php generate.php --model fal-ai/kling-video/v2/master/text-to-video "neon tokyo street"

# custom output path
php generate.php --out panda.mp4 "a red panda surfing"
```

The video is downloaded to `out.mp4` by default. Status updates print to stderr.

## Picking a model

Defaults: `fal-ai/ltx-video` (text) and `fal-ai/ltx-video/image-to-video` (image) — fast and cheap.
Browse current model IDs and their exact input schemas at
<https://fal.ai/models?categories=text-to-video>. Popular options:

| Model id | Notes |
|---|---|
| `fal-ai/ltx-video` | Fast, low cost — good default |
| `fal-ai/kling-video/v2/master/text-to-video` | Higher quality, slower, pricier |
| `fal-ai/minimax-video-01` | Strong motion |
| `fal-ai/veo3` | High quality, premium pricing |

Different models accept different extra inputs (duration, aspect ratio, seed, etc.).
Add them in `generate.php` where the `$input` array is built.

## How the queue API works

1. `POST https://queue.fal.run/{model}` with your JSON input → returns `request_id` + URLs.
2. Poll `status_url` until `COMPLETED` (`IN_QUEUE` → `IN_PROGRESS` → `COMPLETED`).
3. `GET response_url` → the model output (a video URL).

See `fal.php` for the implementation and <https://docs.fal.ai/model-endpoints/queue> for details.
