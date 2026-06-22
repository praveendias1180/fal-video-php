# fal.ai experiment log

A running log of what we built and ran while learning fal.ai video generation.
Companion to `MODELS.md` (pricing) and `README.md` (how to run).

> Dates are 2026-06-22. Costs are actual, pulled from the fal usage API via `usage.php`.

---

## 1. Project scaffold (PHP, no SDK)

Built a minimal PHP client over fal's async **queue API** (submit → poll → fetch):

| File | Purpose |
|---|---|
| `fal.php` | `FalClient` — queue submit/poll/result, `.env` loader, image→data-URI helper |
| `generate.php` | CLI for text/image-to-video, with `--param key=value` passthrough |
| `image.php` | CLI for image generation (FLUX) — makes reference frames for i2v |
| `usage.php` | Account balance + per-model spend (uses admin key from `.env`) |
| `MODELS.md` | Pricing/comparison reference for the models we considered |
| `.env` | `FAL_KEY` + `FAL_ADMIN_KEY` (gitignored, never committed) |

How the queue API works: `POST https://queue.fal.run/{model}` → returns
`request_id` + status/response URLs → poll status until `COMPLETED` → GET the result.

---

## 2. Text-to-video runs

### Run 1 — first smoke test (LTX)
- **Model:** `fal-ai/ltx-video`
- **Prompt:** "a red panda surfing a wave at sunset, cinematic"
- **Output:** 768×512, 5s, no audio → `panda.mp4`
- **Cost:** ~$0.02 (flat per video)
- **Takeaway:** works end-to-end, but low quality — it's the budget model.

### Run 2 — quality upgrade (Seedance)
- **Model:** `bytedance/seedance-2.0/fast/text-to-video`
- **Params:** `resolution=720p`, `duration=5`
- **Prompt:** "a red panda surfing a wave at sunset, cinematic, highly detailed fur, dynamic motion"
- **Output:** 1280×720, 5s, **with AAC audio** → `panda_seedance.mp4`
- **Cost:** ~$1.22 (billed per 1k tokens at $0.0112)
- **Takeaway:** big jump over LTX (resolution + audio). ~60× the price, though.

---

## 3. Image-to-video workflow (the main lesson)

Goal: see how image-to-video keeps a **consistent character** across shots —
the key technique for a multi-shot ad. Workflow: **FLUX (make image) → Kling (animate it)**.

### Step A — generate the reference image
- **Model:** `fal-ai/flux/schnell`
- **Params:** `image_size=portrait_16_9` (vertical/mobile)
- **Prompt:**
  > A fit 38-year-old American man with short dark hair and light stubble,
  > wearing a heather-gray athletic t-shirt and black shorts, standing in a
  > sunlit home garage gym with a kettlebell and weight rack behind him,
  > confident relaxed posture, looking at the camera, cinematic commercial
  > photography, soft warm morning light, sharp focus, full body vertical composition
- **Output:** vertical image → `ref.png`
- **Cost:** ~$0.003 (1 megapixel)

### Step B — animate the SAME image two ways
- **Model:** `fal-ai/kling-video/v2.6/pro/image-to-video`
- **Params (both):** `image_url=<ref.png hosted URL>`, `duration=5`, `generate_audio=false`
- **Output (both):** 1080×1920 (true 9:16), 5s
- **Cost:** $0.70 total (10s @ $0.07/s — the no-audio tier; `generate_audio=false` worked)

| Shot | Motion prompt | File |
|---|---|---|
| A | "The man picks up a kettlebell and performs powerful kettlebell swings, sweat forming on his brow, slow cinematic push-in camera, energetic and determined" | `shotA_swing.mp4` |
| B | "The man turns toward the camera, wipes his forehead with the back of his hand, and gives a confident warm smile, gentle handheld camera, soft golden morning light" | `shotB_smile.mp4` |

### What this demonstrated
- **One image → many shots.** Both videos start from the identical `ref.png` frame:
  same face, shirt, gym, and lighting. Confirmed by extracting a mid-frame from each
  (`frameA.png` = gripping the kettlebell; `frameB.png` = wiping brow, smiling).
- **The motion prompt only describes the action + camera**, not the appearance —
  the image already locks the character and scene.
- This is the ad workflow in miniature: build the character once, animate it everywhere.

---

## 4. Key parameters learned

| Param | Used on | Notes |
|---|---|---|
| `image_size` | FLUX | `portrait_16_9` = vertical mobile; also `square_hd`, `landscape_16_9`, ... |
| `image_url` | Kling i2v | the start frame; switches a model into image-to-video mode |
| `aspect_ratio` | Seedance | `9:16` for vertical (i2v models inherit ratio from the image instead) |
| `resolution` | Seedance/FLUX-ish | iterate low, finalize high |
| `duration` | Seedance / Kling | Kling = 5 or 10s; Seedance = 4–15s |
| `generate_audio` | Seedance / Kling | set `false` to use the cheaper no-audio tier + add your own VO |
| `seed` | most | reuse to keep a look reproducible |

Pass any of these via `--param key=value` in `generate.php` / `image.php`.

---

## 5. Cost summary (2026-06-22)

Pulled from `php usage.php --start 2026-06-22`:

| Endpoint | Unit | Qty | Cost |
|---|---|---|---|
| `bytedance/seedance-2.0/fast/text-to-video` | 1k tokens | 108.9 | $1.2197 |
| `fal-ai/kling-video/v2.6/pro/image-to-video` | seconds | 10 | $0.7000 |
| `fal-ai/ltx-video` | videos | 2 | $0.0400 |
| `fal-ai/flux/schnell` | megapixels | 1 | $0.0030 |
| **Total** | | | **$1.9627** |

Starting balance was $8.76 → roughly **$6.80 remaining**.

**Cost intuition gained:** image gen is basically free (~$0.003), Kling i2v without
audio is cheap (~$0.07/s), Seedance is the pricey one (~$0.24/s with audio bundled in).
Iterate on cheap models + short durations, spend on the final render.

---

## 6. Files produced

- Scaffold/code: `fal.php`, `generate.php`, `image.php`, `usage.php`
- Docs: `README.md`, `MODELS.md`, this log
- Media (gitignored): `panda.mp4`, `panda_seedance.mp4`, `ref.png`,
  `shotA_swing.mp4`, `shotB_smile.mp4`, `frameA.png`, `frameB.png`

## 7. Next ideas
- Build a 6–8 shot vertical storyboard reusing one character (FLUX → Kling per shot).
- A `batch.php` to render a prompt list into shots automatically.
- Stitch shots + text/VO in an editor (or `ffmpeg`) into a finished mobile clip.
