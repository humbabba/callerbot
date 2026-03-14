document.addEventListener('DOMContentLoaded', () => {
    console.log('I am awake');

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
                setTimeout(type, 50 + Math.random() * 60);
            } else {
                el.classList.add('done');
            }
        }

        setTimeout(type, delay);
    });

    // Chat
    const input = document.getElementById('command-input');
    const chatLog = document.getElementById('chat-log');
    const status = document.getElementById('status');
    const history = [];

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

    function appendMessage(role, text) {
        const line = document.createElement('div');
        line.className = 'mb-2';

        const prefix = role === 'user' ? '> ' : '< ';
        const colorClass = role === 'user' ? 'text-terminal-green' : 'text-terminal-dim';

        line.innerHTML = `<span class="${colorClass}"><span class="select-none">${prefix}</span>${escapeHtml(text)}</span>`;
        chatLog.appendChild(line);
        chatLog.scrollTop = chatLog.scrollHeight;
        return line;
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

    let statusTimeout = null;

    function setStatus(text) {
        if (statusTimeout) {
            clearTimeout(statusTimeout);
            statusTimeout = null;
        }

        status.textContent = '';
        status.classList.remove('done');
        let i = 0;

        function type() {
            if (i < text.length) {
                status.textContent += text[i];
                i++;
                statusTimeout = setTimeout(type, 50 + Math.random() * 60);
            } else {
                status.classList.add('done');
                statusTimeout = null;
            }
        }

        type();
    }

    async function sendMessage(message) {
        input.disabled = true;
        setStatus('Working ...');

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
            setStatus('Response received.');

            const data = await res.json();

            if (data.error) {
                appendMessage('model', data.error);
            } else {
                const meta = [data.model, data.function].filter(Boolean).join(' → ');
                if (meta) {
                    appendStatus(`[${meta}]`);
                }
                appendMessage('model', data.reply);
                history.push({ role: 'model', text: data.reply });
            }
        } catch {
            clearInterval(thinkingInterval);
            removeStatusLines();
            setStatus('Connection error.');
            appendMessage('model', 'Could not reach the server. Check your connection and try again.');
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
