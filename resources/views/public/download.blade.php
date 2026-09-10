<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Get the {{ $appName }} app</title>
    <meta name="robots" content="noindex">
    <style>
        :root { color-scheme: light dark; --bg: #f6f7f9; --card: #fff; --text: #14181f; --muted: #6b7280; --line: #e5e7eb; --accent: #1f6feb; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0f1115; --card: #171a21; --text: #f3f4f6; --muted: #9ca3af; --line: #262b35; --accent: #5b9bff; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        main { max-width: 380px; width: 100%; margin: 24px; padding: 32px 28px; background: var(--card); border: 1px solid var(--line); border-radius: 18px; text-align: center; }
        img.logo { width: 76px; height: 76px; border-radius: 18px; margin-bottom: 16px; }
        h1 { font-size: 1.3rem; margin: 0 0 8px; }
        p.muted { color: var(--muted); margin: 0 0 24px; line-height: 1.5; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 13px; margin-bottom: 12px; border-radius: 11px; text-decoration: none; font-weight: 600; }
        .btn.ios { background: #000; color: #fff; }
        .btn.android { background: var(--accent); color: #fff; }
        .spinner { display: none; margin: 0 auto 16px; width: 28px; height: 28px; border-radius: 50%; border: 3px solid var(--line); border-top-color: var(--accent); animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        body.redirecting .spinner { display: block; }
        body.redirecting .links { display: none; }
    </style>
</head>
<body>
<main>
    <div class="spinner"></div>
    <img class="logo" src="{{ asset('logo.png') }}" alt="{{ $appName }}">
    <h1>Get the {{ $appName }} app</h1>
    <p class="muted">Taking you to the store to download {{ $appName }}&hellip;</p>

    <div class="links">
        <a class="btn ios" href="{{ $iosUrl }}">Download on the App Store</a>
        <a class="btn android" href="{{ $androidUrl }}">Get it on Google Play</a>
    </div>
</main>
<script>
    (function () {
        var ua = navigator.userAgent || '';
        var isIOS = /iPad|iPhone|iPod/.test(ua)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var isAndroid = /Android/.test(ua);

        if (!isIOS && !isAndroid) {
            return;
        }

        document.body.classList.add('redirecting');

        if (isIOS) {
            window.location.replace(@json($iosUrl));
            return;
        }

        // Try to open the app itself first; the fallback URL fires automatically
        // if it isn't installed, without the user ever seeing an error dialog.
        var intentUrl = 'intent://download/#Intent;scheme=https;package='
            + @json($androidPackage)
            + ';S.browser_fallback_url=' + encodeURIComponent(@json($androidUrl)) + ';end';
        window.location.replace(intentUrl);
    })();
</script>
</body>
</html>
