# fal.ai video models — pricing & comparison

Reference for the text/image-to-video models considered while building this project.

> ⚠️ **Prices verified 2026-06-22** from each model's page on fal.ai. Pricing and model
> IDs drift — always confirm on the model page before relying on a number.
> The "≈ cost / 5s" column is a rough estimate from the per-second rate; flat-rate
> and per-token models are noted.

## Quick comparison

| Model id | Tier | Max res | Audio | Pricing | ≈ cost / 5s |
|---|---|---|---|---|---|
| `fal-ai/ltx-video` | Budget | 768×512 | No | **$0.02 / video** (flat) | **$0.02** |
| `fal-ai/minimax/hailuo-02/standard/text-to-video` | Cheap | 768p | No | $0.045 / sec | ~$0.23 |
| `fal-ai/wan/v2.2-a14b/text-to-video` | Cheap | 720p | No | $0.08/s (720p), $0.06 (580p), $0.04 (480p) | ~$0.40 |
| `fal-ai/kling-video/v2.6/pro/image-to-video` | Balanced | — | Yes | $0.07/s (no audio), $0.14/s (audio), $0.168/s (+voice) | ~$0.35–0.84 |
| `bytedance/seedance-2.0/fast/text-to-video` | Balanced | 720p | Yes (incl.) | $0.2419 / sec | ~$1.21 |
| `bytedance/seedance-2.0/text-to-video` | Balanced+ | 720p | Yes (incl.) | $0.3034 / sec | ~$1.52 |
| `fal-ai/veo3` (fast) | Premium | 1080p | Yes | $0.25/s (audio off), $0.40/s (audio on) | ~$1.25–2.00 |
| `fal-ai/veo3` (standard) | Top tier | 1080p | Yes | $0.50/s (audio off), $0.75/s (audio on) | ~$2.50–3.75 |

## By tier

### Budget — pennies per clip
- **`fal-ai/ltx-video`** — flat **$0.02/video**, 768×512, no audio, very fast.
  The first model we ran. Quality is the tradeoff (this is the one that looked low-quality).

### Cheap — cheap but a real step up from LTX
- **`fal-ai/minimax/hailuo-02/standard/text-to-video`** — 768p, **$0.045/sec**
  (a 6s clip ≈ $0.27). No audio. Strong motion for the price.
- **`fal-ai/wan/v2.2-a14b/text-to-video`** — resolution-tiered pricing:
  **$0.08/s @720p**, $0.06 @580p, $0.04 @480p (billed at 16 fps). No audio. Open-weights lineage.

### Balanced — best quality-per-dollar
- **`fal-ai/kling-video/v2.6/pro/image-to-video`** — image-to-video, 5 or 10s,
  native audio. **$0.07/s** (no audio) / **$0.14/s** (audio) / **$0.168/s** (+ voice control).
  Cheapest of the "high quality" options if you don't need audio.
- **`bytedance/seedance-2.0/fast/text-to-video`** — 720p, audio included, **$0.2419/sec**.
  **This is what we switched to** — clear quality jump over LTX (1280×720 + AAC audio).
  Also has a non-fast variant `bytedance/seedance-2.0/text-to-video` at **$0.3034/sec**.
  - Inputs: `prompt`, `resolution` (480p/720p), `duration` (auto or 4–15s),
    `aspect_ratio` (auto/21:9/16:9/4:3/1:1/3:4/9:16), `generate_audio`, `seed`.
  - Also offers token-based billing: `$0.014 / 1,000 tokens`,
    where `tokens = (height × width × duration × 24) / 1024`.

### Premium / top tier — best quality, highest cost
- **`fal-ai/veo3`** (Google Veo 3) — 1080p, native audio (dialogue, SFX, ambient).
  - **Fast:** $0.25/s (audio off), $0.40/s (audio on).
  - **Standard:** $0.50/s (audio off), $0.75/s (audio on) → a 5s clip with audio = **$3.75**.
  - Use when you want the highest fidelity and 1080p.

## What we actually ran

| When | Model | Output | Cost |
|---|---|---|---|
| First test | `fal-ai/ltx-video` | 768×512, 5s, no audio | ~$0.02 |
| Quality upgrade | `bytedance/seedance-2.0/fast/text-to-video` | 1280×720, 5s, + audio | ~$1.21 |

## Notes on choosing

- **Cheapest acceptable quality:** Hailuo-02 or Wan 2.2 (~$0.23–0.40 / 5s).
- **Best quality-per-dollar with audio:** Kling v2.6 pro (image-to-video).
- **Best text-to-video balance we tested:** Seedance 2.0 fast.
- **No-compromise quality / 1080p:** Veo 3 (expect $2–4 per 5s clip).
- Cost scales with **duration** and often **resolution** — keep clips short (4–5s) while iterating,
  then spend on a final longer/high-res render.

## Sources
- https://fal.ai/models/fal-ai/ltx-video
- https://fal.ai/models/fal-ai/minimax/hailuo-02/standard/text-to-video
- https://fal.ai/models/fal-ai/wan/v2.2-a14b/text-to-video
- https://fal.ai/models/fal-ai/kling-video/v2.6/pro/image-to-video
- https://fal.ai/models/bytedance/seedance-2.0/fast/text-to-video
- https://fal.ai/models/fal-ai/veo3
