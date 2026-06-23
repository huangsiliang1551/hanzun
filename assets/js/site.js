const toggle = document.querySelector('[data-menu-toggle]');
const nav = document.querySelector('[data-menu]');

if (toggle && nav) {
    const setMenuOpen = (open) => {
        nav.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    const isOpen = () => nav.classList.contains('open');

    setMenuOpen(isOpen());

    toggle.addEventListener('click', () => {
        setMenuOpen(!isOpen());
    });

    toggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setMenuOpen(!isOpen());
        }

        if (e.key === 'Escape') {
            setMenuOpen(false);
        }
    });

    nav.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            setMenuOpen(false);
        }
    });
}

function safeStorageGet(storage, key) {
    try {
        return storage?.getItem?.(key) || '';
    } catch (error) {
        return '';
    }
}

function safeStorageSet(storage, key, value) {
    try {
        storage?.setItem?.(key, value);
    } catch (error) {
        return;
    }
}

function safeStorageRemove(storage, key) {
    try {
        storage?.removeItem?.(key);
    } catch (error) {
        return;
    }
}

function safeSessionStorageProbe() {
    try {
        if (!window.sessionStorage) {
            return false;
        }
        const probeKey = '__hanzun_session_probe__';
        window.sessionStorage.setItem(probeKey, '1');
        window.sessionStorage.removeItem(probeKey);
        return true;
    } catch (error) {
        return false;
    }
}

function trackVisit() {
    if (!window.fetch) {
        return;
    }

    const storageKey = 'hanzun-client-id';
    const sessionStorageKey = 'hanzun-support-session';
    let clientId = safeStorageGet(window.localStorage, storageKey);

    if (!clientId) {
        clientId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        safeStorageSet(window.localStorage, storageKey, clientId);
    }

    let supportSessionCode = '';
    if (safeSessionStorageProbe()) {
        supportSessionCode = safeStorageGet(window.sessionStorage, sessionStorageKey);
    }

    const payload = {
        client_id: clientId,
        session_code: supportSessionCode,
        path: `${window.location.pathname}${window.location.search}`,
        title: document.title,
        referrer: document.referrer,
        language: document.documentElement.lang || document.body?.dataset?.lang || 'en',
    };

    const request = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
        keepalive: true,
    };

    fetch('/api/visitor-events', request).then(async (response) => {
        if (!response || !response.ok) {
            return;
        }

        const result = await response.json().catch(() => null);
        const nextSessionCode = result?.data?.session_code;

        if (!nextSessionCode) {
            return;
        }

        safeStorageSet(
            window.sessionStorage,
            sessionStorageKey,
            String(nextSessionCode)
        );
    }).catch(() => {
        // best effort only
    });

    try {
        safeStorageRemove(window.localStorage, 'hanzun-support-session');
    } catch (error) {
        return;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        trackVisit();
    }, { once: true });
} else {
    trackVisit();
}
