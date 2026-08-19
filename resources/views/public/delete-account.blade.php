<!doctype html>
<html lang="{{ $lang }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('delete_account.page_title') }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, sans-serif; color: #172033; background: #f5f7fb; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; }
        main { max-width: 680px; margin: auto; background: white; border-radius: 18px; padding: 32px; box-shadow: 0 12px 40px #17203314; }
        h1 { margin-top: 0; font-size: 2rem; }
        .muted { color: #5c667a; line-height: 1.65; }
        .notice { padding: 16px; border-radius: 10px; background: #eaf8ef; color: #176b39; margin-bottom: 20px; }
        label { display: block; font-weight: 650; margin: 18px 0 7px; }
        input[type=email], textarea { width: 100%; padding: 12px; border: 1px solid #cbd2df; border-radius: 9px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
        .check input { margin-top: 4px; }
        button { width: 100%; border: 0; border-radius: 9px; padding: 13px; background: #b42318; color: white; font: 700 1rem inherit; cursor: pointer; }
        .error { color: #b42318; font-size: .9rem; margin-top: 5px; }
        ul { line-height: 1.65; }
        .topbar { display: flex; justify-content: flex-end; margin-bottom: 18px; }
        .language { border: 1px solid #cbd2df; border-radius: 999px; padding: 7px 12px; color: #334155; text-decoration: none; font-weight: 650; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <div class="topbar"><a class="language" href="{{ route('delete-account.create', ['lang' => $lang === 'ar' ? 'en' : 'ar']) }}">{{ __('delete_account.switch_language') }}</a></div>
    <h1>{{ __('delete_account.heading') }}</h1>
    <p class="muted">{{ __('delete_account.intro') }}</p>

    @if (session('deletion_request_received'))
        <div class="notice">{{ __('delete_account.success') }}</div>
    @endif

    <h2>{{ __('delete_account.what_deleted') }}</h2>
    <ul class="muted">
        <li>{{ __('delete_account.deleted_account') }}</li>
        <li>{{ __('delete_account.deleted_images') }}</li>
        <li>{{ __('delete_account.retained_records') }}</li>
    </ul>

    <form method="post" action="{{ route('delete-account.store', ['lang' => $lang]) }}">
        @csrf
        <label for="email">{{ __('delete_account.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="reason">{{ __('delete_account.reason') }}</label>
        <textarea id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea>
        @error('reason') <div class="error">{{ $message }}</div> @enderror

        <label class="check"><input name="confirm" type="checkbox" value="1" required> <span>{{ __('delete_account.confirmation') }}</span></label>
        @error('confirm') <div class="error">{{ $message }}</div> @enderror

        <button type="submit">{{ __('delete_account.submit') }}</button>
    </form>
</main>
</body>
</html>
