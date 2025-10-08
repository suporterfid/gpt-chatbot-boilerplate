<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Website with AI Assistant</title>
    <link rel="stylesheet" href="chatbot.css">
    <style>
        /* Brand visual identity styles */
        :root {
            --brand-primary: #5f6360;
            --brand-secondary: rgb(var(--color_48, 245, 245, 245));
            --brand-accent: rgb(var(--color_49, 80, 120, 255));
            --brand-background: rgb(var(--color_50, 249, 249, 250));
            --brand-text: rgb(var(--color_51, 38, 38, 38));
            --brand-border: rgb(var(--color_52, 208, 208, 210));
            --brand-muted: rgba(38, 38, 38, 0.65);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Arial", "Helvetica", "helvetica-w01-bold", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: var(--brand-text);
            background: var(--brand-background);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .site-header {
            background: var(--brand-secondary);
            border-bottom: 1px solid var(--brand-border);
        }

        .container {
            width: min(1200px, 100%);
            margin: 0 auto;
            padding: 32px 24px;
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 1px solid var(--brand-border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon svg {
            width: 28px;
            height: 28px;
            stroke: var(--brand-primary);
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-name {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: var(--brand-primary);
        }

        .nav-links {
            display: flex;
            gap: 16px;
            font-weight: 500;
            color: var(--brand-muted);
        }

        .nav-links a:focus-visible,
        .cta-button:focus-visible {
            outline: 3px solid var(--brand-accent);
            outline-offset: 3px;
        }

        main {
            flex: 1;
        }

        .hero {
            padding-block: 64px 32px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 24px;
        }

        .hero-content {
            grid-column: span 6;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--brand-primary);
        }

        .hero-subtitle {
            font-size: 20px;
            font-weight: 400;
            color: var(--brand-muted);
        }

        .cta-button {
            align-self: flex-start;
            padding: 12px 28px;
            border-radius: 999px;
            background: var(--brand-accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .cta-button:hover {
            background: color-mix(in srgb, var(--brand-accent) 85%, #ffffff 15%);
            transform: translateY(-2px);
        }

        .hero-visual {
            grid-column: span 6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-card {
            max-width: 420px;
            background: #fff;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid var(--brand-border);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .hero-card img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 24px;
            margin-bottom: 24px;
            border: 4px solid var(--brand-secondary);
        }

        .hero-card h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--brand-text);
            margin-bottom: 12px;
        }

        .hero-card p {
            margin: 0;
            color: var(--brand-muted);
        }

        .section-title {
            font-size: 36px;
            font-weight: 600;
            color: var(--brand-text);
            margin-bottom: 24px;
        }

        .features {
            padding-block: 32px 24px;
            background: #fff;
        }

        .feature-grid {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--brand-background);
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 220px;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--brand-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature-icon svg {
            width: 28px;
            height: 28px;
            stroke: var(--brand-primary);
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .feature-card h3 {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }

        .feature-card p {
            margin: 0;
            color: var(--brand-muted);
        }

        .overview {
            padding-block: 32px 64px;
        }

        .overview-content {
            grid-column: span 7;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .overview-aside {
            grid-column: span 5;
            background: #fff;
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .overview-aside h4 {
            font-size: 20px;
            font-weight: 500;
            margin: 0;
        }

        .overview-aside ul {
            margin: 0;
            padding-left: 20px;
            color: var(--brand-muted);
        }

        .site-footer {
            background: var(--brand-secondary);
            border-top: 1px solid var(--brand-border);
            padding-block: 24px;
        }

        .footer-inner {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: var(--brand-muted);
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .hero-content,
            .hero-visual,
            .overview-content,
            .overview-aside {
                grid-column: span 12;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 24px 16px;
            }

            .grid {
                gap: 16px;
            }

            .hero {
                padding-block: 48px 24px;
            }

            .hero-title {
                font-size: 40px;
            }

            .hero-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <header class="site-header">
            <div class="container header-inner">
                <div class="brand" aria-label="GPT Assistant Boilerplate brand">
                    <span class="brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" aria-hidden="true">
                            <path d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.07-6.07l-2.12 2.12M9.05 14.95l-2.12 2.12m0-10.6 2.12 2.12m8.02 8.02 2.12 2.12" />
                        </svg>
                    </span>
                    <span class="brand-name">GPT Assistant Boilerplate</span>
                </div>
                <nav class="nav-links" aria-label="Primary">
                    <a href="#features">Features</a>
                    <a href="#overview">How it works</a>
                    <a href="#assistant">Assistant</a>
                </nav>
            </div>
        </header>

        <main>
            <section class="hero">
                <div class="container grid">
                    <div class="hero-content">
                        <p class="hero-subtitle">Conversational intelligence for forward-thinking teams.</p>
                        <h1 class="hero-title">Welcome to the GPT Assistant Boilerplate</h1>
                        <p>Empower your applications with a production-ready AI assistant framework. Launch custom experiences that respond instantly, understand context, and help your users get more done.</p>
                        <button class="cta-button" type="button">Launch the assistant</button>
                    </div>
                    <div class="hero-visual" id="assistant">
                        <div class="hero-card" role="presentation">
                            <img src="/assets/assistant-avatar.png" alt="Abstract illustration representing the AI assistant" loading="lazy">
                            <h2>Always-on expertise</h2>
                            <p>Your AI assistant is ready in the bottom-right corner to answer questions and analyze files whenever you need support.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="features" id="features">
                <div class="container grid">
                    <div class="feature-grid">
                        <article class="feature-card">
                            <span class="feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 5v14m7-7H5" />
                                </svg>
                            </span>
                            <h3>Extendable foundation</h3>
                            <p>Plug in your preferred AI models, data sources, and business logic without rebuilding core chat capabilities.</p>
                        </article>
                        <article class="feature-card">
                            <span class="feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M4 7h16M4 12h10M4 17h6" />
                                </svg>
                            </span>
                            <h3>Accessible by design</h3>
                            <p>WCAG-friendly typography, contrast, and layout ensure everyone can interact with your assistant confidently.</p>
                        </article>
                        <article class="feature-card">
                            <span class="feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 3a9 9 0 1 0 9 9" />
                                    <path d="M12 7v5l3 3" />
                                </svg>
                            </span>
                            <h3>Real-time insight</h3>
                            <p>Monitor engagement and iterate fast with built-in analytics events ready for your observability stack.</p>
                        </article>
                        <article class="feature-card">
                            <span class="feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M9 11l3 3L22 4" />
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                </svg>
                            </span>
                            <h3>Secure file handling</h3>
                            <p>Enable uploads with confidence using customizable policies and automatic user consent flows.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="overview" id="overview">
                <div class="container grid">
                    <div class="overview-content">
                        <h2 class="section-title">Deploy conversational AI that feels like your brand</h2>
                        <p>Our boilerplate delivers a polished assistant experience from the first load. It includes responsive layouts, consistent typography, and a floating chat widget that users can open whenever support is needed.</p>
                        <p>Integrate your existing assistants, embed scripts, and keep analytics tracking intact—all while presenting a cohesive design system that reinforces trust and clarity.</p>
                        <p>Use the call-to-action or the floating icon in the corner to open the assistant, upload files, and explore how it can accelerate your workflows.</p>
                    </div>
                    <aside class="overview-aside" aria-label="Assistant quick facts">
                        <h4>Assistant highlights</h4>
                        <ul>
                            <li>Floating widget with custom branding</li>
                            <li>File upload support for deeper analysis</li>
                            <li>Event hooks for monitoring performance</li>
                            <li>Ready for OpenAI Responses API integrations</li>
                        </ul>
                        <p class="hero-subtitle">Your AI assistant is available in the bottom right corner!</p>
                    </aside>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div class="container footer-inner">
                <span>&copy; <?php echo date('Y'); ?> GPT Assistant Boilerplate. All rights reserved.</span>
                <span>Designed for clarity, performance, and innovation.</span>
            </div>
        </footer>
    </div>

    <!-- Enhanced chatbot scripts (cache-busted to avoid stale browser cache) -->
    <?php $cb_ver = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'chatbot-enhanced.js') ?: time(); ?>
    <script src="chatbot-enhanced.js?v=<?php echo $cb_ver; ?>"></script>
    <script>
        // Initialize with your Responses-powered assistant
        const myAssistant = ChatBot.init({
            apiType: 'responses',
            // Dynamic streaming mode: auto for localhost, SSE elsewhere
            streamingMode: (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? 'auto' : 'sse',
            // Use relative endpoint for portability (leave unset to auto-detect based on script path)
            // apiEndpoint can be omitted; keeping explicit here for clarity
            apiEndpoint: '/chat-unified.php',
            mode: 'floating',
            position: 'bottom-right',
            show: false, // Hidden initially, user can open it

            // Responses configuration is resolved on the server from .env
            // (Prompt IDs or other metadata stay server-side)

            // Branding and customization
            title: 'AI Assistant',
            assistant: {
                name: 'My Assistant',
                welcomeMessage: 'Hi! I\'m here to help. You can ask me questions or upload files for analysis.',
                avatar: '/assets/assistant-avatar.png'
            },

            // Theme customization
            theme: {
                primaryColor: '#5f6360',
                backgroundColor: 'rgb(var(--color_50, 249, 249, 250))',
                fontFamily: 'Arial, Helvetica, helvetica-w01-bold, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },

            // File upload support
            enableFileUpload: true,

            // Event handlers
            onConnect: function() {
                console.log('Connected to assistant backend');
            },

            onMessage: function(message) {
                // Track assistant interactions
                if (message.role === 'assistant') {
                    // Only send analytics if Google Analytics gtag is available
                    if (typeof window.gtag === 'function') {
                        window.gtag('event', 'assistant_message', {
                            'message_length': message.content.length
                        });
                    } else {
                        // Optional: comment out or keep for debugging
                        // console.debug('gtag not defined; skipping analytics event');
                    }
                }
            }
        });

        // Optional: Auto-open assistant based on user behavior
        setTimeout(() => {
            if (!localStorage.getItem('assistant_introduced')) {
                myAssistant.show();
                localStorage.setItem('assistant_introduced', 'true');
            }
        }, 5000);
    </script>
</body>
</html>
