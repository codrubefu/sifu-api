<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Sifu este platforma pentru administrarea cluburilor sportive și de arte marțiale: membri, antrenamente, abonamente, plăți și comunicare.">
        <title>Sifu — Administrarea cluburilor sportive</title>
        <style>
            :root {
                color-scheme: light;
                --ink: #172e4d;
                --blue: #2459bc;
                --muted: #506078;
                --surface: #edf3fc;
                --line: #d5dfed;
                --white: #ffffff;
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: var(--white);
                color: var(--ink);
                font-family: 'Trebuchet MS', Arial, sans-serif;
                line-height: 1.65;
            }
            .page { width: min(1120px, 100% - 48px); margin-inline: auto; }
            header { padding-block: 28px; }
            .brand { font-size: 32px; font-weight: 700; letter-spacing: -2px; }
            .hero { padding-block: 64px 80px; max-width: 850px; }
            h1 {
                margin: 0 0 28px;
                font-size: clamp(38px, 6vw, 72px);
                line-height: 1.08;
                letter-spacing: -0.045em;
                text-wrap: balance;
            }
            .intro { max-width: 660px; font-size: 20px; color: var(--muted); }
            .explore {
                display: inline-block;
                margin-top: 16px;
                padding: 12px 22px;
                border-radius: 6px;
                background: var(--blue);
                color: var(--white);
                font-weight: 700;
                text-decoration: none;
            }
            .explore:hover { background: var(--ink); }
            a:focus-visible { outline: 3px solid var(--ink); outline-offset: 5px; }
            .features { background: var(--surface); padding-block: 60px; scroll-margin-top: 24px; }
            h2 { margin: 0 0 36px; font-size: clamp(26px, 4vw, 36px); line-height: 1.2; letter-spacing: -0.025em; }
            .feature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 36px 44px; }
            h3 { margin: 0 0 10px; font-size: 20px; line-height: 1.3; }
            .feature-grid p { margin: 0; color: var(--muted); max-width: 42ch; }
            .organizations { padding-block: 56px; display: grid; grid-template-columns: 1fr 1.3fr; gap: 40px; }
            .organizations h2 { margin: 0; font-size: 28px; }
            .organizations p { margin: 0; max-width: 65ch; color: var(--muted); }
            footer { border-top: 1px solid var(--line); padding-block: 22px; color: var(--muted); font-size: 14px; }
            @media (max-width: 760px) {
                .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .organizations { grid-template-columns: 1fr; gap: 20px; }
            }
            @media (max-width: 480px) {
                .page { width: calc(100% - 40px); }
                .hero { padding-block: 32px 48px; }
                .intro { font-size: 18px; }
                .features { padding-block: 40px; }
                .feature-grid { grid-template-columns: 1fr; gap: 28px; }
            }
        </style>
    </head>
    <body>
        <header class="page">
            <div class="brand">sifu.</div>
        </header>
        <main>
            <section class="hero page" aria-labelledby="welcome-title">
                <h1 id="welcome-title">Mai mult timp pentru sport. Mai multă ordine în club.</h1>
                <p class="intro">Sifu este platforma de administrare pentru cluburi sportive și de arte marțiale. Reunește membrii, antrenamentele, abonamentele și plățile într-un singur loc.</p>
                <a class="explore" href="#functionalitati">Descoperă funcționalitățile</a>
            </section>
            <section class="features" id="functionalitati" aria-labelledby="features-title">
                <div class="page">
                    <h2 id="features-title">Activitatea clubului, organizată zi de zi.</h2>
                    <div class="feature-grid">
                        <article>
                            <h3>Membri și grupe</h3>
                            <p>Gestionează profilurile membrilor, grupele și accesul echipei la informațiile clubului.</p>
                        </article>
                        <article>
                            <h3>Antrenamente și prezențe</h3>
                            <p>Organizează calendarul, înscrierile la evenimente și evidența prezențelor la antrenamente.</p>
                        </article>
                        <article>
                            <h3>Abonamente și servicii</h3>
                            <p>Urmărește serviciile fiecărui membru, perioadele de valabilitate și accesul la activități.</p>
                        </article>
                        <article>
                            <h3>Plăți și documente</h3>
                            <p>Păstrează evidența plăților și a documentelor aferente serviciilor oferite de club.</p>
                        </article>
                        <article>
                            <h3>Comunicare cu membrii</h3>
                            <p>Publică anunțuri și trimite notificări prin e-mail, SMS sau push, în funcție de preferințele membrilor.</p>
                        </article>
                        <article>
                            <h3>Rapoarte și activitate</h3>
                            <p>Consultă rapoarte despre încasări, participare și prezențe pentru o imagine clară a activității.</p>
                        </article>
                    </div>
                </div>
            </section>
            <section class="organizations page" aria-labelledby="organizations-title">
                <h2 id="organizations-title">Un club sau mai multe locații.</h2>
                <p>Sifu permite administrarea mai multor organizații și locații, cu date separate între organizații și drepturi de acces pentru fiecare echipă. Informațiile sunt disponibile în funcție de rolul și responsabilitățile fiecărui utilizator.</p>
            </section>
        </main>
        <footer class="page">Sifu · Administrarea cluburilor sportive și de arte marțiale</footer>
    </body>
</html>
