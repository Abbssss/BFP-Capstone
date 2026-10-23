<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Secure access' }} · FIRE-PMIS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #10213a; }
            * { box-sizing: border-box; }
            body { margin: 0; min-width: 320px; }
            .auth-shell { min-height: 100vh; display: grid; grid-template-columns: minmax(380px, 45.2%) 1fr; background: #fff; }
            .auth-hero { min-height: 100vh; position: relative; display: flex; flex-direction: column; justify-content: space-between; padding: 26px clamp(28px, 7.3vw, 90px) 28px; color: #fff; overflow: hidden; background: #06172c url('{{ asset('images/login-fire-trucks.jpg') }}') center / cover no-repeat; }
            .auth-hero::before { position: absolute; inset: 0; content: ""; background: linear-gradient(180deg, rgba(4, 18, 37, .80) 0%, rgba(4, 18, 37, .76) 42%, rgba(4, 18, 37, .94) 100%); }
            .auth-brand-wrap, .auth-hero-copy, .auth-legal { position: relative; z-index: 1; }
            .auth-back { display: inline-block; margin-bottom: 30px; color: #e1eaf5; font-size: 12px; text-decoration: none; }
            .auth-back:hover { color: #fff; text-decoration: underline; }
            .auth-brand { display: flex; align-items: center; gap: 11px; color: white; text-decoration: none; }
            .auth-brand-mark { width: 27px; height: 31px; display: grid; place-items: center; background: #cf2528; clip-path: polygon(50% 0, 100% 18%, 100% 73%, 50% 100%, 0 73%, 0 18%); color: white; font-size: 15px; font-weight: 800; }
            .auth-brand strong { display: block; font-size: 15px; letter-spacing: .04em; }
            .auth-brand small { display: block; margin-top: 3px; color: #aab9cb; font-size: 8px; letter-spacing: .15em; text-transform: uppercase; }
            .auth-hero-copy { margin: auto 0; max-width: 540px; padding: 55px 0 75px; }
            .auth-eyebrow { color: #f5b522; font-size: 10px; font-weight: 800; letter-spacing: .17em; text-transform: uppercase; }
            .auth-hero-copy h1 { margin: 16px 0 14px; max-width: 520px; color: white; font-size: clamp(28px, 3vw, 44px); font-weight: 600; letter-spacing: -.035em; line-height: 1.12; }
            .auth-hero-copy p { margin: 0; max-width: 440px; color: #c5d0de; font-size: 14px; line-height: 1.7; }
            .auth-legal { border-top: 1px solid rgba(212, 225, 240, .23); padding-top: 17px; color: #fff; font-size: 11px; }
            .auth-legal small { display: block; margin-top: 4px; color: #9cafc4; font-size: 10px; }
            .auth-main { position: relative; display: grid; place-items: center; min-height: 100vh; padding: 48px 28px 66px; }
            .auth-content { width: min(100%, 425px); }
            .auth-content .auth-kicker { margin: 0 0 12px; color: #c51e24; font-size: 10px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
            .auth-content h2 { margin: 0; color: #10213a; font-size: 30px; font-weight: 500; letter-spacing: -.035em; line-height: 1.2; }
            .auth-description { margin: 10px 0 25px; color: #728096; font-size: 13px; line-height: 1.6; }
            .auth-form { display: grid; gap: 17px; }
            .auth-field label { display: block; margin-bottom: 7px; color: #1d2d43; font-size: 11px; font-weight: 600; }
            .auth-field input { width: 100%; height: 43px; border: 1px solid #d5dce5; border-radius: 5px; padding: 0 12px; outline: none; color: #14243b; background: #fff; font: inherit; font-size: 13px; transition: border-color .15s, box-shadow .15s; }
            .auth-field input:focus { border-color: #c31f25; box-shadow: 0 0 0 3px rgba(195, 31, 37, .1); }
            .auth-field input::placeholder { color: #a0aaba; }
            .auth-error { margin-top: 6px; color: #b91c1c; font-size: 12px; }
            .auth-options { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #65738a; font-size: 12px; }
            .auth-check { display: inline-flex; align-items: center; gap: 8px; }
            .auth-check input { accent-color: #bd1f24; }
            .auth-link { color: #b71d23; font-size: 12px; font-weight: 600; text-decoration: none; }
            .auth-link:hover { text-decoration: underline; }
            .auth-submit { display: flex; width: 100%; min-height: 46px; align-items: center; justify-content: center; gap: 9px; border: 0; border-radius: 5px; background: #bd1f24; box-shadow: 0 5px 12px rgba(142, 20, 25, .16); color: white; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; transition: background .15s, transform .15s; }
            .auth-submit:hover { transform: translateY(-1px); background: #a9191e; }
            .auth-bottom { margin-top: 18px; border-top: 1px solid #e8ebf0; padding-top: 15px; color: #8190a4; font-size: 10px; line-height: 1.5; }
            .auth-bottom a { color: #ba1e23; font-weight: 600; text-decoration: none; }
            .auth-status { margin-bottom: 17px; color: #18794e; font-size: 12px; }
            @media (max-width: 760px) {
                .auth-shell { display: block; }
                .auth-hero { min-height: auto; padding: 21px 24px 23px; }
                .auth-back { margin-bottom: 19px; }
                .auth-hero-copy { margin: 0; padding: 34px 0 24px; }
                .auth-hero-copy h1 { font-size: 30px; }
                .auth-hero-copy p { font-size: 13px; }
                .auth-legal { padding-top: 13px; }
                .auth-main { min-height: auto; padding: 40px 24px; }
                .auth-content h2 { font-size: 27px; }
            }
        </style>
    </head>
    <body>
        <div class="auth-shell">
            <aside class="auth-hero">
                <div class="auth-brand-wrap">
                    <a class="auth-back" href="{{ route('home') }}">← Back to home</a>
                    <a class="auth-brand" href="{{ route('home') }}">
                        <span class="auth-brand-mark">F</span>
                        <span><strong>FIRE-PMIS</strong><small>Bureau of Fire Protection</small></span>
                    </a>
                </div>
                <div class="auth-hero-copy">
                    <div class="auth-eyebrow">Secure station access</div>
                    <h1>Equipment readiness begins with reliable information.</h1>
                    <p>Predictive maintenance and asset oversight for BFP</p>
                </div>
                <div class="auth-legal">Authorized personnel only<small>All activity is monitored and logged.</small></div>
            </aside>
            <main class="auth-main">
                <section class="auth-content">
                    {{ $slot }}
                    <div class="auth-bottom">◈ &nbsp;Protected by role-based access and secure session controls.</div>
                </section>
            </main>
        </div>
    </body>
</html>
