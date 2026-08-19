<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listing not available · {{ $appName }}</title>
    {{-- No og:image here: an unavailable listing should not unfurl into a card. --}}
    <meta property="og:title" content="Listing not available">
    <meta property="og:description" content="This {{ $appName }} listing has been removed or is no longer available.">
    <meta name="robots" content="noindex">
    <style>
        :root { color-scheme: light dark; --bg: #f6f7f9; --text: #14181f; --muted: #6b7280; --accent: #1f6feb; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0f1115; --text: #f3f4f6; --muted: #9ca3af; --accent: #5b9bff; }
        }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text);
               font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; text-align: center; padding: 24px; }
        h1 { font-size: 1.4rem; margin: 0 0 8px; }
        p { color: var(--muted); max-width: 34rem; margin: 0 auto 20px; line-height: 1.6; }
        a { display: inline-block; background: var(--accent); color: #fff; text-decoration: none; padding: 11px 20px; border-radius: 10px; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1>This listing is no longer available</h1>
    <p>It may have been sold, removed by its owner, or the link may be incorrect.</p>
    <a href="{{ url('/') }}">Go to {{ $appName }}</a>
</main>
</body>
</html>
