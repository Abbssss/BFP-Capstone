<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#081a31">
    <title>FIRE-PMIS | BFP Sta. Ana, Manila</title>
    <meta name="description" content="Predictive maintenance and equipment readiness management for BFP Sta. Ana, Manila.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #14243a; scroll-behavior: smooth; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; background: #fff; }
        a { color: inherit; }
        .site-nav { position: sticky; z-index: 5; top: 0; display: flex; height: 76px; align-items: center; justify-content: space-between; gap: 24px; border-bottom: 1px solid #e9edf2; padding: 0 max(5vw, calc((100vw - 1200px) / 2)); background: rgba(255,255,255,.96); backdrop-filter: blur(14px); }
        .site-brand { display: inline-flex; align-items: center; gap: 11px; text-decoration: none; }
        .site-mark { display: grid; width: 34px; height: 39px; place-items: center; clip-path: polygon(50% 0,100% 18%,100% 73%,50% 100%,0 73%,0 18%); background: #c82027; color: #fff; font-size: 18px; font-weight: 800; }
        .site-brand strong { display: block; color: #14243a; font-size: 14px; letter-spacing: .04em; }
        .site-brand small { display: block; margin-top: 3px; color: #7b8796; font-size: 9px; letter-spacing: .12em; text-transform: uppercase; }
        .site-nav-links { display: flex; align-items: center; gap: clamp(18px, 3vw, 38px); }
        .site-nav-links a { color: #506075; font-size: 13px; text-decoration: none; transition: color .15s; }
        .site-nav-links a:hover { color: #bd1f24; }
        .site-nav .nav-cta, .button-primary { display: inline-flex; min-height: 43px; align-items: center; justify-content: center; gap: 10px; border: 1px solid #b91f24; border-radius: 5px; padding: 0 18px; background: #bd1f24; color: white; font-size: 13px; font-weight: 700; text-decoration: none; transition: background .15s, transform .15s; }
        .site-nav .nav-cta:hover, .button-primary:hover { transform: translateY(-1px); background: #a7191f; }
        .nav-register { color: #47576c; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .nav-register:hover { color: #bd1f24; }
        .hero { display: grid; min-height: 585px; grid-template-columns: 1fr 1fr; align-items: center; gap: clamp(30px, 6vw, 78px); max-width: 1200px; margin: 0 auto; padding: 70px 30px 76px; }
        .hero-copy { padding: 8px 0; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; color: #bd1f24; font-size: 11px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; }
        .eyebrow::before { width: 23px; height: 2px; background: #bd1f24; content: ""; }
        .hero h1 { margin: 20px 0 18px; color: #10213a; font-size: clamp(38px, 5vw, 61px); font-weight: 600; letter-spacing: -.055em; line-height: 1.05; }
        .hero-copy > p { max-width: 550px; margin: 0; color: #65748a; font-size: 16px; line-height: 1.8; }
        .hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 22px; margin-top: 30px; }
        .button-text { display: inline-flex; align-items: center; gap: 8px; color: #20334d; font-size: 13px; font-weight: 700; text-decoration: none; }
        .button-text:hover { color: #bd1f24; }
        .trust-row { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 32px; color: #56667b; font-size: 11px; }
        .trust-row span { display: inline-flex; align-items: center; gap: 7px; }
        .trust-icon { color: #b51e24; font-size: 14px; }
        .hero-visual { position: relative; min-height: 420px; border-radius: 10px; background: #0b1c30 url('{{ asset('images/bfp-fire-trucks.jpg') }}') center / cover no-repeat; box-shadow: 0 28px 60px rgba(10, 30, 54, .18); }
        .hero-visual::after { position: absolute; inset: 0; border-radius: inherit; background: linear-gradient(130deg, rgba(8,26,49,.08), rgba(8,26,49,.24)); content: ""; }
        .readiness-card, .risk-card { position: absolute; z-index: 1; border: 1px solid rgba(255,255,255,.65); border-radius: 8px; background: rgba(255,255,255,.96); box-shadow: 0 14px 36px rgba(7, 24, 45, .2); }
        .readiness-card { top: 28px; right: -22px; min-width: 185px; padding: 15px 17px; }
        .card-caption { display: block; color: #7e8a9b; font-size: 9px; font-weight: 800; letter-spacing: .12em; }
        .readiness-card strong { display: inline-block; margin-top: 6px; color: #14243a; font-size: 25px; }
        .status-pill { float: right; margin-top: 11px; border-radius: 20px; padding: 4px 8px; background: #e8f5ed; color: #267544; font-size: 9px; font-weight: 700; }
        .risk-card { right: -22px; bottom: 30px; width: 230px; display: flex; gap: 12px; align-items: center; padding: 15px; }
        .risk-mark { display: grid; width: 36px; height: 36px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: #fff0ed; color: #bd3427; font-weight: 800; }
        .risk-card strong { display: block; margin-top: 4px; color: #17273d; font-size: 12px; }
        .risk-card small { display: block; margin-top: 4px; color: #b6493b; font-size: 10px; }
        .section { padding: 88px 30px; }
        .section-inner { max-width: 1140px; margin: 0 auto; }
        .challenge { display: grid; grid-template-columns: .9fr 1.1fr; align-items: center; gap: 60px; border-top: 1px solid #edf0f3; background: #f7f8fa; }
        .section h2 { margin: 13px 0 0; color: #11233b; font-size: clamp(29px, 3.7vw, 42px); font-weight: 600; letter-spacing: -.04em; line-height: 1.14; }
        .challenge-copy { color: #65748a; font-size: 15px; line-height: 1.8; }
        .challenge-copy p { margin: 0 0 17px; }
        .challenge-note { display: flex; align-items: flex-start; gap: 11px; color: #263952; font-weight: 600; }
        .feature-heading { display: flex; align-items: end; justify-content: space-between; gap: 30px; margin-bottom: 36px; }
        .feature-heading > p { max-width: 370px; margin: 0 0 4px; color: #6a788b; font-size: 14px; line-height: 1.7; }
        .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 17px; }
        .feature-card { min-height: 210px; border: 1px solid #e6eaf0; border-radius: 8px; padding: 22px; background: #fff; transition: box-shadow .18s, transform .18s; }
        .feature-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(22, 41, 67, .08); }
        .feature-number { color: #bd1f24; font-size: 11px; font-weight: 800; letter-spacing: .1em; }
        .feature-symbol { display: grid; width: 38px; height: 38px; margin: 17px 0 14px; place-items: center; border-radius: 8px; background: #fff2f1; color: #b61e23; font-size: 18px; }
        .feature-card h3 { margin: 0 0 8px; color: #17283f; font-size: 16px; }
        .feature-card p { margin: 0; color: #758296; font-size: 12px; line-height: 1.7; }
        .process { background: #f7f8fa; }
        .process-heading { max-width: 580px; margin-bottom: 39px; }
        .process-heading p { margin: 15px 0 0; color: #66758a; font-size: 14px; line-height: 1.7; }
        .process-steps { display: grid; grid-template-columns: repeat(5, 1fr); }
        .process-step { position: relative; padding: 0 16px; text-align: center; }
        .process-step:not(:last-child)::after { position: absolute; z-index: 0; top: 22px; left: calc(50% + 28px); width: calc(100% - 56px); height: 1px; background: #d9dee6; content: ""; }
        .step-number { position: relative; z-index: 1; display: grid; width: 44px; height: 44px; margin: 0 auto 14px; place-items: center; border: 1px solid #e1c5c5; border-radius: 50%; background: #fff; color: #bd1f24; font-size: 14px; font-weight: 700; }
        .process-step strong { display: block; color: #192a41; font-size: 14px; }
        .process-step span:last-child { display: block; margin-top: 5px; color: #7a8798; font-size: 11px; }
        .contact-band { display: flex; align-items: center; justify-content: space-between; gap: 30px; border-radius: 10px; padding: 35px 40px; background: #0b1c34; color: white; }
        .contact-band h2 { margin: 0; color: white; font-size: 26px; }
        .contact-band p { margin: 9px 0 0; color: #bac6d5; font-size: 13px; }
        .contact-band .button-primary { flex: 0 0 auto; border-color: #d52a31; background: #c82027; }
        .site-footer { display: flex; align-items: center; justify-content: space-between; gap: 20px; border-top: 1px solid #e8ebef; padding: 24px max(5vw, calc((100vw - 1200px) / 2)); color: #768398; font-size: 11px; }
        .footer-unit { color: #283a52; font-weight: 700; }
        @media (max-width: 850px) {
            .site-nav { height: 68px; padding: 0 22px; }
            .site-nav-links { display: none; }
            .nav-register { display: none; }
            .hero { grid-template-columns: 1fr; gap: 38px; padding: 60px 24px; }
            .hero-visual { min-height: 390px; margin-right: 12px; }
            .challenge { grid-template-columns: 1fr; gap: 22px; }
            .feature-heading { display: block; }
            .feature-heading > p { margin-top: 16px; }
            .feature-grid { grid-template-columns: repeat(2, 1fr); }
            .readiness-card, .risk-card { right: -12px; }
        }
        @media (max-width: 560px) {
            .site-brand small { font-size: 8px; }
            .site-nav .nav-cta { min-height: 39px; padding: 0 12px; font-size: 11px; }
            .hero { padding-top: 48px; }
            .hero h1 { font-size: 41px; }
            .hero-copy > p { font-size: 14px; }
            .trust-row { gap: 12px; }
            .hero-visual { min-height: 300px; }
            .readiness-card { top: 15px; min-width: 155px; padding: 12px; }
            .risk-card { bottom: 15px; width: 208px; padding: 12px; }
            .section { padding: 66px 22px; }
            .feature-grid { grid-template-columns: 1fr; }
            .feature-card { min-height: 0; }
            .process-steps { grid-template-columns: 1fr; gap: 22px; }
            .process-step { display: grid; grid-template-columns: 48px 1fr; column-gap: 14px; padding: 0; text-align: left; }
            .process-step:not(:last-child)::after { top: 46px; left: 21px; width: 1px; height: calc(100% - 23px); }
            .step-number { grid-row: span 2; margin: 0; }
            .process-step strong { align-self: end; }
            .process-step span:last-child { align-self: start; }
            .contact-band { display: block; padding: 27px 24px; }
            .contact-band .button-primary { margin-top: 20px; }
            .site-footer { display: block; padding: 22px; line-height: 1.8; }
            .footer-unit { display: block; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <header class="site-nav">
        <a class="site-brand" href="{{ route('home') }}" aria-label="FIRE-PMIS home">
            <span class="site-mark">F</span>
            <span><strong>FIRE-PMIS</strong><small>Bureau of Fire Protection</small></span>
        </a>
        <nav class="site-nav-links" aria-label="Main navigation">
            <a href="#about">About</a>
            <a href="#features">Features</a>
            <a href="#process">How it works</a>
            <a href="#contact">Contact</a>
        </nav>
        <a class="nav-register" href="{{ route('register') }}">Request access</a>
        <a class="nav-cta" href="{{ route('login') }}">Secure login <span aria-hidden="true">→</span></a>
    </header>

    <main>
        <section class="hero" id="about">
            <div class="hero-copy">
                <span class="eyebrow">BFP · Equipment readiness</span>
                <h1>Predictive Maintenance for Mission-Critical Firefighting Equipment.</h1>
                <p>FIRE-PMIS helps BFP anticipate equipment risks, coordinate maintenance, and protect operational readiness through reliable data and responsible decision support.</p>
                <div class="hero-actions">
                    <a class="button-primary" href="{{ route('login') }}">Access FIRE-PMIS <span aria-hidden="true">→</span></a>
                    <a class="button-text" href="#process">See how it works <span aria-hidden="true">↓</span></a>
                </div>
                <div class="trust-row">
                    <span><b class="trust-icon">✓</b> Role-based access</span>
                    <span><b class="trust-icon">✓</b> Audit-ready records</span>
                    <span><b class="trust-icon">✓</b> Human-reviewed decisions</span>
                </div>
            </div>
            <div class="hero-visual" role="img" aria-label="Fire engines ready inside a station">
                <div class="readiness-card">
                    <span class="card-caption">STATION READINESS</span>
                    <strong>Operational</strong><span class="status-pill">Ready</span>
                </div>
                <div class="risk-card">
                    <span class="risk-mark">!</span>
                    <span><span class="card-caption">RISK FLAG</span><strong>Equipment review</strong><small>Review required by personnel</small></span>
                </div>
            </div>
        </section>

        <section class="section challenge" id="challenge">
            <div class="section-inner" style="display: contents">
                <div>
                    <span class="eyebrow">The operational challenge</span>
                    <h2>Equipment failure cannot be the first warning.</h2>
                </div>
                <div class="challenge-copy">
                    <p>Fragmented records and reactive servicing can leave critical equipment unavailable when every second matters.</p>
                    <div class="challenge-note"><span class="trust-icon">✓</span><span>FIRE-PMIS turns inspections and service records into timely, accountable maintenance action.</span></div>
                </div>
            </div>
        </section>

        <section class="section" id="features">
            <div class="section-inner">
                <div class="feature-heading">
                    <div><span class="eyebrow">One readiness platform</span><h2>Built around the way a fire station works.</h2></div>
                    <p>From the apparatus bay to administrative oversight, every role sees the information needed to keep equipment ready.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-card"><span class="feature-number">01</span><span class="feature-symbol">▤</span><h3>Equipment monitoring</h3><p>Track assets, condition, location, and complete service history in one registry.</p></article>
                    <article class="feature-card"><span class="feature-number">02</span><span class="feature-symbol">✓</span><h3>Digital inspections</h3><p>Record standardized equipment checks, findings, and defects as they happen.</p></article>
                    <article class="feature-card"><span class="feature-number">03</span><span class="feature-symbol">⌁</span><h3>ML risk prediction</h3><p>Surface potential risk using equipment telemetry and verified inspection records.</p></article>
                    <article class="feature-card"><span class="feature-number">04</span><span class="feature-symbol">✦</span><h3>Maintenance coordination</h3><p>Plan, assign, and follow preventive and corrective maintenance work.</p></article>
                    <article class="feature-card"><span class="feature-number">05</span><span class="feature-symbol">◷</span><h3>Readiness oversight</h3><p>Keep service status, inspections, and upcoming work visible to authorized staff.</p></article>
                    <article class="feature-card"><span class="feature-number">06</span><span class="feature-symbol">▥</span><h3>Operational reporting</h3><p>Bring asset and maintenance information together for informed decisions.</p></article>
                </div>
            </div>
        </section>

        <section class="section process" id="process">
            <div class="section-inner">
                <div class="process-heading">
                    <span class="eyebrow">From record to readiness</span>
                    <h2>A clear, accountable maintenance loop.</h2>
                    <p>Decision support is provided while authorized BFP personnel remain in control at every step.</p>
                </div>
                <div class="process-steps">
                    <div class="process-step"><span class="step-number">1</span><strong>Record</strong><span>Equipment data</span></div>
                    <div class="process-step"><span class="step-number">2</span><strong>Inspect</strong><span>Condition check</span></div>
                    <div class="process-step"><span class="step-number">3</span><strong>Predict</strong><span>Decision support</span></div>
                    <div class="process-step"><span class="step-number">4</span><strong>Review</strong><span>Personnel oversight</span></div>
                    <div class="process-step"><span class="step-number">5</span><strong>Maintain</strong><span>Verified action</span></div>
                </div>
            </div>
        </section>

        <section class="section" id="contact">
            <div class="section-inner contact-band">
                <div><h2>Keep your station’s equipment ready.</h2><p>Sign in with your assigned station credentials to access FIRE-PMIS.</p></div>
                <a class="button-primary" href="{{ route('login') }}">Secure login <span aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <span class="footer-unit">FIRE-PMIS · BFP Manila</span>
        <span>For official station use · © {{ date('Y') }} Bureau of Fire Protection</span>
    </footer>
</body>
</html>
