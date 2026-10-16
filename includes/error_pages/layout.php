<?php /* expects $code, $title, $message. Self-contained on purpose: error pages must render even when assets cannot load. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= (int) $code ?> &middot; <?= esc($title) ?></title>
    <style nonce="<?= esc(csp_nonce()) ?>">
        :root { --bg:#f4f6fb; --surface:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --primary:#4f46e5; --primary-hover:#4338ca; --on-primary:#fff; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0a0f1e; --surface:#121a30; --text:#e8ecf8; --muted:#94a3b8; --border:#243252; --primary:#818cf8; --primary-hover:#a5b4fc; --on-primary:#0a0f1e; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; text-align: center; color: var(--text);
               font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.55;
               background: radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.18), transparent 60%), radial-gradient(700px 500px at 100% 110%, rgba(236,72,153,.14), transparent 60%), var(--bg); }
        .mark { display: inline-grid; place-items: center; width: 2.6rem; height: 2.6rem; margin-bottom: 1.25rem; border-radius: 12px; color: #fff; background: linear-gradient(135deg,#6366f1,#8b5cf6 60%,#ec4899); box-shadow: 0 6px 16px rgba(99,102,241,.4); }
        .mark svg { width: 1.35rem; height: 1.35rem; }
        .code { font-size: clamp(5rem, 18vw, 9rem); font-weight: 900; line-height: 1; letter-spacing: -.05em;
                background: linear-gradient(120deg,#4f46e5,#ec4899); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        h1 { margin: .4rem 0 .5rem; font-size: 1.6rem; letter-spacing: -.01em; }
        p { margin: 0 0 1.5rem; color: var(--muted); max-width: 26rem; }
        a.btn { display: inline-block; padding: .7rem 1.3rem; font-weight: 600; text-decoration: none; color: var(--on-primary); background: var(--primary); border-radius: 10px; }
        a.btn:hover { background: var(--primary-hover); }
        .ref { margin-top: 1.5rem; font-size: .8rem; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        <div class="mark"><?= icon('bolt') ?></div>
        <div class="code"><?= (int) $code ?></div>
        <h1><?= esc($title) ?></h1>
        <p><?= esc($message) ?></p>
        <a class="btn" href="<?= esc(currentSiteUrl()) ?>/">Back to home</a>
        <div class="ref">Reference: <?= esc(request_id()) ?></div>
    </main>
</body>
</html>
