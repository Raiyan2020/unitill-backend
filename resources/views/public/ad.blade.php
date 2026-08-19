@php
    $title = $ad->title;
    $price = $ad->formattedPrice();
    $summary = trim((string) ($ad->subtitle ?: $ad->description));
    $summary = $summary !== '' ? \Illuminate\Support\Str::limit(strip_tags($summary), 180) : $title;
    $location = $ad->location_name ?: $ad->city?->nameForLanguageCode($lang);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $appName }}</title>
    <meta name="description" content="{{ $summary }}">
    <link rel="canonical" href="{{ url('/ads/'.$ad->public_id) }}">

    {{-- Open Graph / Twitter cards: this is what makes a pasted link unfurl
         into a preview in WhatsApp, iMessage and social apps. --}}
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:url" content="{{ url('/ads/'.$ad->public_id) }}">
    <meta property="og:title" content="{{ $price ? $title.' — '.$price : $title }}">
    <meta property="og:description" content="{{ $summary }}">
    @if ($coverImage)
        <meta property="og:image" content="{{ $coverImage }}">
        <meta property="og:image:alt" content="{{ $title }}">
    @endif
    @if ($ad->price !== null)
        <meta property="product:price:amount" content="{{ number_format((float) $ad->price, 2, '.', '') }}">
        <meta property="product:price:currency" content="{{ $ad->currency }}">
    @endif
    <meta name="twitter:card" content="{{ $coverImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $summary }}">
    @if ($coverImage)
        <meta name="twitter:image" content="{{ $coverImage }}">
    @endif

    <style>
        :root { color-scheme: light dark; --bg: #f6f7f9; --card: #fff; --text: #14181f; --muted: #6b7280; --line: #e5e7eb; --accent: #1f6feb; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0f1115; --card: #171a21; --text: #f3f4f6; --muted: #9ca3af; --line: #262b35; --accent: #5b9bff; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.55; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        header { display: flex; align-items: center; gap: 10px; padding: 14px 0; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
        .gallery { display: flex; gap: 8px; overflow-x: auto; scroll-snap-type: x mandatory; }
        .gallery img { width: 100%; max-width: 100%; flex: 0 0 100%; scroll-snap-align: center; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
        .body { padding: 16px; }
        h1 { font-size: 1.35rem; margin: 0 0 6px; }
        .price { font-size: 1.5rem; font-weight: 700; margin: 8px 0; }
        .muted { color: var(--muted); font-size: .92rem; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
        .chip { border: 1px solid var(--line); border-radius: 999px; padding: 3px 10px; font-size: .82rem; color: var(--muted); }
        .desc { white-space: pre-wrap; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 8px 0; border-top: 1px solid var(--line); vertical-align: top; }
        td:first-child { color: var(--muted); width: 45%; }
        footer { text-align: center; padding: 24px 0; }
        .cta { display: inline-block; background: var(--accent); color: #fff; text-decoration: none; padding: 11px 20px; border-radius: 10px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <header>{{ $appName }}</header>

    <div class="card">
        @if ($images)
            <div class="gallery">
                @foreach ($images as $image)
                    <img src="{{ $image }}" alt="{{ $title }}" loading="lazy">
                @endforeach
            </div>
        @endif

        <div class="body">
            <h1>{{ $title }}</h1>
            @if ($price)
                <div class="price">{{ $price }}@if ($ad->is_negotiable)<span class="muted"> · negotiable</span>@endif</div>
            @endif

            @if ($categoryPath)
                <div class="chips">
                    @foreach ($categoryPath as $crumb)
                        <span class="chip">{{ $crumb }}</span>
                    @endforeach
                </div>
            @endif

            @if ($location)
                <div class="muted">{{ $location }}</div>
            @endif

            @if (trim((string) $ad->description) !== '')
                <div class="desc">{{ $ad->description }}</div>
            @endif

            @if ($attributes)
                <table>
                    @foreach ($attributes as $attribute)
                        <tr>
                            <td>{{ $attribute['label'] }}</td>
                            <td>{{ $attribute['value'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>

    <footer>
        <p class="muted">Get the {{ $appName }} app to message the seller.</p>
        <a class="cta" href="{{ url('/') }}">Open {{ $appName }}</a>
    </footer>
</div>
</body>
</html>
