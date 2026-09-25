<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #111827;
        }
        .card {
            background: #fff;
            width: 360px;
            max-width: calc(100vw - 2rem);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
            text-align: center;
        }
        .card h1 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        .error { margin: 0 0 1rem; color: #dc2626; font-size: .875rem; }
        .okta {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: .625rem 1rem;
            border-radius: .5rem;
            background: #007dc1;
            color: #fff;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
        }
        .okta:hover { background: #0a6aa1; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Sign in</h1>

        @if ($error = session('okta::error'))
            <p class="error" id="okta-error">{{ $error }}</p>
        @endif

        {{-- How a consumer wires the button: link it at the panel's login route. --}}
        <a href="{{ route('okta.login') }}" id="okta-login" class="okta">
            Log In with Okta
        </a>
    </main>
</body>
</html>
