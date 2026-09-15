# FEATURE MAP — spec → implementation

Every numbered feature from the uploaded specification, where it lives, and whether it is an **addon** (installable/removable from Admin → Addons) or **core** (always present).

Legend: **addon** = ships in `plugins/<key>/` · **core** = `core/` + `templates/` · **planned** = not implemented yet

| # | Feature | Where | Owner |
|---|---|---|---|
| 1 | 🏠 WEBSITE & BRANDING | core | core |
| 2 | 👤 USER & AUTHENTICATION | core | core |
| 3 | 👥 USER ROLES | core | core |
| 4 | 📊 CREATOR DASHBOARD | core | core |
| 5 | 📁 PROJECT MANAGEMENT | core | core |
| 6 | 💡 AI STORY IDEA ENGINE | addon | idea-engine |
| 7 | ✍️ AI STORY GENERATOR | addon | story-studio |
| 8 | 📝 STORY WORKSPACE | addon | story-studio |
| 9 | 🩺 STORY DOCTOR | addon | story-studio |
| 10 | ✍️ REWRITE STUDIO | addon | story-studio |
| 11 | 🧑 CHARACTER STUDIO | addon | character-studio |
| 12 | 📖 CHARACTER BIBLE | addon | character-studio |
| 13 | 🔒 CHARACTER LOCK | addon | character-studio |
| 14 | ❤️ CHARACTER RELATIONSHIP MAP | addon | character-studio |
| 15 | 🎬 SCENE ENGINE | addon | scene-engine |
| 16 | 🎥 AI DIRECTOR | addon | ai-director |
| 17 | 🎞️ SHOT LIST | addon | ai-director |
| 18 | 🖼️ STORYBOARD | addon | ai-director |
| 19 | 🎨 AI IMAGE GENERATION | addon | image-studio |
| 20 | 🖌️ IMAGE EDITOR | addon | image-studio |
| 21 | 🎨 VISUAL STYLE SYSTEM | addon | image-studio |
| 22 | 🗣️ VOICE STUDIO | addon | voice-studio |
| 23 | 🎭 CHARACTER VOICE ASSIGNMENT | addon | voice-studio |
| 24 | 🎵 MUSIC STUDIO | addon | audio-studio |
| 25 | 🔊 SOUND EFFECTS | addon | audio-studio |
| 26 | 🎚️ AUDIO MIXING | addon | audio-studio |
| 27 | 🎥 VIDEO GENERATION | addon | video-engine |
| 28 | 🎞️ VIDEO TIMELINE | addon | timeline-editor |
| 29 | ⚙️ REAL MEDIA/FFMPEG PIPELINE | addon | media-pipeline |
| 30 | 📝 SUBTITLE STUDIO | addon | subtitle-studio |
| 31 | 🖼️ THUMBNAIL STUDIO | addon | thumbnail-studio |
| 32 | 📱 SOCIAL CONTENT STUDIO | addon | social-suite |
| 33 | ✂️ AUTO CLIPS | addon | social-suite |
| 34 | 🚀 VIRAL OPTIMIZER | addon | social-suite |
| 35 | 🏭 CONTENT FACTORY | addon | content-factory |
| 36 | 📺 SERIES BUILDER | addon | content-factory |
| 37 | 🗂️ ASSET LIBRARY | addon | asset-library |
| 38 | ☁️ CLOUD STORAGE | core | core |
| 39 | 🤖 AI ORCHESTRATOR | core | core |
| 40 | 🔌 MULTI-AI PROVIDERS | addon | elevenlabs, openai, replicate, runway, stability |
| 41 | 🔄 AI PROVIDER FALLBACK | addon | elevenlabs, openai, replicate, runway, stability |
| 42 | 🛠️ AI PROVIDER ADMIN | core | core |
| 43 | 📈 AI USAGE TRACKING | addon | analytics-pro |
| 44 | ⚡ REDIS | core | core — replaced by the **MySQL job queue** (queue-manager addon); no Redis on cPanel |
| 45 | 📋 BULLMQ / JOB QUEUE | addon | queue-manager |
| 46 | 👷 WORKERS | addon | queue-manager |
| 47 | 📋 JOB MANAGEMENT | addon | queue-manager |
| 48 | 🪙 CREDIT SYSTEM | addon | billing-suite |
| 49 | 📊 USAGE LIMITS | addon | billing-suite |
| 50 | 💰 WALLET | addon | billing-suite |
| 51 | 💳 PAYSTACK | addon | paystack |
| 52 | 💳 FLUTTERWAVE | addon | flutterwave |
| 53 | 📦 SUBSCRIPTIONS | addon | subscriptions |
| 54 | 🎟️ COUPONS | addon | billing-suite |
| 55 | 🤝 REFERRAL SYSTEM | addon | billing-suite |
| 56 | 🎁 FREE CREDIT SYSTEM | addon | billing-suite |
| 57 | 📢 ADVERTISEMENT SYSTEM | addon | ads-suite |
| 58 | 🎁 REWARDED ADS | addon | ads-suite |
| 59 | 🔔 NOTIFICATION SYSTEM | addon | notifications |
| 60 | 📣 ANNOUNCEMENTS | addon | notifications |
| 61 | 🛡️ ADMIN DASHBOARD | core | core |
| 62 | 🎛️ FEATURE GOVERNANCE | core | core |
| 63 | ⚙️ FEATURE CONFIGURATION | core | core |
| 64 | 📊 ANALYTICS | addon | analytics-pro |
| 65 | 🧾 AUDIT LOGGING | addon | audit-security |
| 66 | 🏢 AGENCY WORKSPACE | addon | agency-workspace |
| 67 | 👨‍💼 CLIENT PORTAL | addon | agency-workspace |
| 68 | 👥 COLLABORATION | addon | agency-workspace |
| 69 | ✅ APPROVAL WORKFLOW | addon | agency-workspace |
| 70 | 🎨 BRAND KIT | addon | **brand-kit** (logos, colours, fonts, watermark, intro/outro, templates) |
| 71 | 🛒 MARKETPLACE | addon | marketplace |
| 72 | 💵 MARKETPLACE TRANSACTIONS | addon | marketplace |
| 73 | 📱 SOCIAL ACCOUNT CONNECTION | addon | social-publishing |
| 74 | 📤 SOCIAL PUBLISHING | addon | social-publishing |
| 75 | 📲 PWA | addon | pwa-pack |
| 76 | ⚡ PERFORMANCE | addon | media-pipeline |
| 77 | 👁️ OBSERVABILITY | addon | media-pipeline |
| 78 | 🔐 SECURITY | addon | audit-security |
| 79 | 🧪 TESTING | core | core |
| 80 | 🌍 CULTURE & LANGUAGE SUPPORT | addon | culture-pack |
| 81 | 📦 EXPORT SYSTEM | addon | export-suite |
| 82 | 🔗 SHARING | core | core — `shares` table, `?r=share/<token>`, read-only client approval links |
| 83 | 🔄 COMPLETE PRODUCTION PIPELINE | core | core |

## Notes on the honest ones

* **44 Redis** — deliberately not used. cPanel does not provide Redis; the queue is MySQL-backed (`core/jobs.php`) and drained by `cron.php`.
* **29 / 76 / 77 Real FFmpeg pipeline** — `core/media.php` probes for FFmpeg at runtime. If it is missing, renders fail with an explicit "NOT CONFIGURED" error instead of faking a file.
* **70 Brand kit** — implemented (`plugins/brand-kit/`, `brand_kits` table, `?r=brandkit`, plan-gated to Pro and above).
* **12 Character bible** — each character card carries an editable bible (appearance, background, motivations, goals, fears, references, consistency) plus a completeness meter.
* **34 Viral optimizer** — `social-suite.optimize` produces hooks, titles, hashtags, audience and clip selection, with an explicit "no guaranteed virality" notice.
* **69 Approval workflow** — Draft → Review → Changes → Approval → Final on the project page, with change requests that can be resolved.
* **76 Caching** — file-based cache (`core/util.php`: `sf_cache_get/set/forget/prune`), used for addon manifest discovery and pruned by cron. No Redis required.
* **Admin coverage** — every feature above that needs management has an admin tab: AI Providers (keys/priority/status), Feature governance (45 toggles), Wallet & Payments, Coupons, Referrals, Announcements, Marketplace moderation, Agencies, Social, Storage, PWA, Analytics, Audit, Logs, System check (with one-click repair).
* **40 Multi-AI providers** — five vendor adapters plus `openai-compatible`, which connects any OpenAI-compatible endpoint (OpenRouter, Groq, Together, Fireworks, DeepSeek, Mistral, Ollama, LM Studio, your own proxy) for text, images and voice.
* **1 Dark/light UI** — `?r=theme` switches theme; stored per account (and in a cookie for guests); CSS variables drive both palettes.
* **40 / 41 / 43 Multi-AI providers, fallback, usage** — adapters for OpenAI, Stability, ElevenLabs, Replicate and Runway ship as addons and report `NOT CONFIGURED` until you add keys.
