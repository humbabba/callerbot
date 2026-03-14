<?php $config = require __DIR__ . '/config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Callerbot</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="dist/css/style.css?v=<?= $config['version'] ?>">
</head>
<body class="min-h-screen flex items-center justify-center font-mono text-terminal-green antialiased">
    <div class="scanline"></div>

    <main class="w-full max-w-2xl px-6">
        <div class="border border-terminal-dim/40 rounded bg-terminal-panel/60 p-8 backdrop-blur-sm">
            <div class="flex items-center gap-2 text-terminal-dim text-sm mb-6 select-none">
                <span class="inline-block w-2 h-2 rounded-full bg-terminal-green animate-pulse"></span>
                <span>CALLERBOT v<?= $config['version'] ?> &mdash; SESSION ACTIVE</span>
            </div>

            <p id="status" class="typewriter text-lg md:text-xl glow mb-4 min-h-[1.75em]">Hello, how may I be of service?</p>

            <div id="chat-log" class="text-sm max-h-94 overflow-y-auto mb-4">
                <div class="text-terminal-dim/80 text-sm mb-3 leading-relaxed" id="intro">
                    <strong class="text-terminal-green/80">Function calling</strong> lets an AI model
                    reach beyond its training data by invoking real tools &mdash; APIs, databases,
                    calculations &mdash; in the middle of a conversation. The model decides
                    <em>which</em> function to call and <em>what arguments</em> to pass, then
                    weaves the live results into its response.
                    <br><br>
                    Callerbot is a demo of this pattern built around <strong class="text-terminal-green/80">travel
                    research</strong>: weather, forecasts, travel advisories, destination guides,
                    local time, country intel, currency conversion, holidays, and more. Ask a
                    question that fits a tool and Callerbot will call it. Ask something no tool
                    covers and it will propose one, complete with parameters and a rationale.
                    <br><br>
                    <span class="text-terminal-dim/60">Try a conversation like:</span><br>
                    <span class="text-terminal-green/60">&gt;</span> <span class="text-terminal-dim">"I'm thinking about visiting Thailand"</span><br>
                    <span class="text-terminal-green/60">&gt;</span> <span class="text-terminal-dim">"Is it safe?"</span><br>
                    <span class="text-terminal-green/60">&gt;</span> <span class="text-terminal-dim">"What's the weather like in Bangkok?"</span><br>
                    <span class="text-terminal-green/60">&gt;</span> <span class="text-terminal-dim">"How much is $500 in local currency?"</span><br>
                    <span class="text-terminal-green/60">&gt;</span> <span class="text-terminal-dim">"Any holidays coming up?"</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-terminal-dim select-none">&gt;</span>
                <input
                    id="command-input"
                    type="text"
                    class="flex-1 bg-transparent border border-terminal-dim/40 rounded px-3 py-2 text-terminal-green placeholder-terminal-dim/50 outline-none transition-shadow duration-200"
                    placeholder="Type a command..."
                    autocomplete="off"
                    spellcheck="false"
                    autofocus
                >
            </div>
        </div>

        <p class="text-terminal-dim/60 text-sm text-center mt-4 select-none">
            &copy; <?= date('Y') ?> <a href="https://sublogicalendeavors.com/" target="_blank">Sublogical Endeavors</a>
        </p>
    </main>
    <script src="dist/js/app.js?v=<?= $config['version'] ?>"></script>
</body>
</html>
