<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · PlantGrid</title>
    <meta name="description" content="PlantGrid is a portfolio demonstration of manufacturing ICT operations management by Tanui Sila Kipngetich. Explore assets, cases, network, monitoring and plant technology workflows for fictional Northridge Processing.">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}?v={{ @filemtime(public_path('images/favicon-32x32.png')) ?: 1 }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon-48x48.png') }}?v={{ @filemtime(public_path('images/favicon-48x48.png')) ?: 1 }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}?v={{ @filemtime(public_path('images/apple-touch-icon.png')) ?: 1 }}">
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pg-home" data-brand-font="{{ $brandFont ?? 'ibm-plex' }}">
    <a class="pg-skip" href="#content">Skip to content</a>

    <header class="pg-home-bar" data-home-bar>
        <a class="pg-home-brand" href="{{ route('home') }}" aria-label="PlantGrid home">
            @include('partials.brand-logo', ['class' => 'pg-logo pg-logo-home', 'title' => 'PlantGrid, Manufacturing Technology Management'])
        </a>
        <nav class="pg-home-nav" aria-label="Page">
            <a href="#product">Product</a>
            <a href="#plant">Plant story</a>
            <a href="#capabilities">Capabilities</a>
            <a href="#demo">Demo</a>
            <a href="#about">About</a>
        </nav>
        <div class="pg-home-bar-actions">
            @include('partials.theme-menu', [
                'buttonClass' => 'pg-theme-menu-toggle pg-theme-menu-toggle--home',
            ])
            @auth
                <a class="btn btn-sm btn-pg" href="{{ route('dashboard') }}">Open dashboard</a>
            @else
                <a class="btn btn-sm btn-pg-line" href="{{ route('login') }}">Sign in</a>
                <a class="btn btn-sm btn-pg" href="{{ route('login') }}">Try the demo</a>
            @endauth
        </div>
    </header>

    <main id="content">
        <section class="pg-home-hero" aria-label="PlantGrid introduction">
            <div class="pg-home-hero-media" aria-hidden="true">
                <img
                    src="{{ asset('images/home-hero-plant.jpg') }}?v={{ @filemtime(public_path('images/home-hero-plant.jpg')) ?: 1 }}"
                    alt=""
                    width="1920"
                    height="1080"
                    fetchpriority="high"
                    data-home-parallax
                >
            </div>
            <div class="pg-home-hero-shade" aria-hidden="true"></div>
            <div class="pg-home-hero-copy" data-home-reveal>
                <p class="pg-home-brandmark">PlantGrid</p>
                <h1>Manufacturing technology, managed in one console</h1>
                <p class="pg-home-lead">
                    A portfolio demonstration of ICT operations for plant assets, cases, network, servers, maintenance, security and simulated monitoring.
                </p>
                <div class="pg-home-cta">
                    @auth
                        <a class="btn btn-pg" href="{{ route('dashboard') }}">Continue to dashboard</a>
                        <a class="btn btn-pg-line" href="#product">Explore the product</a>
                    @else
                        <a class="btn btn-pg" href="{{ route('login') }}">Try the demo</a>
                        <a class="btn btn-pg-line" href="#demo">How the demo works</a>
                    @endauth
                </div>
            </div>
        </section>

        <section class="pg-home-section" id="product">
            <div class="pg-home-wrap pg-home-feature" data-home-reveal>
                <div class="pg-home-feature-copy">
                    <p class="pg-home-kicker">What PlantGrid is</p>
                    <h2>An ICT operations desk shaped around plant work</h2>
                    <p>
                        PlantGrid is a working portfolio application for manufacturing technology teams.
                        It keeps inventory, support work and infrastructure records in one place so a shift can move from
                        an open case to the asset, the switch, the server and the maintenance note without leaving the console.
                    </p>
                    <p>
                        The plant, people and alerts are fictional. Monitoring samples and backup runs are simulated and labelled as such.
                        Nothing here controls real machinery or live networks.
                    </p>
                    <ul class="pg-home-points">
                        <li>Role-aware access for ICT, production, department and audit work</li>
                        <li>Case workflow from new request through assignment, progress, resolution and closure</li>
                        <li>Registers for assets, network devices, servers, production systems and compliance reviews</li>
                        <li>Northridge Processing sample data ready to explore after sign-in</li>
                    </ul>
                </div>
                <figure class="pg-home-feature-media">
                    <img
                        src="{{ asset('images/home-ops-console.jpg') }}?v={{ @filemtime(public_path('images/home-ops-console.jpg')) ?: 1 }}"
                        alt="ICT operations desk with dual monitors in a plant technology office"
                        width="1200"
                        height="900"
                        loading="lazy"
                    >
                    <figcaption>Operations desk for plant technology work — the console is where cases, assets and infrastructure meet.</figcaption>
                </figure>
            </div>
        </section>

        <section class="pg-home-section pg-home-section-media" id="plant">
            <div class="pg-home-media-band" data-home-reveal>
                <img
                    src="{{ asset('images/home-packaging-floor.jpg') }}?v={{ @filemtime(public_path('images/home-packaging-floor.jpg')) ?: 1 }}"
                    alt="Packaging hall aisle with stainless stations and floor computers"
                    width="1920"
                    height="1080"
                    loading="lazy"
                >
            </div>
            <div class="pg-home-wrap pg-home-story" data-home-reveal>
                <p class="pg-home-kicker">Northridge Processing</p>
                <h2>A fictional plant with a complete ICT story</h2>
                <p>
                    The demonstration is set at Northridge Processing: packaging halls, intake scales, file servers,
                    floor switches and wireless access points. Seeded tickets such as the packaging label printer case
                    connect people, assets and network paths so you can walk a realistic operations narrative end to end.
                </p>
                <div class="pg-home-story-grid">
                    <div>
                        <h3>Packaging and production floor</h3>
                        <p>Label printers, weigh-scale PCs and production equipment records sit next to the cases that affect them.</p>
                    </div>
                    <div>
                        <h3>Plant network fabric</h3>
                        <p>Documented firewalls, core and floor switches, and access points with hostnames, IPs and locations.</p>
                    </div>
                    <div>
                        <h3>Server room and backups</h3>
                        <p>File and application servers, simulated monitoring thresholds and labelled backup demonstration runs.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="pg-home-section" id="capabilities">
            <div class="pg-home-wrap" data-home-reveal>
                <div class="pg-home-section-head">
                    <p class="pg-home-kicker">Capabilities</p>
                    <h2>Modules you can open after sign-in</h2>
                    <p>
                        Each area below is live in the demo. Use them to see how an ICT team would keep plant technology
                        records current during a shift.
                    </p>
                </div>

                <div class="pg-home-capability-list">
                    <article class="pg-home-capability">
                        <h3>Dashboard</h3>
                        <p>Open work, deadlines, recent cases and key figures drawn from the demonstration database so you know where attention is needed first.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Cases</h3>
                        <p>Queue and board views with a status path from new request through assignment, progress, resolution and closure, including notes and history.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Assets</h3>
                        <p>ICT asset register with departments, locations, assignments, condition and maintenance history for laptops, printers, network gear and more.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Network</h3>
                        <p>Devices, subnets, links and topology context for plant connectivity, including tags such as PG-NET-0006 and hostnames like PLT-AP-PACK.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Servers</h3>
                        <p>Host inventory with roles, environments, services and related infrastructure notes for the demonstration plant.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Monitoring</h3>
                        <p>Simulated alerts and thresholds so the operations story includes degraded services without connecting to real plant systems.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Maintenance</h3>
                        <p>Scheduled and in-progress work orders linked to assets and technicians, including packaging-floor interventions.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Production</h3>
                        <p>Production systems, equipment records and operational incident notes for technology that sits on the manufacturing line.</p>
                    </article>
                    <article class="pg-home-capability">
                        <h3>Security, backups and reports</h3>
                        <p>Control reviews, simulated backup results, audit-friendly views and exportable operational summaries for the portfolio walkthrough.</p>
                    </article>
                </div>
            </div>

            <div class="pg-home-wrap pg-home-feature pg-home-feature-reverse" data-home-reveal>
                <figure class="pg-home-feature-media">
                    <img
                        src="{{ asset('images/home-network-rack.jpg') }}?v={{ @filemtime(public_path('images/home-network-rack.jpg')) ?: 1 }}"
                        alt="Industrial network rack with organized patch panels and fiber"
                        width="1200"
                        height="900"
                        loading="lazy"
                    >
                    <figcaption>Infrastructure is documented in place — network and server records stay beside the cases they affect.</figcaption>
                </figure>
                <div class="pg-home-feature-copy">
                    <p class="pg-home-kicker">Infrastructure depth</p>
                    <h2>From floor device to patch panel</h2>
                    <p>
                        PlantGrid treats network and server records as first-class operations data.
                        When a packaging switch slows or a wireless access point needs attention, the console already
                        holds the hostname, management IP, location and linked asset tag.
                    </p>
                    <p>
                        The AI assistant can answer from that same live demo register — ask about open cases,
                        asset tags or device status and it replies from the Northridge sample data.
                    </p>
                </div>
            </div>
        </section>

        <section class="pg-home-section pg-home-section-alt" id="demo">
            <div class="pg-home-wrap" data-home-reveal>
                <div class="pg-home-section-head">
                    <p class="pg-home-kicker">Try the demo</p>
                    <h2>Sign in and walk the Northridge plant story</h2>
                    <p>
                        Every seeded account uses the same password. Start as ICT Manager, technician, employee or auditor
                        to see how permissions change the console.
                    </p>
                </div>

                <div class="pg-home-demo-layout">
                    <div class="pg-home-demo-steps">
                        <h3>How to start</h3>
                        <ol>
                            <li>Open the sign-in page and choose a demonstration account. Password for every account: <span class="pg-mono">PlantGrid-Demo</span>.</li>
                            <li>From the dashboard, open Cases and review ticket <span class="pg-mono">PG-2026-0148</span>, the packaging label printer request.</li>
                            <li>Follow the linked asset and maintenance record, then check Network and Monitoring for the wider picture.</li>
                            <li>Switch roles or ask the PlantGrid Assistant about open cases, alerts and asset tags.</li>
                        </ol>
                        <div class="pg-home-cta">
                            @auth
                                <a class="btn btn-pg" href="{{ route('dashboard') }}">Open the live console</a>
                            @else
                                <a class="btn btn-pg" href="{{ route('login') }}">Try the demo</a>
                            @endauth
                        </div>
                    </div>

                    <div class="pg-home-demo-accounts">
                        <h3>Demonstration accounts</h3>
                        <p class="pg-home-note mb-2">Start with the System Administrator for the fullest console.</p>
                        <table class="pg-home-accounts">
                            <tbody>
                                <tr><th>System Administrator <span class="text-muted">(preferred)</span></th><td class="pg-mono">amina.okello@northridge.example</td></tr>
                                <tr><th>ICT Manager</th><td class="pg-mono">daniel.mwangi@northridge.example</td></tr>
                                <tr><th>ICT Technician</th><td class="pg-mono">faith.cheruiyot@northridge.example</td></tr>
                                <tr><th>Department Manager</th><td class="pg-mono">peter.kamau@northridge.example</td></tr>
                                <tr><th>General Employee</th><td class="pg-mono">grace.wanjiku@northridge.example</td></tr>
                                <tr><th>Auditor</th><td class="pg-mono">helen.achieng@northridge.example</td></tr>
                            </tbody>
                        </table>
                        <p class="pg-home-note">
                            Monitoring samples and backup runs are simulated. They complete the operations story;
                            they do not talk to real plant systems.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="pg-home-section" id="about">
            <div class="pg-home-wrap pg-home-about" data-home-reveal>
                <div class="pg-home-about-copy">
                    <p class="pg-home-kicker">Portfolio</p>
                    <h2>Built by Tanui Sila Kipngetich</h2>
                    <p>
                        PlantGrid is a portfolio website made to show product thinking, careful interface craft
                        and practical ICT operations design in a setting close to real manufacturing technology work.
                        It is an independent educational project and is not affiliated with or endorsed by any third-party CRM vendor.
                    </p>
                    <p>
                        I wanted to prove that an operations product can stay clear without noise: strong brand presence,
                        readable records, role-aware access and workflows that match how ICT teams move work forward.
                    </p>
                    <p>
                        I am ready to collaborate with like-minded people to advance my skills and ideas.
                        If you are building in technology, manufacturing systems, ICT operations or related product work,
                        I welcome a conversation.
                    </p>
                </div>
                <aside class="pg-home-about-aside">
                    <h3>What this demonstration emphasises</h3>
                    <ul class="pg-home-points">
                        <li>End-to-end plant ICT narrative instead of isolated screens</li>
                        <li>Permission-aware console behaviour across six demo roles</li>
                        <li>Honest labelling of simulated monitoring and backups</li>
                        <li>Assistant support for live demo questions and note refining</li>
                    </ul>
                </aside>
            </div>
        </section>

        <section class="pg-home-final" data-home-reveal>
            <div class="pg-home-wrap">
                <p class="pg-home-kicker">Next step</p>
                <h2>Explore the PlantGrid demonstration</h2>
                <p>
                    Sign in, open the dashboard and walk the Northridge sample data.
                    Use it as a living example of how I approach systems, interface and operations product design.
                </p>
                <div class="pg-home-cta">
                    @auth
                        <a class="btn btn-pg" href="{{ route('dashboard') }}">Open dashboard</a>
                    @else
                        <a class="btn btn-pg" href="{{ route('login') }}">Try the demo</a>
                        <a class="btn btn-pg-line" href="{{ route('login') }}">Sign in</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')
</body>
</html>
