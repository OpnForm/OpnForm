<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Authorize {{ $client->name }} - OpnForm</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f8fafc; color: #0f172a; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .card { width: 100%; max-width: 440px; padding: 32px; border: 1px solid #e2e8f0; border-radius: 20px; background: white; box-shadow: 0 20px 50px rgba(15, 23, 42, .08); }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .brand-logo { display: block; width: 42px; height: 42px; }
        .brand-name { font-size: 18px; font-weight: 700; letter-spacing: -.02em; }
        h1 { margin: 0 0 8px; text-align: center; font-size: 24px; line-height: 1.2; }
        .intro { margin: 0; text-align: center; color: #64748b; line-height: 1.5; }
        .account, .destination, .permission { margin-top: 24px; padding: 16px; border-radius: 12px; background: #f8fafc; }
        .label { margin: 0 0 6px; color: #64748b; font-size: 13px; }
        .value { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        .permission { display: flex; gap: 10px; align-items: flex-start; margin-top: 12px; }
        .permission span { color: #2563eb; }
        .permission p { margin: 0; color: #334155; font-size: 14px; line-height: 1.45; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 28px; }
        form { margin: 0; }
        button { width: 100%; min-height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; background: white; color: #0f172a; font: inherit; font-weight: 600; cursor: pointer; }
        button.primary { border-color: #2563eb; background: #2563eb; color: white; }
        button:hover { filter: brightness(.97); }
    </style>
</head>
<body>
<main class="card">
    <div class="brand" aria-label="OpnForm">
        <svg class="brand-logo" aria-hidden="true" fill="none" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            <linearGradient id="opnform-gradient" gradientUnits="userSpaceOnUse" x1="60" x2="60" y1="2" y2="118.352">
                <stop offset="0" stop-color="#2563eb"/>
                <stop offset="1" stop-color="#60a5fa"/>
            </linearGradient>
            <path clip-rule="evenodd" d="m80.6502 118.352c22.9638-8.418 39.3498-30.4712 39.3498-56.352 0-33.1371-26.8629-60-60-60s-60 26.8629-60 60c0 25.8808 16.3862 47.934 39.3498 56.352l16.631-38.9411c-7.4746-1.9924-12.9808-8.8086-12.9808-16.9109 0-9.665 7.835-17.5 17.5-17.5s17.5 7.835 17.5 17.5c0 8.4269-5.9563 15.4627-13.8885 17.1269z" fill="url(#opnform-gradient)" fill-rule="evenodd"/>
        </svg>
        <span class="brand-name">OpnForm</span>
    </div>
    <h1>Connect {{ $client->name }}</h1>
    <p class="intro">Review the access requested for your OpnForm account.</p>

    <section class="account">
        <p class="label">Signed in as</p>
        <p class="value">{{ $user->email }}</p>
    </section>

    <section class="destination">
        <p class="label">Callback destination</p>
        <p class="value">{{ $request->query('redirect_uri') }}</p>
    </section>

    @foreach ($scopes as $scope)
        <section class="permission">
            <span aria-hidden="true">●</span>
            <p>{{ $scope->description }}</p>
        </section>
    @endforeach

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Cancel</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">Authorize</button>
        </form>
    </div>
</main>
</body>
</html>
