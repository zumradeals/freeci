<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $title }} — FreeCI</title>
<style>
  :root { --bg: #f6f8fb; --card: #fff; --ink: #0f1b2d; --muted: #4a5a70; --line: #d9e0ea; --accent: #17375e; --warm: #c2410c; --tint: #fff3e6; --tint-ink: #b45309; }
  @media (prefers-color-scheme: dark) { :root { --bg: #0b1422; --card: #111d30; --ink: #eef2f8; --muted: #b3bfd0; --line: #2a3a52; --accent: #8fb4e8; --warm: #f59e6b; --tint: #2a1f14; --tint-ink: #f5b36b; } }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 16px; }
  main { max-width: 34rem; width: 100%; background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 36px 28px; text-align: center; display: grid; gap: 12px; justify-items: center; }
  .logo { font-weight: 800; font-size: 1.25rem; } .logo b { color: var(--warm); }
  .i { width: 56px; height: 56px; border-radius: 16px; background: var(--tint); color: var(--tint-ink); display: grid; place-items: center; font-size: 1.75rem; }
  h1 { margin: 0; font-size: 1.5rem; line-height: 1.25; }
  p { margin: 0; color: var(--muted); }
  .note { border-top: 1px solid var(--line); padding-top: 12px; width: 100%; font-size: .9375rem; }
  .b { display: inline-block; margin-top: 6px; background: var(--accent); color: var(--card); text-decoration: none; font-weight: 700; padding: 12px 20px; border-radius: 10px; }
</style>
</head>
<body>
<main role="main">
  <div class="logo">Free<b>CI</b></div>
  <div class="i" aria-hidden="true">{{ $icon }}</div>
  <h1>{{ $title }}</h1>
  <p>{{ $lead }}</p>
  <p class="note">{{ $note }}</p>
  <p>{{ $hint }}</p>
  <a class="b" href="">{{ $action }}</a>
</main>
</body>
</html>
