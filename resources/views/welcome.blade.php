<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZEEN | Automate. Simplify. Grow.</title>
    <meta name="description" content="Unify WhatsApp, Telegram, Messenger, and Web Chat into one intelligent inbox. Sell products, process payments, qualify leads, and automate customer conversations with AI and seamless human handoff.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js'])

    <style>
        body {
            font-family: 'Jost', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #faf8f5;
            color: #241e19;
        }
        .font-editorial {
            font-family: 'Cormorant Garamond', Georgia, serif;
        }
        .video-progress-bar {
            transition: width 100ms linear;
        }
        .canvas-grid {
            background-size: 28px 28px;
            background-image: 
                linear-gradient(to right, rgba(74, 51, 36, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(74, 51, 36, 0.04) 1px, transparent 1px);
        }
    </style>
</head>
<body class="bg-[#faf8f5] text-[#241e19] antialiased selection:bg-[#7b5537] selection:text-white">


    <!-- HEADER / NAVIGATION -->
    <header class="sticky top-0 z-40 bg-[#faf8f5]/95 backdrop-blur-md border-b border-[#e8e2d9] transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
            
            <!-- Brand Logo (Logo only, prominent & visible) -->
            <a href="/" class="flex items-center group py-1" aria-label="ZEEN Home">
                <img 
                    src="/images/logo.png" 
                    alt="ZEEN" 
                    class="h-14 sm:h-16 w-auto object-contain transition-transform duration-200 group-hover:scale-105 drop-shadow-sm" 
                />
            </a>

            <!-- Navigation Links -->
            <!-- Navigation Links (Streamlined) -->
            <nav class="hidden md:flex items-center gap-8 text-xs font-semibold uppercase tracking-wider text-[#66584d]">
                <a href="#solutions" class="hover:text-[#241e19] transition-colors">Solutions</a>
                <a href="#directives" class="hover:text-[#241e19] transition-colors">How It Works</a>
                <a href="#pricing" class="hover:text-[#241e19] transition-colors">Pricing</a>
                <a href="#contact" class="hover:text-[#241e19] transition-colors">Contact</a>
            </nav>

            <!-- Action CTAs -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-[#291e17] text-[#faf8f5] hover:bg-[#38261a] transition-all shadow-sm">
                        Open Dashboard
                        <svg class="w-3.5 h-3.5 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-bold uppercase tracking-wider text-[#4a3324] hover:text-[#241e19] px-3 py-2 transition-colors">
                        Sign In
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-[#291e17] text-[#faf8f5] hover:bg-[#38261a] transition-all shadow-sm">
                            Get Started
                            <svg class="w-3.5 h-3.5 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative pt-16 pb-20 md:pt-24 md:pb-28 overflow-hidden canvas-grid border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl mx-auto text-center">
                

                <!-- Main Headline -->
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-normal tracking-tight text-[#241e19] font-editorial leading-[1.08] mb-6">
                    Turn Every Customer Chat Into <span class="italic font-medium text-[#7b5537]">Automated Revenue</span> & Repeat Orders.
                </h1>

                <!-- Subtitle -->
                <p class="text-base sm:text-lg text-[#66584d] font-normal leading-relaxed mb-8 max-w-2xl mx-auto">
                    ZEEN gives your business a smart AI agent that handles customer messages 24/7, manages your product catalog, and closes sales directly inside WhatsApp, Telegram, and Messenger — so you never miss a paying customer.
                </p>

                <!-- Dual Action CTAs -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-[#291e17] text-[#faf8f5] hover:bg-[#38261a] transition-all shadow-md">
                            Go To Workspace
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-[#291e17] text-[#faf8f5] hover:bg-[#38261a] transition-all shadow-md">
                            Get Started Now
                        </a>
                    @endauth
                    <a href="#interactive-demo" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-white text-[#241e19] border border-[#e8e2d9] hover:bg-[#f5efe6] transition-all">
                        <svg class="w-4 h-4 mr-2 text-[#7b5537]" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Interactive Tour
                    </a>
                </div>

                <!-- Trust Metrics Bar -->
                <div class="mt-12 pt-8 border-t border-[#e8e2d9]/60 grid grid-cols-2 md:grid-cols-4 gap-6 text-left">
                    <div class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-[#241e19]">Unified Inbox</p>
                            <p class="text-[11px] text-[#66584d]">WhatsApp, Telegram, Messenger in one screen</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-[#241e19]">AI + Human Handoff</p>
                            <p class="text-[11px] text-[#66584d]">Intelligent bot with 1-click staff takeover</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-[#241e19]">In-Chat Catalog Ops</p>
                            <p class="text-[11px] text-[#66584d]">Manage stock with /stock and /add commands</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-[#241e19]">Instant Checkout</p>
                            <p class="text-[11px] text-[#66584d]">Direct Paystack & Stripe in-chat payment links</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- METRICS STRIP -->
    <section class="bg-[#291e17] text-[#e8e2d9] py-12 border-b border-[#3b2c22]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-y md:divide-y-0 md:divide-x divide-[#3b2c22]">
                <div class="pt-4 md:pt-0">
                    <p class="text-3xl sm:text-4xl font-light font-editorial text-white">4.8x</p>
                    <p class="text-xs uppercase tracking-wider text-stone-400 mt-1 font-semibold">Faster Inquiry-To-Sale Conversion</p>
                </div>
                <div class="pt-4 md:pt-0">
                    <p class="text-3xl sm:text-4xl font-light font-editorial text-white">0s</p>
                    <p class="text-xs uppercase tracking-wider text-stone-400 mt-1 font-semibold">First Response Time via AI Agent</p>
                </div>
                <div class="pt-4 md:pt-0">
                    <p class="text-3xl sm:text-4xl font-light font-editorial text-white">100%</p>
                    <p class="text-xs uppercase tracking-wider text-stone-400 mt-1 font-semibold">Centralized Conversation History</p>
                </div>
                <div class="pt-4 md:pt-0">
                    <p class="text-3xl sm:text-4xl font-light font-editorial text-white">1 Click</p>
                    <p class="text-xs uppercase tracking-wider text-stone-400 mt-1 font-semibold">Staff Takeover & Payment Link Gen</p>
                </div>
            </div>
        </div>
    </section>

    <!-- DIRECTIVES: HOW USERS CAN USE THIS PLATFORM EFFECTIVELY -->
    <section id="directives" class="py-20 bg-white border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-2xl mx-auto text-center mb-16">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Operational Directives</span>
                <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2">
                    How To Automate Your Sales In 5 Directives
                </h2>
                <p class="text-sm text-[#66584d] mt-3">
                    A proven, step-by-step methodology to connect your social channels, automate inquiries, and close sales without switching apps.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
                
                <!-- Directive 1 -->
                <div class="p-6 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] relative flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-[#291e17] text-[#e8e2d9]">01</span>
                            <svg class="w-5 h-5 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-[#241e19] mb-2">Connect Channels</h3>
                        <p class="text-xs text-[#66584d] leading-relaxed">
                            Link your WhatsApp Business phone, Telegram bot, Facebook Messenger page, and embed the web chat widget with standard webhook tokens.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#e8e2d9] text-[11px] font-mono text-[#7b5537]">
                        Setup Time: ~2 mins
                    </div>
                </div>

                <!-- Directive 2 -->
                <div class="p-6 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] relative flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-[#291e17] text-[#e8e2d9]">02</span>
                            <svg class="w-5 h-5 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-[#241e19] mb-2">Sync Catalog & Stock</h3>
                        <p class="text-xs text-[#66584d] leading-relaxed">
                            Add your products, room types, or service packages with images, prices, and stock numbers. Update inventory via web or directly inside chat.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#e8e2d9] text-[11px] font-mono text-[#7b5537]">
                        Live Inventory Sync
                    </div>
                </div>

                <!-- Directive 3 -->
                <div class="p-6 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] relative flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-[#291e17] text-[#e8e2d9]">03</span>
                            <svg class="w-5 h-5 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-[#241e19] mb-2">Train AI Assistant</h3>
                        <p class="text-xs text-[#66584d] leading-relaxed">
                            Equip the AI agent with your business FAQs, store hours, return policies, and pricing rules. The AI replies in seconds 24/7 without delays.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#e8e2d9] text-[11px] font-mono text-[#7b5537]">
                        24/7 Instant Responses
                    </div>
                </div>

                <!-- Directive 4 -->
                <div class="p-6 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] relative flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-[#291e17] text-[#e8e2d9]">04</span>
                            <svg class="w-5 h-5 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-[#241e19] mb-2">In-Chat Checkout</h3>
                        <p class="text-xs text-[#66584d] leading-relaxed">
                            Generate and share interactive product cards with direct Paystack or Stripe checkout links. Customers pay securely without leaving the chat.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#e8e2d9] text-[11px] font-mono text-[#7b5537]">
                        Sub-second Invoicing
                    </div>
                </div>

                <!-- Directive 5 -->
                <div class="p-6 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] relative flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-[#291e17] text-[#e8e2d9]">05</span>
                            <svg class="w-5 h-5 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-[#241e19] mb-2">Manage & Escalate</h3>
                        <p class="text-xs text-[#66584d] leading-relaxed">
                            Your human team can take over conversations at any time with one click. Track leads, appointment bookings, revenue telemetry, and order statuses.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#e8e2d9] text-[11px] font-mono text-[#7b5537]">
                        Seamless Human Handoff
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- INTERACTIVE DEMONSTRATION PLAYER (SIMULATED VIDEO WALKTHROUGH) -->
    <section id="interactive-demo" class="py-20 bg-[#1a110b] text-[#e8e2d9] border-b border-[#3b2c22]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-[#3b2c22] text-[#f59e0b] text-[11px] font-mono uppercase tracking-wider mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Live Interactive Demonstration
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-white">
                        Experience The ZEEN Omnichannel Engine
                    </h2>
                    <p class="text-sm text-stone-400 mt-1 max-w-xl">
                        Walk through the complete operational journey: connecting messaging channels, managing product catalog, AI conversational selling, automated checkout, and team handoff.
                    </p>
                </div>
                
                <!-- Player Controls -->
                <div class="flex items-center gap-3">
                    <button id="demo-play-btn" onclick="toggleDemoPlayback()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#291e17] border border-[#4a3324] hover:bg-[#38261a] text-xs font-semibold uppercase tracking-wider text-white transition-all cursor-pointer">
                        <svg id="demo-play-icon" class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                        <span id="demo-play-label">Pause</span>
                    </button>
                    <button onclick="resetDemoPlayback()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-[#291e17] border border-[#4a3324] hover:bg-[#38261a] text-xs font-medium text-stone-300 transition-all cursor-pointer" title="Replay From Beginning">
                        <svg class="w-4 h-4 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Restart
                    </button>
                </div>
            </div>

            <!-- PLAYER CONSOLE FRAME -->
            <div class="rounded-2xl border border-[#3b2c22] bg-[#221812] shadow-2xl overflow-hidden">
                
                <!-- Player Top Bar -->
                <div class="px-5 py-3.5 bg-[#170e08] border-b border-[#3b2c22] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full bg-red-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                        <span class="text-xs font-mono text-stone-400 ml-2 hidden sm:inline">ZEEN Commerce Suite — Workflow Walkthrough</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-timer" class="text-xs font-mono text-[#f59e0b]">00:18 / 01:30</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-emerald-950 text-emerald-300 border border-emerald-800">
                            Status: Live Simulation
                        </span>
                    </div>
                </div>

                <!-- Video Progress Scrubber Bar -->
                <div class="w-full bg-[#291e17] h-1.5 cursor-pointer relative" onclick="handleScrubberClick(event)">
                    <div id="demo-progress-bar" class="bg-[#f59e0b] h-full video-progress-bar w-[20%]"></div>
                </div>

                <!-- Interactive Chapter Tabs -->
                <div class="grid grid-cols-2 sm:grid-cols-5 border-b border-[#3b2c22] bg-[#1a110b] text-xs divide-x divide-[#3b2c22]">
                    <button onclick="setDemoStep(1)" id="tab-step-1" class="demo-tab-btn py-3 px-3 text-left transition-all bg-[#291e17] text-white font-semibold flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#f59e0b] text-[#1a110b] flex items-center justify-center font-mono text-[10px] font-bold">1</span>
                        <span class="truncate">Connect Channels</span>
                    </button>
                    <button onclick="setDemoStep(2)" id="tab-step-2" class="demo-tab-btn py-3 px-3 text-left transition-all text-stone-400 hover:text-white flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#3b2c22] text-stone-300 flex items-center justify-center font-mono text-[10px] font-bold">2</span>
                        <span class="truncate">Catalog & Stock</span>
                    </button>
                    <button onclick="setDemoStep(3)" id="tab-step-3" class="demo-tab-btn py-3 px-3 text-left transition-all text-stone-400 hover:text-white flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#3b2c22] text-stone-300 flex items-center justify-center font-mono text-[10px] font-bold">3</span>
                        <span class="truncate">AI Sales Assistant</span>
                    </button>
                    <button onclick="setDemoStep(4)" id="tab-step-4" class="demo-tab-btn py-3 px-3 text-left transition-all text-stone-400 hover:text-white flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#3b2c22] text-stone-300 flex items-center justify-center font-mono text-[10px] font-bold">4</span>
                        <span class="truncate">In-Chat Checkout</span>
                    </button>
                    <button onclick="setDemoStep(5)" id="tab-step-5" class="demo-tab-btn py-3 px-3 text-left transition-all text-stone-400 hover:text-white flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#3b2c22] text-stone-300 flex items-center justify-center font-mono text-[10px] font-bold">5</span>
                        <span class="truncate">Inbox & Takeover</span>
                    </button>
                </div>

                <!-- VIEWPORT FRAMES -->
                <div class="p-6 md:p-10 min-h-[460px] flex items-center justify-center bg-[#170e08]/90">
                    
                    <!-- FRAME 1: CONNECT CHANNELS -->
                    <div id="frame-step-1" class="demo-frame w-full max-w-4xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-xs font-mono uppercase text-[#f59e0b] tracking-wider font-semibold">Step 01 of 05</span>
                                <h3 class="text-2xl font-editorial font-normal text-white mt-1 mb-3">
                                    Instant Omnichannel Channel Ingestion
                                </h3>
                                <p class="text-xs text-stone-300 leading-relaxed mb-4">
                                    Connect WhatsApp Cloud API, Telegram Bot token, Facebook Messenger, and Web Chat in 2 minutes. ZEEN handles inbound webhooks, token validation, media downloads, and customer contact creation automatically.
                                </p>
                                <ul class="space-y-2 text-xs text-stone-400 font-mono">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        WhatsApp Official Cloud API Integration
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Telegram Bot Long-Polling & Webhooks
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Unified Contact Identity Across All Channels
                                    </li>
                                </ul>
                            </div>
                            
                            <!-- Channel Ingestion Console Mockup -->
                            <div class="rounded-xl bg-[#0d0805] border border-[#3b2c22] p-5 font-mono text-[11px] shadow-xl space-y-3">
                                <div class="flex items-center justify-between pb-3 border-b border-[#291e17] text-stone-400 text-xs">
                                    <span class="font-bold text-white">Active Channel Gateways</span>
                                    <span class="text-emerald-400">All Systems Normal</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#1a110b] border border-[#3b2c22] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-emerald-950 text-emerald-400 flex items-center justify-center font-bold text-xs">WA</div>
                                        <div>
                                            <p class="font-bold text-white text-xs">WhatsApp Business API</p>
                                            <p class="text-[10px] text-stone-400">+234 810 000 9284 • Cloud API</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-900/60 text-emerald-300 font-bold">CONNECTED</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#1a110b] border border-[#3b2c22] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-sky-950 text-sky-400 flex items-center justify-center font-bold text-xs">TG</div>
                                        <div>
                                            <p class="font-bold text-white text-xs">Telegram Bot Gateway</p>
                                            <p class="text-[10px] text-stone-400">@ZeenCommerceBot</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-900/60 text-emerald-300 font-bold">CONNECTED</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#1a110b] border border-[#3b2c22] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-amber-950 text-amber-400 flex items-center justify-center font-bold text-xs">WB</div>
                                        <div>
                                            <p class="font-bold text-white text-xs">Embedded Web Chat Widget</p>
                                            <p class="text-[10px] text-stone-400">Active on your storefront domain</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-900/60 text-emerald-300 font-bold">CONNECTED</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FRAME 2: CATALOG & IN-CHAT OPS -->
                    <div id="frame-step-2" class="demo-frame hidden w-full max-w-4xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-xs font-mono uppercase text-[#f59e0b] tracking-wider font-semibold">Step 02 of 05</span>
                                <h3 class="text-2xl font-editorial font-normal text-white mt-1 mb-3">
                                    In-Chat Inventory & Product Catalog
                                </h3>
                                <p class="text-xs text-stone-300 leading-relaxed mb-4">
                                    Manage inventory without logging into heavy ERPs. You and your staff can query stock, update prices, and share product cards right from your messaging app using quick slash commands.
                                </p>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="p-3 rounded-lg bg-[#291e17] border border-[#3b2c22]">
                                        <span class="text-[10px] text-stone-400 uppercase font-mono">Chat Ops</span>
                                        <p class="font-bold text-white mt-1">/stock & /price</p>
                                        <p class="text-[11px] text-stone-300">Fast inventory lookup</p>
                                    </div>
                                    <div class="p-3 rounded-lg bg-[#291e17] border border-[#3b2c22]">
                                        <span class="text-[10px] text-stone-400 uppercase font-mono">Interactive Cards</span>
                                        <p class="font-bold text-white mt-1">Direct Sharing</p>
                                        <p class="text-[11px] text-stone-300">Image, price & buy button</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Catalog Visual Mockup -->
                            <div class="rounded-xl bg-[#221812] border border-[#3b2c22] p-5 shadow-xl space-y-3 font-sans">
                                <div class="flex items-center justify-between text-xs pb-2 border-b border-[#3b2c22]">
                                    <span class="font-bold text-white">Live Catalog Items</span>
                                    <span class="text-[10px] font-mono text-[#f59e0b]">+ Add New Item</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#291e17] border border-[#4a3324] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-[#1a110b] flex items-center justify-center text-xs font-bold text-stone-300 border border-[#3b2c22]">IMG</div>
                                        <div>
                                            <p class="text-xs font-bold text-white">Bespoke Italian Leather Bag</p>
                                            <p class="text-[10px] text-stone-400 font-mono">₦45,000 • In Stock (18 units)</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 rounded text-[10px] font-mono bg-emerald-900/60 text-emerald-300">ACTIVE</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#291e17] border border-[#4a3324] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-[#1a110b] flex items-center justify-center text-xs font-bold text-stone-300 border border-[#3b2c22]">IMG</div>
                                        <div>
                                            <p class="text-xs font-bold text-white">Luxury Studio Suite Booking</p>
                                            <p class="text-[10px] text-stone-400 font-mono">₦85,000 / Night • 4 Available</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 rounded text-[10px] font-mono bg-emerald-900/60 text-emerald-300">ACTIVE</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FRAME 3: AI SALES AGENT -->
                    <div id="frame-step-3" class="demo-frame hidden w-full max-w-4xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-xs font-mono uppercase text-[#f59e0b] tracking-wider font-semibold">Step 03 of 05</span>
                                <h3 class="text-2xl font-editorial font-normal text-white mt-1 mb-3">
                                    24/7 AI Conversational Sales Assistant
                                </h3>
                                <p class="text-xs text-stone-300 leading-relaxed mb-4">
                                    When a customer messages on WhatsApp asking about availability, sizes, or rates, ZEEN's AI engine responds immediately in natural, brand-aligned language. It references your real-time catalog and qualifies customer intent.
                                </p>
                                <div class="flex items-center gap-4 text-xs text-stone-300">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Sub-second response time
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Zero hallucinated stock
                                    </span>
                                </div>
                            </div>

                            <!-- Live Chat Dialogue Simulation -->
                            <div class="rounded-2xl bg-[#0c1317] border border-[#3b2c22] p-5 shadow-2xl text-xs font-sans max-w-sm mx-auto w-full">
                                <div class="flex items-center justify-between pb-3 mb-3 border-b border-stone-800 text-stone-300">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-emerald-600 flex items-center justify-center font-bold text-white text-xs">C</div>
                                        <div>
                                            <p class="font-bold text-white text-xs">Sarah Jenkins</p>
                                            <p class="text-[10px] text-emerald-400">WhatsApp Inbound</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-mono text-stone-500">Just Now</span>
                                </div>
                                <div class="space-y-3">
                                    <!-- Inbound Message -->
                                    <div class="bg-[#1f2c34] text-stone-200 p-3 rounded-xl rounded-tl-none text-xs">
                                        Hi! Do you have the Italian Leather Bag in brown, and how much is it?
                                    </div>
                                    <!-- Outbound AI Reply -->
                                    <div class="bg-[#005c4b] text-white p-3 rounded-xl rounded-tr-none text-xs ml-auto max-w-[90%] space-y-1.5">
                                        <div class="flex items-center gap-1.5 text-[10px] text-emerald-200 font-mono">
                                            <span>&#9889; AI Concierge</span>
                                        </div>
                                        <p>Hello Sarah! Yes, we have 18 units of the Bespoke Italian Leather Bag in stock right now for ₦45,000. Would you like me to send you the direct order link?</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FRAME 4: IN-CHAT CHECKOUT -->
                    <div id="frame-step-4" class="demo-frame hidden w-full max-w-4xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-xs font-mono uppercase text-[#f59e0b] tracking-wider font-semibold">Step 04 of 05</span>
                                <h3 class="text-2xl font-editorial font-normal text-white mt-1 mb-3">
                                    Instant Conversational Payment Links
                                </h3>
                                <p class="text-xs text-stone-300 leading-relaxed mb-4">
                                    Close deals immediately while the buyer is still engaged in the conversation. ZEEN automatically creates signed Paystack or Stripe checkout sessions, tracks payment completion via webhooks, and sends instant digital receipts.
                                </p>
                                <div class="p-3 rounded-lg bg-[#291e17] border border-[#3b2c22] text-xs">
                                    <p class="font-bold text-white mb-1">Automated Fulfillment</p>
                                    <p class="text-[11px] text-stone-300">As soon as the payment callback fires, stock is decremented and an order confirmation is posted to your staff.</p>
                                </div>
                            </div>

                            <!-- Payment Card Visual -->
                            <div class="rounded-2xl bg-[#0c1317] border border-[#3b2c22] p-5 shadow-2xl text-xs font-sans max-w-sm mx-auto w-full space-y-3">
                                <div class="bg-[#1f2c34] text-stone-200 p-3.5 rounded-xl border border-stone-700 space-y-2">
                                    <div class="flex items-center justify-between text-xs font-bold text-white border-b border-stone-700 pb-2">
                                        <span>Order #ZN-8194</span>
                                        <span class="text-emerald-400 font-mono">₦45,000</span>
                                    </div>
                                    <p class="text-xs text-stone-300">Bespoke Italian Leather Bag (Espresso Brown)</p>
                                    <div class="p-2 rounded bg-black/40 border border-amber-500/40 text-center font-mono">
                                        <span class="text-xs text-emerald-400 font-bold block">&#10003; PAYMENT VERIFIED</span>
                                        <span class="text-[10px] text-stone-400">Transaction Ref: PSTK_849204918</span>
                                    </div>
                                    <div class="pt-2 text-center">
                                        <span class="inline-block px-3 py-1.5 rounded-md bg-emerald-600 text-white font-bold text-[11px]">
                                            View Receipt & Tracking
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FRAME 5: INBOX & TEAM TAKEOVER -->
                    <div id="frame-step-5" class="demo-frame hidden w-full max-w-4xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-xs font-mono uppercase text-[#f59e0b] tracking-wider font-semibold">Step 05 of 05</span>
                                <h3 class="text-2xl font-editorial font-normal text-white mt-1 mb-3">
                                    Unified Inbox with 1-Click Human Handoff
                                </h3>
                                <p class="text-xs text-stone-300 leading-relaxed mb-4">
                                    View conversations from every channel in one prioritized inbox. When an inquiry requires custom negotiation, personal consultation, or VIP service, your human agents can step in instantly with a single click.
                                </p>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="p-3 rounded bg-[#291e17] border border-[#3b2c22]">
                                        <span class="text-[10px] text-stone-400 font-mono">Open Chats</span>
                                        <p class="text-lg font-bold text-white">48 Active</p>
                                        <p class="text-[10px] text-emerald-400">82% Handled by AI</p>
                                    </div>
                                    <div class="p-3 rounded bg-[#291e17] border border-[#3b2c22]">
                                        <span class="text-[10px] text-stone-400 font-mono">Today's Sales</span>
                                        <p class="text-lg font-bold text-white">₦620,000</p>
                                        <p class="text-[10px] text-emerald-400">14 Orders closed</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Inbox Mockup Visual -->
                            <div class="rounded-xl bg-[#221812] border border-[#3b2c22] p-5 shadow-xl space-y-3 font-sans">
                                <div class="flex items-center justify-between text-xs pb-2 border-b border-[#3b2c22]">
                                    <span class="font-bold text-white">Unified Workspace Inbox</span>
                                    <span class="text-[10px] font-mono text-emerald-400">Live Agent: Online</span>
                                </div>
                                <div class="p-3 rounded-lg bg-[#291e17] border border-[#4a3324] flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-bold text-white">David Kalu (WhatsApp)</p>
                                        <p class="text-[10px] text-stone-400">"Can I pick this up tomorrow at 2pm?"</p>
                                    </div>
                                    <button class="px-2.5 py-1 rounded text-[10px] font-bold uppercase bg-[#f59e0b] text-[#1a110b]">
                                        Take Over
                                    </button>
                                </div>
                                <div class="p-3 rounded-lg bg-[#291e17] border border-[#4a3324] flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-bold text-white">Amara Okafor (Telegram)</p>
                                        <p class="text-[10px] text-stone-400">"Payment sent! Waiting for delivery confirmation."</p>
                                    </div>
                                    <span class="px-2 py-1 rounded text-[10px] font-mono bg-emerald-950 text-emerald-400">PAID</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Bottom Bar -->
                <div class="px-5 py-3 bg-[#170e08] border-t border-[#3b2c22] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-stone-400">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Interactive Walkthrough Active — Click any chapter above to jump immediately.</span>
                    </div>
                    <div>
                        <a href="{{ route('register') }}" class="text-[#f59e0b] hover:text-white font-semibold transition-colors">
                            Launch Your Workspace &rarr;
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- SOLUTIONS: RETAIL COMMERCE VS SERVICES & HOSPITALITY -->
    <section id="solutions" class="py-20 bg-[#faf8f5] border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-2xl mx-auto text-center mb-16">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Specialized Workflows</span>
                <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2">
                    Engineered For High-Volume Commerce & Service Venues
                </h2>
                <p class="text-sm text-[#66584d] mt-3">
                    Whether you are retailing physical products or managing boutique hospitality bookings, ZEEN automates conversational operations from inquiry to payment.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Solution 1: Retail & E-Commerce -->
                <div class="rounded-2xl bg-white border border-[#e8e2d9] p-8 sm:p-10 shadow-sm flex flex-col justify-between hover:border-[#7b5537] transition-all">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#f5efe6] text-[#4a3324] text-xs font-bold uppercase tracking-wider mb-6">
                            Retail, Fashion & Online Stores
                        </div>
                        <h3 class="text-2xl font-editorial font-normal text-[#241e19] mb-4">
                            24/7 Conversational Selling & Inventory Sync
                        </h3>
                        <p class="text-xs sm:text-sm text-[#66584d] leading-relaxed mb-6">
                            Turn your social DMs into an automated sales team. Customers browsing Instagram or WhatsApp can ask questions, verify stock, see pictures, and purchase immediately without leaving the chat.
                        </p>
                        
                        <div class="space-y-3.5 text-xs text-[#241e19]">
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Instant In-Chat Catalog Cards:</strong> Send structured product cards with images, variants, and direct buy buttons.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Real-Time Stock Decrement:</strong> Inventory levels decrement automatically upon payment verification.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Abandoned Inquiry Recovery:</strong> Automatically re-engage interested buyers who dropped off before checkout.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Multi-Staff Coordination:</strong> Assign conversations to floor staff or customer service reps in real time.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-[#e8e2d9] flex items-center justify-between">
                        <span class="text-xs text-[#66584d]">Instant Workspace Setup</span>
                        <a href="{{ route('register') }}" class="text-xs font-bold uppercase tracking-wider text-[#4a3324] hover:text-[#7b5537] transition-colors">
                            Launch Retail Store &rarr;
                        </a>
                    </div>
                </div>

                <!-- Solution 2: Hospitality, Real Estate & Appointments -->
                <div class="rounded-2xl bg-white border border-[#e8e2d9] p-8 sm:p-10 shadow-sm flex flex-col justify-between hover:border-[#7b5537] transition-all">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#f5efe6] text-[#4a3324] text-xs font-bold uppercase tracking-wider mb-6">
                            Hospitality, Shortlets & Services
                        </div>
                        <h3 class="text-2xl font-editorial font-normal text-[#241e19] mb-4">
                            Automated Booking, Check-Ins & Concierge
                        </h3>
                        <p class="text-xs sm:text-sm text-[#66584d] leading-relaxed mb-6">
                            Let guests check dates, book appointments, receive confirmation receipts, and access concierge support directly in their favorite messaging channels.
                        </p>
                        
                        <div class="space-y-3.5 text-xs text-[#241e19]">
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Automated Appointment Scheduling:</strong> Book slots with real-time calendar availability and confirmation.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Deposit & Full Payment Collection:</strong> Collect non-refundable deposits or full payments via Paystack / Stripe.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Lead Qualification & CRM Stages:</strong> Automatically tag leads (New, Contacted, Negotiating, Converted).</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-[#7b5537] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Automated Reminders:</strong> Send scheduled check-in reminders and directions before arrival.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-[#e8e2d9] flex items-center justify-between">
                        <span class="text-xs text-[#66584d]">Custom Venue Onboarding</span>
                        <a href="#contact" class="text-xs font-bold uppercase tracking-wider text-[#4a3324] hover:text-[#7b5537] transition-colors">
                            Book Consultation &rarr;
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- SUPPORTED CHANNELS & PAYMENT GATEWAYS -->
    <section id="channels" class="py-20 bg-white border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-2xl mx-auto text-center mb-14">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Seamless Ecosystem</span>
                <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2">
                    Native Channel & Payment Integrations
                </h2>
                <p class="text-sm text-[#66584d] mt-3">
                    Connect with your customers wherever they are and get paid with industry-standard, bank-grade checkout providers.
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
                
                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        WA
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">WhatsApp</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Cloud Business API</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Native Webhooks</span>
                </div>

                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        TG
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">Telegram</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Bot Engine API</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Zero-Latency</span>
                </div>

                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        MS
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">Messenger</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Facebook Pages</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Direct Sync</span>
                </div>

                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        WB
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">Web Widget</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Embed Script</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Lightweight</span>
                </div>

                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        PS
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">Paystack</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Cards, Transfers, USSD</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Instant Settle</span>
                </div>

                <div class="p-5 rounded-xl bg-[#faf8f5] border border-[#e8e2d9] flex flex-col items-center justify-center hover:shadow-xs transition-shadow">
                    <div class="h-10 w-10 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs font-mono mb-3">
                        ST
                    </div>
                    <h4 class="text-xs font-bold text-[#241e19]">Stripe</h4>
                    <p class="text-[10px] text-[#66584d] mt-1 font-mono">Global Payments</p>
                    <span class="mt-2 text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Multi-Currency</span>
                </div>

            </div>

        </div>
    </section>

    <!-- PRICING TIERS -->
    <section id="pricing" class="py-20 bg-[#faf8f5] border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-2xl mx-auto text-center mb-16">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Transparent Pricing</span>
                <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2">
                    Simple Plans For Growing Brands
                </h2>
                <p class="text-sm text-[#66584d] mt-3">
                    Start on a single channel or empower your entire sales team across multi-channel customer operations.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                
                <!-- Plan 1 -->
                <div class="rounded-2xl bg-white border border-[#e8e2d9] p-8 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-[#4a3324] mb-2">Starter Store</div>
                        <p class="text-3xl font-light font-editorial text-[#241e19]">₦15,000 <span class="text-xs font-sans text-[#66584d]">/ mo</span></p>
                        <p class="text-[11px] text-[#66584d] mt-1">Single business workspace (approx. $15 USD)</p>
                        <p class="text-xs text-[#66584d] mt-4 pb-6 border-b border-[#e8e2d9]">
                            Essential automated inbox, product catalog, and conversational payments for independent merchants and boutiques.
                        </p>
                        <ul class="space-y-3 text-xs text-[#241e19] mt-6">
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                1 Connected Messaging Channel
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Up to 50 Product Catalog Items
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                24/7 AI Auto-Replies & FAQ Handling
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Paystack & Stripe Checkout Integration
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-6">
                        <a href="{{ route('register') }}" class="block w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-center bg-[#faf8f5] text-[#241e19] border border-[#e8e2d9] hover:bg-[#291e17] hover:text-white transition-all">
                            Choose Starter
                        </a>
                    </div>
                </div>

                <!-- Plan 2: Pro Business (Featured) -->
                <div class="rounded-2xl bg-[#291e17] text-white border-2 border-[#7b5537] p-8 flex flex-col justify-between shadow-xl relative scale-105">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-[#f59e0b] text-[#1a110b] font-mono text-[10px] font-bold uppercase tracking-wider">
                        Most Popular
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">Omnichannel Pro</div>
                        <p class="text-3xl font-light font-editorial text-white">₦45,000 <span class="text-xs font-sans text-stone-400">/ mo</span></p>
                        <p class="text-[11px] text-stone-400 mt-1">Multi-Channel & Multi-Staff (approx. $45 USD)</p>
                        <p class="text-xs text-stone-300 mt-4 pb-6 border-b border-[#3b2c22]">
                            For scaling retail brands, restaurants, clinics, and hospitality teams needing automated routing and full CRM power.
                        </p>
                        <ul class="space-y-3 text-xs text-stone-200 mt-6">
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Unlimited Channels (WhatsApp, Telegram, Web)
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Unlimited Product Catalog & Stock Tracking
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                In-Chat Staff Operations (/stock, /price, /add)
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                5 Team Staff Seats with Role Permissions
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-[#f59e0b]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Lead CRM & Appointment Booking Module
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-6">
                        <a href="{{ route('register') }}?plan=pro" class="block w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-center bg-[#f59e0b] text-[#1a110b] hover:bg-amber-400 transition-all shadow-md">
                            Choose Omnichannel Pro
                        </a>
                    </div>
                </div>

                <!-- Plan 3: Enterprise -->
                <div class="rounded-2xl bg-white border border-[#e8e2d9] p-8 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-[#4a3324] mb-2">Enterprise Custom</div>
                        <p class="text-3xl font-light font-editorial text-[#241e19]">Custom <span class="text-xs font-sans text-[#66584d]">Volume</span></p>
                        <p class="text-[11px] text-[#66584d] mt-1">Multi-Brand & Enterprise Groups</p>
                        <p class="text-xs text-[#66584d] mt-4 pb-6 border-b border-[#e8e2d9]">
                            Dedicated infrastructure, custom AI fine-tuning, ERP inventory sync, and priority webhook pipelines for multi-location groups.
                        </p>
                        <ul class="space-y-3 text-xs text-[#241e19] mt-6">
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Unlimited Workspaces & Team Members
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Custom ERP / Warehouse Database Sync
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Dedicated Account Manager & SLA
                            </li>
                            <li class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                99.98% High-Availability Cluster
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-6">
                        <a href="#contact" class="block w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-center bg-[#faf8f5] text-[#241e19] border border-[#e8e2d9] hover:bg-[#291e17] hover:text-white transition-all">
                            Talk To Solutions Team
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section id="about" class="py-20 bg-white border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">About The Platform</span>
                    <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2 mb-6">
                        Built To End Fragmented Inboxes & Lost Sales
                    </h2>
                    <p class="text-sm text-[#66584d] leading-relaxed mb-4">
                        Modern consumers do not want to fill out static website contact forms and wait 48 hours for an email reply. They message your brand on WhatsApp, Telegram, or Instagram expecting instant answers, accurate pricing, and immediate ways to buy.
                    </p>
                    <p class="text-sm text-[#66584d] leading-relaxed mb-6">
                        ZEEN was built to solve this operational friction. By uniting AI conversational intelligence, real-time product inventory, and instant payment links into a unified console, businesses can close orders in seconds while giving their staff full visibility and one-click takeover control.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4 text-xs font-medium text-[#241e19]">
                        <div class="p-3.5 rounded-lg bg-[#faf8f5] border border-[#e8e2d9]">
                            <p class="text-base font-bold font-editorial text-[#7b5537]">Zero Latency</p>
                            <p class="text-[11px] text-[#66584d] mt-0.5">Automated AI responses in under 2 seconds across all active channels.</p>
                        </div>
                        <div class="p-3.5 rounded-lg bg-[#faf8f5] border border-[#e8e2d9]">
                            <p class="text-base font-bold font-editorial text-[#7b5537]">Human In The Loop</p>
                            <p class="text-[11px] text-[#66584d] mt-0.5">Seamless human takeover whenever an inquiry needs personal attention.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-[#faf8f5] border border-[#e8e2d9] p-8 space-y-6">
                    <h3 class="text-lg font-bold text-[#241e19] border-b border-[#e8e2d9] pb-3">Core Engineering Principles</h3>
                    
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                            01
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-[#241e19]">Multi-Tenant Isolation</h4>
                            <p class="text-xs text-[#66584d] mt-0.5">Every business workspace has dedicated database scopes. Customer conversation records are strictly partitioned and confidential.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                            02
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-[#241e19]">Idempotent Payment Webhooks</h4>
                            <p class="text-xs text-[#66584d] mt-0.5">Payment verification uses cryptographic signature checking and idempotent handlers to prevent duplicate order fulfillments.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-[#291e17] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                            03
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-[#241e19]">In-Chat Operational Agility</h4>
                            <p class="text-xs text-[#66584d] mt-0.5">Store owners can update inventory, restock goods, and inspect daily sales right from their phone without opening a laptop.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRIVACY & DATA SECURITY SECTION -->
    <section id="privacy" class="py-20 bg-[#faf8f5] border-b border-[#e8e2d9]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-3xl mx-auto text-center mb-16">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Trust & Security</span>
                <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2">
                    Enterprise Privacy & Data Protection
                </h2>
                <p class="text-sm text-[#66584d] mt-3">
                    Your customer relationships are your most valuable asset. ZEEN complies with international data privacy standards and secures customer conversations with end-to-end encryption.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <div class="p-6 rounded-xl bg-white border border-[#e8e2d9] shadow-xs">
                    <div class="w-10 h-10 rounded-lg bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-[#241e19] mb-2">Encrypted Transmission</h3>
                    <p class="text-xs text-[#66584d] leading-relaxed">
                        All channel communications, webhook events, and customer credentials travel over TLS 1.3 encryption with HMAC-SHA256 signature verification.
                    </p>
                </div>

                <div class="p-6 rounded-xl bg-white border border-[#e8e2d9] shadow-xs">
                    <div class="w-10 h-10 rounded-lg bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-[#241e19] mb-2">GDPR & Consent Governance</h3>
                    <p class="text-xs text-[#66584d] leading-relaxed">
                        We support full right-to-erasure and export endpoints. Customer data is stored exclusively to fulfill commerce interactions and never shared with third-party ad brokers.
                    </p>
                </div>

                <div class="p-6 rounded-xl bg-white border border-[#e8e2d9] shadow-xs">
                    <div class="w-10 h-10 rounded-lg bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-[#241e19] mb-2">PCI-DSS Compliant Gateways</h3>
                    <p class="text-xs text-[#66584d] leading-relaxed">
                        Payment card details never touch our servers. Transactions are handled directly via PCI-DSS Level 1 certified gateways (Paystack and Stripe).
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section id="contact" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-[#7b5537]">Get In Touch</span>
                    <h2 class="text-3xl sm:text-4xl font-normal font-editorial text-[#241e19] mt-2 mb-4">
                        Schedule a Strategy Session With Our Engineering Team
                    </h2>
                    <p class="text-sm text-[#66584d] leading-relaxed mb-8">
                        Looking to automate high-volume WhatsApp sales, integrate a custom catalog database, or deploy a unified multi-agent inbox for your enterprise team? We are here to help.
                    </p>

                    <div class="space-y-6 text-xs text-[#241e19]">
                        <div class="flex items-start gap-4">
                            <div class="w-9 h-9 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-[#7b5537] flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-sm">Enterprise Sales & Solutions</p>
                                <p class="text-stone-500 text-xs">sales@zeen.io</p>
                                <p class="text-[11px] text-stone-400 mt-0.5">Direct response from our solutions architect within 2 hours</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-9 h-9 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-[#7b5537] flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-sm">Customer Success & Technical Support</p>
                                <p class="text-stone-500 text-xs">support@zeen.io</p>
                                <p class="text-[11px] text-stone-400 mt-0.5">24/7 Webhook and message pipeline monitoring</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="rounded-2xl bg-[#faf8f5] border border-[#e8e2d9] p-8 shadow-xs">
                    <h3 class="text-lg font-bold text-[#241e19] mb-1">Request an Onboarding Demo</h3>
                    <p class="text-xs text-[#66584d] mb-6">Tell us about your business and we will configure a demo workspace with your catalog.</p>

                    <form id="contact-form" onsubmit="handleContactSubmit(event)" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">Full Name</label>
                            <input type="text" name="name" required class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]" placeholder="e.g. Adebayo Ogunlesi">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">Work Email</label>
                                <input type="email" name="email" required class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]" placeholder="adebayo@company.com">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">Company / Brand Name</label>
                                <input type="text" name="company" class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]" placeholder="Ogunlesi Retail Group">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">Business Industry</label>
                                <select name="venue_type" class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]">
                                    <option value="retail">Retail Store & E-Commerce</option>
                                    <option value="hospitality">Hotels, Shortlets & Hospitality</option>
                                    <option value="services">Professional Services & Consulting</option>
                                    <option value="real_estate">Real Estate & Property</option>
                                    <option value="healthcare">Healthcare & Wellness</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">Monthly Inbound Chats</label>
                                <select name="access_points" class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]">
                                    <option value="under_500">&lt; 500 chats / month</option>
                                    <option value="500_2000">500 to 2,000 chats / month</option>
                                    <option value="2000_10000">2,000 to 10,000 chats / month</option>
                                    <option value="10000_plus">10,000+ chats / month</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-[#4a3324] mb-1">What would you like to automate?</label>
                            <textarea name="message" rows="3" required class="w-full text-xs rounded-lg border-[#e8e2d9] bg-white px-3.5 py-2.5 text-[#241e19] focus:border-[#7b5537] focus:ring-[#7b5537]" placeholder="e.g. We get 100+ WhatsApp DMs daily asking about prices and availability. We want to automate sales and send payment links directly..."></textarea>
                        </div>

                        <div id="contact-status-box" class="hidden p-3 rounded-lg text-xs"></div>

                        <button type="submit" id="contact-submit-btn" class="w-full py-3 rounded-lg text-xs font-bold uppercase tracking-wider bg-[#291e17] text-[#faf8f5] hover:bg-[#38261a] transition-all shadow-sm">
                            Submit Consultation Request
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-[#1a110b] text-[#e8e2d9] border-t border-[#3b2c22] pt-16 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10 pb-12 border-b border-[#3b2c22]">
                
                <!-- Brand Column -->
                <div class="md:col-span-2 space-y-4">
                    <a href="/" class="inline-block group" aria-label="ZEEN Home">
                        <img 
                            src="/images/logo.png" 
                            alt="ZEEN" 
                            class="h-12 w-auto object-contain transition-transform duration-200 group-hover:scale-105" 
                            style="filter: brightness(0) invert(1) sepia(0.3) saturate(3) brightness(1.4) drop-shadow(0 0 12px rgba(251,191,36,0.6));"
                        />
                    </a>
                    <p class="text-xs text-stone-400 leading-relaxed max-w-sm">
                        Enterprise omnichannel conversational commerce platform. Unify customer chats across WhatsApp, Telegram, Messenger, and Web into an automated revenue engine.
                    </p>
                    <p class="text-[11px] text-stone-500 font-mono">
                        Instant Payments • Real-Time Catalog • AI Sales Automation • Human Takeover
                    </p>
                </div>

                <!-- Solutions -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3">Solutions</h4>
                    <ul class="space-y-2 text-xs text-stone-400">
                        <li><a href="#solutions" class="hover:text-white transition-colors">Unified Inbox</a></li>
                        <li><a href="#solutions" class="hover:text-white transition-colors">AI Sales Assistant</a></li>
                        <li><a href="#solutions" class="hover:text-white transition-colors">In-Chat Checkout</a></li>
                        <li><a href="#solutions" class="hover:text-white transition-colors">Inventory Control</a></li>
                        <li><a href="#solutions" class="hover:text-white transition-colors">Appointment Booking</a></li>
                    </ul>
                </div>

                <!-- Channels -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3">Channels</h4>
                    <ul class="space-y-2 text-xs text-stone-400">
                        <li><a href="#channels" class="hover:text-white transition-colors">WhatsApp Business</a></li>
                        <li><a href="#channels" class="hover:text-white transition-colors">Telegram Bot</a></li>
                        <li><a href="#channels" class="hover:text-white transition-colors">Facebook Messenger</a></li>
                        <li><a href="#channels" class="hover:text-white transition-colors">Web Chat Widget</a></li>
                        <li><a href="#channels" class="hover:text-white transition-colors">Paystack & Stripe</a></li>
                    </ul>
                </div>

                <!-- Compliance -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3">Trust & Security</h4>
                    <ul class="space-y-2 text-xs text-stone-400">
                        <li><a href="#privacy" class="hover:text-white transition-colors">GDPR Compliance</a></li>
                        <li><a href="#privacy" class="hover:text-white transition-colors">Data Encryption</a></li>
                        <li><a href="#about" class="hover:text-white transition-colors">Engineering Ethos</a></li>
                        <li><a href="#contact" class="hover:text-white transition-colors">Support Portal</a></li>
                        <li><a href="#contact" class="hover:text-white transition-colors">Schedule Demo</a></li>
                    </ul>
                </div>

            </div>

            <!-- Footer Bottom -->
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <p>&copy; {{ date('Y') }} ZEEN Omnichannel Business Platform. All rights reserved.</p>
                <div class="flex items-center gap-6">
                    <a href="#privacy" class="hover:text-stone-300 transition-colors">Privacy Notice</a>
                    <a href="#privacy" class="hover:text-stone-300 transition-colors">Terms of Service</a>
                    <a href="#contact" class="hover:text-stone-300 transition-colors">Security Disclosures</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- INTERACTIVE DEMO SIMULATION ENGINE -->
    <script>
        let currentStep = 1;
        const totalSteps = 5;
        let isPlaying = true;
        let elapsedSeconds = 18;
        const totalSeconds = 90;
        let playbackInterval = null;

        const stepRanges = [
            { step: 1, start: 0, end: 18 },
            { step: 2, start: 19, end: 36 },
            { step: 3, start: 37, end: 54 },
            { step: 4, start: 55, end: 72 },
            { step: 5, start: 73, end: 90 },
        ];

        function initDemoPlayer() {
            startPlaybackLoop();
            updateUI();
        }

        function startPlaybackLoop() {
            if (playbackInterval) clearInterval(playbackInterval);
            playbackInterval = setInterval(() => {
                if (isPlaying) {
                    elapsedSeconds++;
                    if (elapsedSeconds > totalSeconds) {
                        elapsedSeconds = 0;
                    }
                    const match = stepRanges.find(r => elapsedSeconds >= r.start && elapsedSeconds <= r.end);
                    if (match && match.step !== currentStep) {
                        currentStep = match.step;
                    }
                    updateUI();
                }
            }, 1000);
        }

        function toggleDemoPlayback() {
            isPlaying = !isPlaying;
            const label = document.getElementById('demo-play-label');
            const icon = document.getElementById('demo-play-icon');
            if (isPlaying) {
                label.innerText = 'Pause';
                icon.innerHTML = '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>';
            } else {
                label.innerText = 'Play';
                icon.innerHTML = '<path d="M8 5v14l11-7z"/>';
            }
        }

        function resetDemoPlayback() {
            elapsedSeconds = 0;
            currentStep = 1;
            isPlaying = true;
            document.getElementById('demo-play-label').innerText = 'Pause';
            document.getElementById('demo-play-icon').innerHTML = '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>';
            updateUI();
        }

        function setDemoStep(stepNumber) {
            currentStep = stepNumber;
            const range = stepRanges.find(r => r.step === stepNumber);
            if (range) {
                elapsedSeconds = range.start;
            }
            updateUI();
        }

        function handleScrubberClick(e) {
            const rect = e.currentTarget.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const ratio = Math.max(0, Math.min(1, clickX / rect.width));
            elapsedSeconds = Math.round(ratio * totalSeconds);
            const match = stepRanges.find(r => elapsedSeconds >= r.start && elapsedSeconds <= r.end);
            if (match) currentStep = match.step;
            updateUI();
        }

        function updateUI() {
            const mins = Math.floor(elapsedSeconds / 60);
            const secs = elapsedSeconds % 60;
            const formattedTime = `0${mins}:${secs < 10 ? '0' : ''}${secs} / 01:30`;
            const timerEl = document.getElementById('demo-timer');
            if (timerEl) timerEl.innerText = formattedTime;

            const percent = Math.min(100, Math.max(0, (elapsedSeconds / totalSeconds) * 100));
            const progBar = document.getElementById('demo-progress-bar');
            if (progBar) progBar.style.width = `${percent}%`;

            for (let i = 1; i <= totalSteps; i++) {
                const frame = document.getElementById(`frame-step-${i}`);
                const tab = document.getElementById(`tab-step-${i}`);
                if (frame) {
                    if (i === currentStep) {
                        frame.classList.remove('hidden');
                    } else {
                        frame.classList.add('hidden');
                    }
                }
                if (tab) {
                    if (i === currentStep) {
                        tab.className = 'demo-tab-btn py-3 px-3 text-left transition-all bg-[#291e17] text-white font-semibold flex items-center gap-2';
                        const badge = tab.querySelector('span:first-child');
                        if (badge) badge.className = 'w-5 h-5 rounded-full bg-[#f59e0b] text-[#1a110b] flex items-center justify-center font-mono text-[10px] font-bold';
                    } else {
                        tab.className = 'demo-tab-btn py-3 px-3 text-left transition-all text-stone-400 hover:text-white flex items-center gap-2';
                        const badge = tab.querySelector('span:first-child');
                        if (badge) badge.className = 'w-5 h-5 rounded-full bg-[#3b2c22] text-stone-300 flex items-center justify-center font-mono text-[10px] font-bold';
                    }
                }
            }
        }

        async function handleContactSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('contact-submit-btn');
            const statusBox = document.getElementById('contact-status-box');
            
            btn.disabled = true;
            btn.innerText = 'TRANSMITTING REQUEST...';

            const formData = new FormData(form);

            try {
                const res = await fetch('/contact', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    },
                    body: formData
                });

                const data = await res.json();
                statusBox.classList.remove('hidden', 'bg-red-900/40', 'text-red-200', 'border-red-700');
                statusBox.classList.add('bg-emerald-900/40', 'text-emerald-200', 'border', 'border-emerald-700');
                statusBox.innerText = data.message || 'Thank you! Your request has been received. Our solutions team will contact you shortly.';
                form.reset();
            } catch (err) {
                statusBox.classList.remove('hidden', 'bg-emerald-900/40', 'text-emerald-200', 'border-emerald-700');
                statusBox.classList.add('bg-emerald-900/40', 'text-emerald-200', 'border', 'border-emerald-700');
                statusBox.innerText = 'Thank you! Your request has been logged. Our solutions team will reach out shortly.';
                form.reset();
            } finally {
                btn.disabled = false;
                btn.innerText = 'SUBMIT CONSULTATION REQUEST';
            }
        }

        document.addEventListener('DOMContentLoaded', initDemoPlayer);
    </script>
</body>
</html>
