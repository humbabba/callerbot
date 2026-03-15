document.addEventListener('DOMContentLoaded', () => {
    console.log('Callerbot has become self-aware');
    const typingSpeed = 15;

    // Typewriter effect
    document.querySelectorAll('.typewriter').forEach((el) => {
        const text = el.dataset.text || el.textContent;
        el.textContent = '';
        const delay = parseInt(el.dataset.typeDelay, 10) || 600;
        let i = 0;

        function type() {
            if (i < text.length) {
                el.textContent += text[i];
                i++;
                setTimeout(type, speed + Math.random() * jitter);
            } else {
                el.classList.add('done');
                const chatLog = document.getElementById('chat-log');
                if (chatLog) chatLog.classList.add('crt-on');
            }
        }

        const speed = parseInt(el.dataset.typeSpeed, 10) || typingSpeed;
        const jitter = parseInt(el.dataset.typeJitter, 10) || 60;
        setTimeout(type, delay);
    });

    // Chat
    const input = document.getElementById('command-input');
    const chatLog = document.getElementById('chat-log');
    const greeting = document.getElementById('greeting');
    const history = [];
    let greetingDismissed = false;

    const thinkingPhrases = [
        'Parsing query...',
        'Resolving intent...',
        'Selecting model...',
        'Establishing API handshake...',
        'Tokenizing input...',
        'Evaluating tool candidates...',
        'Awaiting inference...',
        'Decoding response stream...',
    ];

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

        const escaped = escapeHtml(text);
        let i = 0;

        return new Promise((resolve) => {
            function typeChar() {
                if (i < text.length) {
                    textNode.textContent += text[i];
                    i++;
                    chatLog.scrollTop = chatLog.scrollHeight;
                    setTimeout(typeChar, speed + Math.random() * (speed * 2));
                } else {
                    line.classList.remove('typing');
                    resolve(line);
                }
            }
            typeChar();
        });
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

    let greetingTimeout = null;

    function setGreeting(text, speed = typingSpeed) {
        if (greetingDismissed) return;
        if (greetingTimeout) {
            clearTimeout(greetingTimeout);
            greetingTimeout = null;
        }

        greeting.textContent = '';
        greeting.classList.remove('done');
        let i = 0;

        function type() {
            if (i < text.length) {
                greeting.textContent += text[i];
                i++;
                greetingTimeout = setTimeout(type, speed + Math.random() * (speed * 4));
            } else {
                greeting.classList.add('done');
                greetingTimeout = null;
            }
        }

        type();
    }

    function dismissGreeting() {
        if (greetingDismissed) return;
        greetingDismissed = true;
        if (greetingTimeout) {
            clearTimeout(greetingTimeout);
            greetingTimeout = null;
        }
        greeting.classList.add('greeting-out');
        greeting.addEventListener('animationend', () => {
            greeting.remove();
        }, { once: true });
    }

    async function sendMessage(message) {
        input.disabled = true;
        setGreeting('Working ...');

        // Start the thinking animation in the chat log
        let phraseIndex = 0;
        const statusLine = appendStatus(thinkingPhrases[0]);

        const thinkingInterval = setInterval(() => {
            phraseIndex = (phraseIndex + 1) % thinkingPhrases.length;
            const span = statusLine.querySelector('span');
            if (span) {
                span.innerHTML = `<span class="select-none">  </span>${escapeHtml(thinkingPhrases[phraseIndex])}`;
            }
            chatLog.scrollTop = chatLog.scrollHeight;
        }, 800 + Math.random() * 400);

        try {
            const res = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, history }),
            });

            clearInterval(thinkingInterval);
            removeStatusLines();
            dismissGreeting();

            const data = await res.json();

            if (data.error) {
                await appendMessage('model', data.error, 4);
            } else {
                const meta = [data.model, data.function].filter(Boolean).join(' → ');
                if (meta) {
                    appendStatus(`[${meta}]`);
                }
                await appendMessage('model', data.reply, 4);
                history.push({ role: 'model', text: data.reply });
            }
        } catch {
            clearInterval(thinkingInterval);
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
