document.addEventListener('DOMContentLoaded', () => {
    console.log('Callerbot has become self-aware');
    const typingSpeed = 15;

    /**
     * Single typing engine. Returns { promise, cancel }.
     * - el: element to type into (uses a textNode child)
     * - text: string to type
     * - opts.speed: base ms per char (default typingSpeed)
     * - opts.jitter: random ms added per char (default speed * 2)
     * - opts.onDone: callback when typing finishes
     */
    function typeText(el, text, opts = {}) {
        const speed = opts.speed ?? typingSpeed;
        const jitter = opts.jitter ?? speed * 2;
        const onDone = opts.onDone ?? null;
        let i = 0;
        let timer = null;
        let cancelled = false;

        const promise = new Promise((resolve) => {
            function step() {
                if (cancelled) return resolve();
                if (i < text.length) {
                    el.textContent += text[i];
                    i++;
                    timer = setTimeout(step, speed + Math.random() * jitter);
                } else {
                    timer = null;
                    if (onDone) onDone();
                    resolve();
                }
            }
            step();
        });

        return {
            promise,
            cancel() {
                cancelled = true;
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
            },
        };
    }

    // Typewriter init for .typewriter elements
    document.querySelectorAll('.typewriter').forEach((el) => {
        const text = el.dataset.text || el.textContent;
        el.textContent = '';
        const delay = parseInt(el.dataset.typeDelay, 10) || 600;
        const speed = parseInt(el.dataset.typeSpeed, 10) || typingSpeed;
        const jitter = parseInt(el.dataset.typeJitter, 10) || 60;

        setTimeout(() => {
            typeText(el, text, {
                speed,
                jitter,
                onDone() {
                    el.classList.add('done');
                    const log = document.getElementById('chat-log');
                    if (log) log.classList.add('crt-on');
                },
            });
        }, delay);
    });

    // Chat
    const input = document.getElementById('command-input');
    const chatLog = document.getElementById('chat-log');
    const greeting = document.getElementById('greeting');
    const history = [];
    let greetingDismissed = false;
    let greetingTyper = null;

    input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        const intro = document.getElementById('intro');
        if (intro) intro.remove();
        appendMessage('user', message);
        history.push({ role: 'user', text: message });
        sendMessage(message);
    });

    function appendMessage(role, text, speed = typingSpeed) {
        const line = document.createElement('div');
        line.className = 'mb-2';

        const prefix = role === 'user' ? '> ' : '< ';
        const colorClass = role === 'user' ? 'text-terminal-green' : 'text-terminal-dim';

        if (role === 'user') {
            line.innerHTML = `<span class="${colorClass}"><span class="select-none">${prefix}</span>${escapeHtml(text)}</span>`;
            chatLog.appendChild(line);
            chatLog.scrollTop = chatLog.scrollHeight;
            return Promise.resolve(line);
        }

        // Type out model responses
        const span = document.createElement('span');
        span.className = colorClass;
        const prefixSpan = document.createElement('span');
        prefixSpan.className = 'select-none';
        prefixSpan.textContent = prefix;
        span.appendChild(prefixSpan);
        const textNode = document.createTextNode('');
        span.appendChild(textNode);
        line.appendChild(span);
        line.classList.add('typing');
        chatLog.appendChild(line);

        const { promise } = typeText(textNode, text, {
            speed,
            jitter: speed * 2,
            onDone() {
                line.classList.remove('typing');
            },
        });

        // Keep chat scrolled during typing
        const scrollInterval = setInterval(() => {
            chatLog.scrollTop = chatLog.scrollHeight;
        }, 50);
        promise.then(() => clearInterval(scrollInterval));

        return promise.then(() => line);
    }

    function appendStatus(text) {
        const line = document.createElement('div');
        line.className = 'mb-1 status-line';
        line.innerHTML = `<span class="text-terminal-dim/60 text-sm"><span class="select-none">  </span>${escapeHtml(text)}</span>`;
        chatLog.appendChild(line);
        chatLog.scrollTop = chatLog.scrollHeight;
        return line;
    }

    function removeStatusLines() {
        chatLog.querySelectorAll('.status-line').forEach(el => el.remove());
    }

    function setGreeting(text, speed = typingSpeed) {
        if (greetingDismissed) return;
        if (greetingTyper) greetingTyper.cancel();

        greeting.textContent = '';
        greeting.classList.remove('done');

        greetingTyper = typeText(greeting, text, {
            speed,
            jitter: speed * 4,
            onDone() {
                greeting.classList.add('done');
                greetingTyper = null;
            },
        });
    }

    function dismissGreeting() {
        if (greetingDismissed) return;
        greetingDismissed = true;
        if (greetingTyper) {
            greetingTyper.cancel();
            greetingTyper = null;
        }
        greeting.classList.add('greeting-out');
        greeting.addEventListener('animationend', () => {
            greeting.remove();
        }, { once: true });
    }

    async function sendMessage(message) {
        input.disabled = true;
        setGreeting('Working ...');

        const statusLine = appendStatus('Connecting...');

        function updateStatus(text) {
            const span = statusLine.querySelector('span');
            if (span) {
                span.innerHTML = `<span class="select-none">  </span>${escapeHtml(text)}`;
            }
            chatLog.scrollTop = chatLog.scrollHeight;
        }

        try {
            const res = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, history }),
            });

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let finalData = null;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                // Parse SSE events from buffer
                const parts = buffer.split('\n\n');
                buffer = parts.pop();

                for (const part of parts) {
                    let event = 'message';
                    let data = '';
                    for (const line of part.split('\n')) {
                        if (line.startsWith('event: ')) event = line.slice(7);
                        else if (line.startsWith('data: ')) data = line.slice(6);
                    }
                    if (!data) continue;

                    const parsed = JSON.parse(data);
                    if (event === 'status') {
                        updateStatus(parsed.text);
                    } else if (event === 'done') {
                        finalData = parsed;
                    }
                }
            }

            removeStatusLines();
            dismissGreeting();

            if (!finalData) {
                await appendMessage('model', 'No response received from the server.', 4);
            } else if (finalData.error) {
                await appendMessage('model', finalData.error, 4);
            } else {
                const meta = [finalData.model, finalData.function].filter(Boolean).join(' \u2192 ');
                if (meta) {
                    appendStatus(`[${meta}]`);
                }
                await appendMessage('model', finalData.reply, 4);
                history.push({ role: 'model', text: finalData.reply });
            }
        } catch {
            removeStatusLines();
            dismissGreeting();
            await appendMessage('model', 'Could not reach the server. Check your connection and try again.', 4);
        }

        input.disabled = false;
        input.focus();
    }

    function escapeHtml(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }
});
