const toggle = document.querySelector('[data-menu-toggle]');
const nav = document.querySelector('[data-menu]');

if (toggle && nav) {
    toggle.addEventListener('click', () => {
        nav.classList.toggle('open');
    });
    toggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            nav.classList.toggle('open');
        }
    });
}

function trackVisit() {
    const storageKey = 'hanzun-client-id';
    const sessionStorageKey = 'hanzun-support-session';
    let clientId = localStorage.getItem(storageKey);

    if (!clientId) {
        clientId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(storageKey, clientId);
    }

    let supportSessionCode = '';
    try {
        supportSessionCode = window.sessionStorage?.getItem(sessionStorageKey) || '';
        window.localStorage?.removeItem(sessionStorageKey);
    } catch (error) {
        supportSessionCode = '';
    }

    fetch('/api/visitor-events', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            client_id: clientId,
            session_code: supportSessionCode,
            path: window.location.pathname + window.location.search,
            title: document.title,
            referrer: document.referrer,
            language: document.documentElement.lang || document.body?.dataset?.lang || 'en',
        }),
        keepalive: true,
    }).then(async (response) => {
        const result = await response.json().catch(() => null);
        if (result && result.data && result.data.session_code) {
            try {
                window.sessionStorage?.setItem(sessionStorageKey, result.data.session_code);
            } catch (error) {
                return;
            }
        }
    }).catch(() => {});
}

trackVisit();
