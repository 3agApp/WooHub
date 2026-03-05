<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') | {{ config('app.name') }}</title>

        <style>
            :root {
                color-scheme: light dark;
            }

            body {
                margin: 0;
                font-family: "Instrument Sans", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #0b0d12;
                color: #e7eaf0;
            }

            .card {
                width: min(38rem, calc(100vw - 2rem));
                border: 1px solid #2a3140;
                border-radius: 0.9rem;
                padding: 2rem;
                background: #121722;
            }

            .code {
                font-size: 0.875rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #aab4c8;
                margin: 0 0 0.75rem;
            }

            h1 {
                margin: 0;
                font-size: clamp(1.4rem, 2vw, 1.8rem);
            }

            p {
                margin: 0.9rem 0 1.6rem;
                color: #c3cbe0;
                line-height: 1.5;
            }

            a {
                display: inline-block;
                border: 1px solid #334155;
                color: inherit;
                text-decoration: none;
                border-radius: 0.65rem;
                padding: 0.55rem 0.9rem;
            }

            a:hover {
                border-color: #475569;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="code">@yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <a href="{{ url('/dashboard') }}">Go to dashboard</a>
        </main>
    </body>
</html>