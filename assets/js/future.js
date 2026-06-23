const body = document.body;
const html = document.documentElement;
const menuToggle = document.querySelector("[data-menu-toggle]");
const menu = document.querySelector("[data-menu]");
const dropdown = document.querySelector("[data-lang-dropdown]");
const dropdownTrigger = document.querySelector("[data-lang-trigger]");
const dropdownLabel = document.querySelector("[data-lang-label]");
const dropdownFlag = document.querySelector("[data-lang-flag]");
const langMenu = document.querySelector("[data-lang-menu]");
const productNav = document.querySelector("[data-product-nav]");
const productTrigger = document.querySelector("[data-product-trigger]");
const productPanel = document.querySelector("[data-product-panel]");
const navDropdownItems = document.querySelectorAll("[data-nav-dropdown]");
const productTabs = document.querySelectorAll("[data-product-tab]");
const productViews = document.querySelectorAll("[data-product-view]");
const productBranches = document.querySelectorAll(".nav-tree-branch");
const mobileProductAccordion = productPanel ? document.createElement("div") : null;
const revealItems = document.querySelectorAll(".reveal");
const counters = document.querySelectorAll("[data-count]");
const loopStrips = document.querySelectorAll("[data-loop-strip]");
const certificateStage = document.querySelector("[data-certificate-stage]");
const contactFab = document.querySelector("[data-contact-fab]");
const contactTrigger = document.querySelector("[data-contact-trigger]");
const contactChoosers = document.querySelectorAll("[data-contact-chooser]");
const backToTopButton = document.querySelector("[data-back-to-top]");
const backToTopButtons = document.querySelectorAll("[data-back-to-top], [data-back-to-top-dock]");
const supportPanel = document.querySelector("[data-support-panel]");
const supportTriggers = document.querySelectorAll("[data-support-trigger]");
const supportCloseButtons = document.querySelectorAll("[data-support-close]");
const supportForm = document.querySelector("[data-support-form]");
const supportInput = document.querySelector("[data-support-input]");
const supportMessages = document.querySelector("[data-support-messages]");
const supportStatus = document.querySelector("[data-support-status]");
const supportSubmitButton = document.querySelector("[data-support-submit]");
const supportSubmitLabel = document.querySelector("[data-support-submit-label]");
const supportPromptButtons = document.querySelectorAll("[data-support-prompt]");
const leadForms = document.querySelectorAll(".lead-form");
const wechatPanel = document.querySelector("[data-wechat-panel]");
const wechatTrigger = document.querySelector("[data-wechat-trigger]");
const wechatCloseButtons = document.querySelectorAll("[data-wechat-close]");
const wechatCopyButton = document.querySelector("[data-wechat-copy]");
const metaDescription = document.querySelector("#meta-description");
const mobileFabMedia = window.matchMedia ? window.matchMedia("(max-width: 860px)") : null;
const heroAnchorButtons = document.querySelectorAll('.hero-actions a[href^="#"]');
const featuredSolutionsGrid = document.querySelector("[data-home-featured-solutions]");
const featuredProductsShowcase = document.querySelector("[data-home-featured-products]");
const featuredCasesBoard = document.querySelector("[data-home-featured-cases]");
const featuredNewsGrid = document.querySelector("[data-home-featured-news]");
const contactGrid = document.querySelector("[data-contact-grid]");
const footerContactList = document.querySelector("[data-footer-contact-list]");
const footerFeaturedProducts = document.querySelector("[data-footer-featured-products]");
const footerFeaturedSolutions = document.querySelector("[data-footer-featured-solutions]");
const brandLinks = document.querySelectorAll(".brand");
const isStaticGeneratedPublicPage = Boolean(body?.dataset.forceLang);

var REGIONAL_CONTACT_PRIORITY = {
    'zh': ['wechat', 'phone', 'email'],
    'ja': ['line', 'email', 'phone'],
    'ko': ['whatsapp', 'email', 'phone'],
    'default': ['whatsapp', 'email', 'phone']
};
function getRegionalContactKeys(langCode) {
    var prefix = String(langCode || 'zh').slice(0, 2).toLowerCase();
    return REGIONAL_CONTACT_PRIORITY[prefix] || REGIONAL_CONTACT_PRIORITY['default'];
}
const brandLogos = document.querySelectorAll(".brand img");
const brandTitleNodes = document.querySelectorAll(".brand .brand-copy strong");
const brandSubtitleNodes = document.querySelectorAll(".brand .brand-copy span");
const footerBrandLogos = document.querySelectorAll(".footer-brand-logo");
const footerBrandTitleNodes = document.querySelectorAll(".footer-brand-title strong");
const footerBrandSubtitleNodes = document.querySelectorAll(".footer-brand-subtitle");
const footerBottomNodes = document.querySelectorAll(".footer-redesign-bottom span");
let productHoverCloseTimer = null;
const navHoverCloseTimers = new WeakMap();
let publicSiteConfig = null;

let languages = [
    { code: "zh", flag: "🇨🇳", country: "China", native: "中文", content: "zh", htmlLang: "zh-CN", continent: "asia" },
    { code: "en", flag: "🇬🇧", country: "United Kingdom", native: "English", content: "en", htmlLang: "en-GB", continent: "europe" },
];

const languageGroups = [
    { key: "asia", zh: "亚洲", en: "Asia" },
    { key: "europe", zh: "欧洲", en: "Europe" },
    { key: "africa", zh: "非洲", en: "Africa" },
    { key: "americas", zh: "美洲", en: "Americas" },
    { key: "global", zh: "其他", en: "Other" },
];

const languageFlagCodes = {
    zh: "cn",
    en: "gb",
    "ar-SA": "sa",
    "bg-BG": "bg",
    "hr-HR": "hr",
    "cs-CZ": "cz",
    "da-DK": "dk",
    "nl-NL": "nl",
    "fi-FI": "fi",
    "fr-FR": "fr",
    "de-DE": "de",
    "el-GR": "gr",
    "hi-IN": "in",
    "it-IT": "it",
    "ja-JP": "jp",
    "ko-KR": "kr",
    "no-NO": "no",
    "pt-PT": "pt",
    "ro-RO": "ro",
    "ru-RU": "ru",
    "es-ES": "es",
    "sv-SE": "se",
    "id-ID": "id",
    "sr-RS": "rs",
    "vi-VN": "vn",
    "hu-HU": "hu",
    "th-TH": "th",
    "tr-TR": "tr",
    "fa-IR": "ir",
    "sw-TZ": "tz",
    "bn-BD": "bd",
    "bs-BA": "ba",
    "lo-LA": "la",
    la: "va",
    "mr-IN": "in",
    "mn-MN": "mn",
    "ta-IN": "in",
    "te-IN": "in",
    "ml-IN": "in",
    "si-LK": "lk",
    "ur-PK": "pk",
};

const languageShortLabels = {
    zh: "CN",
    en: "EN",
};

function readPublicRuntimeConfig() {
    if (publicSiteConfig) {
        return publicSiteConfig;
    }

    const runtimeNode = document.getElementById("hanzun-public-runtime");
    if (runtimeNode) {
        try {
            const parsed = JSON.parse(String(runtimeNode.textContent || "").trim() || "null");
            if (parsed && typeof parsed === "object") {
                publicSiteConfig = parsed;
                return publicSiteConfig;
            }
        } catch (error) {
            console.warn("Failed to parse public runtime config.", error);
        }
    }

    if (window.__HANZUN_PUBLIC_RUNTIME__ && typeof window.__HANZUN_PUBLIC_RUNTIME__ === "object") {
        publicSiteConfig = window.__HANZUN_PUBLIC_RUNTIME__;
        return publicSiteConfig;
    }

    return null;
}

function syncRuntimeLanguages() {
    const runtime = readPublicRuntimeConfig();
    const runtimeLanguages = Array.isArray(runtime?.languages) ? runtime.languages : [];
    if (!runtimeLanguages.length) {
        return;
    }

    languages = runtimeLanguages.map((item) => {
        const code = String(item.code || "").trim().toLowerCase() || "zh";
        return {
            code,
            flag: code === "zh" ? "🇨🇳" : "🇬🇧",
            country: String(item.country || (code === "zh" ? "China" : "United Kingdom")).trim(),
            native: String(item.native || (code === "zh" ? "中文" : "English")).trim(),
            content: String(item.content || (code === "zh" ? "zh" : "en")).trim(),
            htmlLang: String(item.htmlLang || (code === "zh" ? "zh-CN" : "en-GB")).trim(),
            continent: String(item.continent || (code === "zh" ? "asia" : "europe")).trim(),
        };
    });
}

function buildLanguageMap() {
    return new Map(languages.map((item) => [item.code, item]));
}

function getLanguageMap() {
    return buildLanguageMap();
}

function normalizedLang(code) {
    const value = String(code || "").trim();

    if (getLanguageMap().has(value)) {
        return value;
    }

    const normalized = value.toLowerCase();

    if (normalized.startsWith("zh")) {
        return "zh";
    }

    for (const candidate of languages) {
        if (normalized === candidate.code.toLowerCase() || normalized.startsWith(candidate.code.slice(0, 2).toLowerCase())) {
            return candidate.code;
        }
    }

    return "zh";
}

function getContentLanguage(code) {
    return getLanguageMap().get(code)?.content || "en";
}

function getLanguageLabel(code) {
    const normalized = normalizedLang(code);
    return languageShortLabels[normalized] || getFlagCode(normalized).toUpperCase();
}

function getFlagCode(code) {
    return String(languageFlagCodes[code] || "cn").slice(0, 2).toLowerCase();
}

function getLanguageGroupLabel(group) {
    const contentLang = getContentLanguage(normalizedLang(body?.dataset.lang || "zh"));
    return contentLang === "zh" ? group.zh : group.en;
}

function getFlagBadgeSvg(code) {
    const lang = getLanguageMap().get(normalizedLang(code)) || getLanguageMap().get("zh");
    const flagCode = getFlagCode(code);
    const fallback = getFlagSymbol(code);
    const escapedFallback = escapeHtml(fallback);
    return `<img src="${assetPath(`assets/images/flags/${flagCode}.svg`)}" alt="${escapeHtml(lang.country)} flag" loading="lazy" decoding="async" data-flag-fallback="${escapedFallback}">`;
}

function assetPath(path) {
    const value = String(path || "").trim();
    if (!value) {
        return "";
    }
    if (/^(https?:)?\/\//i.test(value) || value.startsWith("/")) {
        return value;
    }
    return `/${value.replace(/^\/+/, "")}`;
}

function currentStaticLanguagePrefix() {
    return `/${toApiLanguageCode(body?.dataset.lang || "zh")}`;
}

function localizedStaticFile(path) {
    const normalized = String(path || "").trim().replace(/^\/+/, "");
    return `${currentStaticLanguagePrefix()}/${normalized}`;
}

function currentLocalizedStaticUrlForLanguage(code) {
    const targetCode = toApiLanguageCode(code || body?.dataset.lang || "zh");
    const pathname = String(window.location.pathname || "/").replace(/\\/g, "/");
    const stripped = pathname.replace(/^\/[a-z]{2}(?=\/)/i, "") || "/index.html";
    return `/${targetCode}${stripped}${window.location.hash || ""}`;
}

function getFlagEmoji(countryCode) {
    const normalized = String(countryCode || "CN").slice(0, 2).toUpperCase();

    if (!/^[A-Z]{2}$/.test(normalized)) {
        return "🏳️";
    }

    return String.fromCodePoint(
        normalized.charCodeAt(0) + 127397,
        normalized.charCodeAt(1) + 127397
    );
}

function getFlagSymbol(code) {
    const flagCode = languageFlagCodes[code] || "cn";
    const normalized = String(flagCode || "CN").slice(0, 2).toUpperCase();

    if (!/^[A-Z]{2}$/.test(normalized)) {
        return String.fromCodePoint(0x1F3F3, 0xFE0F);
    }

    return String.fromCodePoint(
        normalized.charCodeAt(0) + 127397,
        normalized.charCodeAt(1) + 127397
    );
}

function escapeHtml(value) {
    return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function setMetaContent(name, value) {
    if (!value) return;
    const attr = name.startsWith("og:") ? "property" : "name";
    const escaped = CSS.escape(name);
    let el = document.querySelector(`meta[${attr}="${escaped}"]`);
    if (!el) {
        el = document.createElement("meta");
        el.setAttribute(attr, name);
        document.head.appendChild(el);
    }
    el.setAttribute("content", value);
}

function setCanonicalUrl(url) {
    if (!url) return;
    let el = document.querySelector("link[rel='canonical']");
    if (!el) {
        el = document.createElement("link");
        el.setAttribute("rel", "canonical");
        document.head.appendChild(el);
    }
    el.setAttribute("href", url);
}

function safeStorageGet(key) {
    try {
        return window.localStorage?.getItem(key);
    } catch (error) {
        return null;
    }
}

function safeStorageSet(key, value) {
    try {
        window.localStorage?.setItem(key, value);
    } catch (error) {
        return;
    }
}

function safeStorageRemove(key) {
    try {
        window.localStorage?.removeItem(key);
    } catch (error) {
        return;
    }
}

function readStoredPublicLanguage() {
    return typeof safeStorageGet === "function" ? String(safeStorageGet("hanzun-lang") || "").trim() : "";
}

function readStoredPublicLanguageSource() {
    return typeof safeStorageGet === "function" ? String(safeStorageGet("hanzun-lang-source") || "").trim() : "";
}

function hasStoredManualLanguageChoice() {
    return readStoredPublicLanguage() !== "" && readStoredPublicLanguageSource() === "manual";
}

function hasExplicitPublicLanguageChoice() {
    return String(body?.dataset.forceLang || "").trim() !== "" || hasStoredManualLanguageChoice();
}

function resolveInitialPublicLanguage() {
    const forced = String(body?.dataset.forceLang || "").trim();
    if (forced) {
        return forced;
    }

    // URL ?lang=xx（伪静态注入或直接参数）
    const urlLang = new URLSearchParams(window.location.search).get('lang');
    if (urlLang) {
        const normalized = typeof normalizedLang === "function" ? normalizedLang(urlLang) : null;
        if (normalized) return normalized;
    }

    if (hasStoredManualLanguageChoice()) {
        const stored = readStoredPublicLanguage();
        if (stored) {
            return stored;
        }
    }

    const detected = typeof detectBrowserLanguage === "function" ? String(detectBrowserLanguage() || "").trim() : "";
    if (detected) {
        return detected;
    }

    return String(body?.dataset.lang || "zh").trim() || "zh";
}

function resolvePreferredRuntimeLanguage(site, currentCode) {
    const forced = String(body?.dataset.forceLang || "").trim();
    if (forced) {
        return forced;
    }

    if (hasStoredManualLanguageChoice()) {
        return readStoredPublicLanguage();
    }

    const strategy = String(site?.language_strategy || "ua-first").trim();
    const defaultLanguage = String(site?.default_language || "").trim();

    if (strategy === "default-first" && defaultLanguage) {
        return defaultLanguage;
    }

    return String(currentCode || readStoredPublicLanguage() || detectBrowserLanguage() || defaultLanguage || body?.dataset.lang || "zh").trim() || "zh";
}

function toApiLanguageCode(code) {
    const normalized = String(code || "").trim().toLowerCase();

    if (!normalized) {
        return "zh";
    }

    if (normalized.startsWith("zh")) {
        return "zh";
    }

    return normalized.slice(0, 2);
}

function detectBrowserLanguage() {
    const candidates = [];

    if (Array.isArray(navigator.languages)) {
        candidates.push(...navigator.languages);
    }

    if (navigator.language) {
        candidates.push(navigator.language);
    }

    for (const candidate of candidates) {
        const mapped = normalizedLang(candidate);

        if (mapped) {
            return mapped;
        }
    }

    return "zh";
}

const supportClientStorageKey = "hanzun-client-id";
const supportSessionStorageKey = "hanzun-support-session";
const supportSessionFallbackStorageKey = "hanzun-support-session-fallback";
let supportDefaultMessagesMarkup = supportMessages ? supportMessages.innerHTML : "";
const supportState = {
    sending: false,
    hydratePromise: null,
    hydratedSessionCode: "",
    lastFailedMessage: "",
    lastInquiryId: 0,
    noticeNodes: new Map(),
};
let supportSessionStorageAvailable = null;

function ensureSupportClientId() {
    let clientId = safeStorageGet(supportClientStorageKey);
    if (clientId) {
        return clientId;
    }

    clientId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    safeStorageSet(supportClientStorageKey, clientId);
    return clientId;
}

function resolveSessionStorageFallbackKey(key) {
    return key === supportSessionStorageKey ? supportSessionFallbackStorageKey : "";
}

function hasUsableSessionStorage() {
    if (typeof supportSessionStorageAvailable === "boolean") {
        return supportSessionStorageAvailable;
    }

    try {
        if (!window.sessionStorage) {
            supportSessionStorageAvailable = false;
            return false;
        }

        const probeKey = "__hanzun_session_probe__";
        window.sessionStorage.setItem(probeKey, "1");
        window.sessionStorage.removeItem(probeKey);
        supportSessionStorageAvailable = true;
        return true;
    } catch (error) {
        supportSessionStorageAvailable = false;
        return false;
    }
}

function safeSessionStorageGet(key) {
    try {
        if (hasUsableSessionStorage()) {
            return window.sessionStorage.getItem(key);
        }
    } catch (error) {
        supportSessionStorageAvailable = false;
    }

    const fallbackKey = resolveSessionStorageFallbackKey(key);
    return fallbackKey ? safeStorageGet(fallbackKey) : null;
}

function safeSessionStorageSet(key, value) {
    try {
        if (hasUsableSessionStorage()) {
            window.sessionStorage.setItem(key, value);
            const fallbackKey = resolveSessionStorageFallbackKey(key);
            if (fallbackKey) {
                safeStorageRemove(fallbackKey);
            }
            return;
        }
    } catch (error) {
        supportSessionStorageAvailable = false;
    }

    const fallbackKey = resolveSessionStorageFallbackKey(key);
    if (fallbackKey) {
        safeStorageSet(fallbackKey, value);
    }
}

function safeSessionStorageRemove(key) {
    try {
        if (hasUsableSessionStorage()) {
            window.sessionStorage.removeItem(key);
        }
    } catch (error) {
        supportSessionStorageAvailable = false;
    }

    const fallbackKey = resolveSessionStorageFallbackKey(key);
    if (fallbackKey) {
        safeStorageRemove(fallbackKey);
    }
}

function currentSupportSessionCode() {
    try {
        window.localStorage?.removeItem(supportSessionStorageKey);
    } catch (error) {
        // ignore stale legacy session storage in localStorage
    }

    return safeSessionStorageGet(supportSessionStorageKey) || "";
}

function currentSupportLanguage() {
    return getContentLanguage(normalizedLang(body?.dataset.lang || "zh")) === "en" ? "en" : "zh";
}

function currentSupportPath() {
    return window.location.pathname + window.location.search;
}

function currentUtmSource() {
    try {
        return new URLSearchParams(window.location.search).get("utm_source") || "";
    } catch (error) {
        return "";
    }
}

function readConfiguredPublicApiBase() {
    const runtime = readPublicRuntimeConfig();
    const configured = [
        runtime?.public_api_base,
        runtime?.publicApiBase,
        runtime?.api_base,
        runtime?.apiBase,
    ].find((value) => typeof value === "string" && value.trim() !== "");

    return configured ? configured.replace(/\/+$/, "") : "";
}

function isPrivatePreviewHostname(hostname) {
    const value = String(hostname || "").trim().toLowerCase();
    if (!value) {
        return false;
    }

    if (value === "localhost" || value === "0.0.0.0" || value === "::1" || value === "[::1]") {
        return true;
    }

    if (/^127(?:\.\d{1,3}){3}$/.test(value)) {
        return true;
    }

    if (/^10(?:\.\d{1,3}){3}$/.test(value)) {
        return true;
    }

    if (/^192\.168(?:\.\d{1,3}){2}$/.test(value)) {
        return true;
    }

    if (/^172\.(1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2}$/.test(value)) {
        return true;
    }

    return false;
}

function resolvePublicApiOrigin() {
    const configuredBase = readConfiguredPublicApiBase();
    if (configuredBase) {
        return configuredBase;
    }

    const location = window.location;
    if (!location || !/^https?:$/i.test(location.protocol || "")) {
        return "";
    }

    if (String(location.port || "") === "8091" && isPrivatePreviewHostname(location.hostname)) {
        return `${location.protocol}//${location.hostname}:8080`;
    }

    return "";
}

function publicApiUrl(path) {
    const value = String(path || "").trim();
    if (!value) {
        return "";
    }

    if (/^(https?:)?\/\//i.test(value)) {
        return value;
    }

    const normalized = value.startsWith("/") ? value : `/${value}`;
    const origin = resolvePublicApiOrigin();
    return origin ? `${origin}${normalized}` : normalized;
}

async function postPublicApi(path, payload, keepalive = false) {
    const response = await fetch(publicApiUrl(path), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
        keepalive,
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || !result || Number(result.code) !== 0) {
        const error = new Error(result && typeof result.message === "string" ? result.message : "Request failed");
        error.code = result && Number(result.code) || 0;
        throw error;
    }

    if (result.data && result.data.session_code) {
        safeSessionStorageSet(supportSessionStorageKey, result.data.session_code);
    }

    return result.data || {};
}

async function getPublicApi(path) {
    const response = await fetch(publicApiUrl(path), {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });
    const result = await response.json().catch(() => null);

    if (!response.ok || !result || Number(result.code) !== 0) {
        const error = new Error(result && typeof result.message === "string" ? result.message : "Request failed");
        error.code = result && Number(result.code) || 0;
        throw error;
    }

    return result;
}

const progressiveMediaWrapperSelector = [
    ".delivery-media",
    ".showcase-feature-media",
    ".showcase-mini-media",
    ".notice-banner-media",
    ".metrics-dashboard-visual",
    ".metrics-cert-media",
    ".case-hero-media",
    ".case-list-media",
    ".news-media",
    ".sales-avatar",
    ".wechat-card-qr",
].join(", ");

function getProgressiveMediaWrapper(image) {
    return image.closest(progressiveMediaWrapperSelector) || image.parentElement;
}

function initProgressiveMedia() {
    document.querySelectorAll("img[data-progressive-media]").forEach((image) => {
        const wrapper = getProgressiveMediaWrapper(image);

        if (!image.hasAttribute("decoding")) {
            image.setAttribute("decoding", "async");
        }

        if (!image.hasAttribute("loading")) {
            image.setAttribute("loading", "lazy");
        }

        // Apply blur-up placeholder effect
        image.classList.add("progressive-blur");

        const markReady = () => {
            image.classList.add("is-loaded");
            image.classList.remove("progressive-blur");
            wrapper?.classList.add("media-ready");
        };

        const markError = () => {
            image.classList.add("is-error");
            image.classList.remove("progressive-blur");
            wrapper?.classList.add("media-ready");
        };

        if (image.complete && image.naturalWidth > 0) {
            markReady();
            return;
        }

        wrapper?.classList.remove("media-ready");
        image.addEventListener("load", markReady, { once: true });
        image.addEventListener("error", markError, { once: true });
    });
}

function initLazyVideo() {
    const video = document.querySelector("video[data-progressive-video]");
    if (!video) return;

    const source = String(video.getAttribute("data-src") || video.getAttribute("src") || "").trim();
    if (video.hasAttribute("src")) {
        video.removeAttribute("src");
    }
    video.setAttribute("preload", "none");

    const loadVideo = () => {
        if (!source || video.getAttribute("src")) {
            return;
        }
        video.src = source;
        video.setAttribute("preload", "metadata");
        video.classList.add("video-ready");
        video.load();
    };

    video.addEventListener("play", () => {
        loadVideo();
    }, { once: true });

    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    loadVideo();
                    observer.unobserve(video);
                }
            });
        }, { rootMargin: "200px" });
        observer.observe(video);
    } else {
        loadVideo();
    }
}

function collectSalesContacts() {
    return Array.from(document.querySelectorAll(".sales-card")).map((card) => {
        const name = card.querySelector(".sales-name-bar strong")?.textContent?.trim() || "";
        const links = Array.from(card.querySelectorAll('a[href^="mailto:"], a[href^="tel:"]'));
        const emailLink = links.find((link) => link.getAttribute("href")?.startsWith("mailto:"));
        const phoneLink = links.find((link) => link.getAttribute("href")?.startsWith("tel:"));
        const emailHref = emailLink?.getAttribute("href") || "";
        const phoneHref = phoneLink?.getAttribute("href") || "";

        return {
            name,
            email: emailHref.replace(/^mailto:/i, "").trim(),
            emailHref,
            phone: phoneHref.replace(/^tel:/i, "").trim(),
            phoneHref,
        };
    }).filter((contact) => contact.name && (contact.email || contact.phone));
}

function populateSalesContactMenus() {
    const contacts = collectSalesContacts();

    if (!contacts.length) {
        return;
    }

    document.querySelectorAll("[data-contact-list]").forEach((menu) => {
        if (menu.dataset.staticContactMenu === "1" || menu.querySelector("a")) {
            return;
        }

        const nextMarkup = renderSalesContactMenu(menu.dataset.contactList, contacts);

        if (!nextMarkup) {
            return;
        }

        menu.innerHTML = nextMarkup;
    });
}

function hydrateStaticCategoryFilters() {
    const filterRoot = document.querySelector('[data-category-filter-root="1"]');

    if (!filterRoot) {
        return;
    }

    const categoryButtons = Array.from(filterRoot.querySelectorAll("[data-category-slug]"));
    const categoryCards = Array.from(document.querySelectorAll("[data-category-card]"));
    const activeSlug = String(new URLSearchParams(window.location.search).get("category") || "").trim().toLowerCase();
    const hasMatchingButton = activeSlug !== "" && categoryButtons.some((button) => String(button.dataset.categorySlug || "").trim().toLowerCase() === activeSlug);
    const effectiveSlug = hasMatchingButton ? activeSlug : "";
    const allButton = filterRoot.querySelector('[data-category-all="1"]');

    if (allButton) {
        allButton.classList.toggle("is-active", effectiveSlug === "");
        if (effectiveSlug !== "") {
            allButton.removeAttribute("aria-current");
        } else {
            allButton.setAttribute("aria-current", "page");
        }
    }

    categoryButtons.forEach((button) => {
        const buttonSlug = String(button.dataset.categorySlug || "").trim().toLowerCase();
        const isActive = effectiveSlug !== "" && buttonSlug === effectiveSlug;
        button.classList.toggle("is-active", isActive);
        if (isActive) {
            button.setAttribute("aria-current", "page");
        } else {
            button.removeAttribute("aria-current");
        }
    });

    categoryCards.forEach((card) => {
        const cardSlug = String(card.dataset.categorySlug || "").trim().toLowerCase();
        const matches = effectiveSlug === "" || cardSlug === effectiveSlug;
        card.hidden = !matches;
        card.style.display = matches ? "" : "none";
    });
}


function getAnchorOffset() {
    const header = document.querySelector(".site-header");
    return (header?.offsetHeight || 0) + 18;
}

function renderSalesContactMenu(type, contacts) {
    const key = type === "phone" ? "phone" : "email";
    const hrefKey = key === "phone" ? "phoneHref" : "emailHref";

    return contacts
        .filter((contact) => contact[key] && contact[hrefKey])
        .map((contact) => `
            <a class="float-option float-option-inline" href="${escapeHtml(contact[hrefKey])}">
                <strong>${escapeHtml(`${contact.name}: ${contact[key]}`)}</strong>
            </a>
        `)
        .join("");
}

function scrollToAnchorTarget(hash, updateHistory = false, behavior = "smooth") {
    if (!hash || !hash.startsWith("#")) {
        return false;
    }

    const target = document.querySelector(hash);

    if (!target) {
        return false;
    }

    const targetTop = target.getBoundingClientRect().top + window.scrollY - getAnchorOffset();
    window.scrollTo({
        top: Math.max(0, targetTop),
        behavior,
    });

    if (updateHistory) {
        window.history.pushState(null, "", hash);
    }

    return true;
}

function bindHeroAnchorButtons() {
    if (!heroAnchorButtons.length) {
        return;
    }

    heroAnchorButtons.forEach((link) => {
        link.addEventListener("click", (event) => {
            const hash = link.getAttribute("href");

            if (!scrollToAnchorTarget(hash, true)) {
                return;
            }

            event.preventDefault();
        });
    });
}

function syncInitialHeroHash() {
    if (window.location.hash !== "#contact" && window.location.hash !== "#clients") {
        return;
    }

    window.setTimeout(() => {
        scrollToAnchorTarget(window.location.hash, false, "auto");
    }, 120);
}

if (mobileProductAccordion) {
    mobileProductAccordion.className = "mobile-product-accordion";
    mobileProductAccordion.hidden = true;
    productPanel.appendChild(mobileProductAccordion);
}

let languageMenuRendered = false;

function renderLanguageMenu() {
    if (!langMenu) {
        return;
    }

    const activeLang = normalizedLang(body?.dataset.lang || "zh");

    langMenu.innerHTML = languageGroups.map((group) => {
        const entries = languages.filter((lang) => lang.continent === group.key);

        if (!entries.length) {
            return "";
        }

        return `
            <section class="lang-group">
                <div class="lang-group-title">${getLanguageGroupLabel(group)}</div>
                <div class="lang-group-grid">
                    ${entries.map((lang) => {
                        const isTranslated = lang.content === lang.code.slice(0, 2);
                        return `
                        <button type="button" data-lang-option="${lang.code}"${lang.code === activeLang ? ' class="active"' : ""}>
                            <span class="lang-option-flag" aria-hidden="true">${getFlagBadgeSvg(lang.code)}</span>
                            <span class="lang-option-name">
                                <strong>${getLanguageLabel(lang.code)}</strong>
                                ${isTranslated ? "" : '<small class="lang-fallback-note">(English)</small>'}
                            </span>
                        </button>
                    `}).join("")}
                </div>
            </section>
        `;
    }).join("");
}

function ensureLanguageMenuRendered() {
    if (languageMenuRendered) {
        return;
    }

    renderLanguageMenu();
    languageMenuRendered = true;
}

function ensureSectionHeading(sectionId, zhTitle, enTitle) {
    const heading = document.querySelector(`#${sectionId} .section-heading`);
    const eyebrow = heading?.querySelector(".eyebrow");

    if (!heading || !eyebrow) {
        return null;
    }

    let copy = heading.querySelector(".section-heading-copy");

    if (!copy) {
        copy = document.createElement("div");
        copy.className = "section-heading-copy";
        heading.insertBefore(copy, heading.firstChild);
        copy.appendChild(eyebrow);
    }

    let title = copy.querySelector(".section-heading-title");

    if (!title) {
        title = document.createElement("h2");
        title.className = "section-heading-title";
        copy.appendChild(title);
    }

    title.dataset.zh = zhTitle;
    title.dataset.en = enTitle;
    title.textContent = zhTitle;

    heading.classList.add("section-heading-mobile-action");
    return heading;
}

function injectSectionMoreLink(sectionId, zhTitle, enTitle, href, zhText, enText) {
    const heading = ensureSectionHeading(sectionId, zhTitle, enTitle);

    if (!heading || heading.querySelector(".section-more-link")) {
        return;
    }

    const link = document.createElement("a");
    link.className = "section-more-link";
    link.href = href;
    link.dataset.zh = zhText;
    link.dataset.en = enText;
    link.textContent = zhText;
    heading.appendChild(link);
}

function injectSectionSurfaceLink(sectionId, hostSelector, href, zhText, enText) {
    const host = document.querySelector(`#${sectionId} ${hostSelector}`);

    if (!host || host.querySelector(".section-surface-link")) {
        return;
    }

    const link = document.createElement("a");
    link.className = "section-surface-link";
    link.href = href;
    link.dataset.zh = zhText;
    link.dataset.en = enText;
    link.textContent = zhText;
    host.appendChild(link);
}

function enhanceSectionHeadingActions() {
    return;
}

let publicBootstrapRequestVersion = 0;

const homepageFallbackImages = {
    solution: [
        "assets/images/home/company-strength-process-generated.jpg",
        "assets/images/home/equipment-integrated-line.jpg",
        "assets/images/home/equipment-transfer-line.jpg",
        "assets/images/home/equipment-depositing-station.jpg",
        "assets/images/home/equipment-forming-module.jpg",
    ],
    product: [
        "assets/images/home/equipment-forming-module.jpg",
        "assets/images/home/equipment-transfer-line.jpg",
        "assets/images/home/equipment-integrated-line.jpg",
        "assets/images/home/equipment-depositing-station.jpg",
    ],
    case: [
        "assets/images/home/equipment-integrated-line.jpg",
        "assets/images/home/equipment-transfer-line.jpg",
        "assets/images/home/company-strength-real.jpg",
        "assets/images/home/news-real-booth.jpg",
    ],
    news: [
        "assets/images/home/news-real-expo-hall.jpg",
        "assets/images/home/news-real-booth.jpg",
        "assets/images/home/news-real-handshake-team.jpg",
        "assets/images/home/news-real-handshake-table.jpg",
        "assets/images/home/news-real-business-pose.jpg",
    ],
};

function getLocalizedRuntimeCopy(zhText, enText) {
    return currentSupportLanguage() === "en" ? enText : zhText;
}

function buildStaticPublicHref(type, slug = "") {
    const normalizedType = String(type || "").trim().toLowerCase();
    const normalizedSlug = String(slug || "").trim();

    if (normalizedType === "about") {
        return localizedStaticFile("about.html");
    }

    if (normalizedType === "contact") {
        return `${localizedStaticFile("about.html")}#contact`;
    }

    if (normalizedType === "products") {
        return normalizedSlug ? localizedStaticFile(`products/${encodeURIComponent(normalizedSlug)}.html`) : localizedStaticFile("products.html");
    }

    if (normalizedType === "solutions") {
        return normalizedSlug ? localizedStaticFile(`solutions/${encodeURIComponent(normalizedSlug)}.html`) : localizedStaticFile("solutions.html");
    }

    if (normalizedType === "news") {
        return normalizedSlug ? localizedStaticFile(`news/${encodeURIComponent(normalizedSlug)}.html`) : localizedStaticFile("news.html");
    }

    if (normalizedType === "cases") {
        return normalizedSlug ? localizedStaticFile(`cases/${encodeURIComponent(normalizedSlug)}.html`) : localizedStaticFile("cases.html");
    }

    if (normalizedType === "pages") {
        return normalizedSlug ? localizedStaticFile(`pages/${encodeURIComponent(normalizedSlug)}.html`) : localizedStaticFile("about.html");
    }

    return "";
}

function mapStaticPublicHref(candidate, item) {
    const route = String(candidate || "").trim();

    if (!route) {
        return "";
    }

    const normalized = route
        .replace(/^https?:\/\/[^/]+/i, "")
        .replace(/^\/+/, "")
        .replace(/^[a-z]{2}\//i, "")
        .replace(/^#/, "");

    if (!normalized) {
        return "";
    }

    if (normalized === "about" || normalized === "contact") {
        return buildStaticPublicHref(normalized);
    }

    if (normalized === "products" || normalized.startsWith("products/")) {
        const segments = normalized.split("/");
        const slug = String(item?.slug || (segments.length > 1 ? segments[segments.length - 1].replace(/\.html$/i, "") : "")).trim();
        return buildStaticPublicHref("products", slug);
    }

    if (normalized === "solutions" || normalized.startsWith("solutions/")) {
        const segments = normalized.split("/");
        const slug = String(item?.slug || (segments.length > 1 ? segments[segments.length - 1].replace(/\.html$/i, "") : "")).trim();
        return buildStaticPublicHref("solutions", slug);
    }

    if (normalized === "news" || normalized.startsWith("news/")) {
        const segments = normalized.split("/");
        const slug = String(item?.slug || (segments.length > 1 ? segments[segments.length - 1].replace(/\.html$/i, "") : "")).trim();
        return buildStaticPublicHref("news", slug);
    }

    if (normalized === "cases" || normalized.startsWith("cases/")) {
        const segments = normalized.split("/");
        const slug = String(item?.slug || (segments.length > 1 ? segments[segments.length - 1].replace(/\.html$/i, "") : "")).trim();
        return buildStaticPublicHref("cases", slug);
    }

    if (normalized === "pages" || normalized.startsWith("pages/")) {
        const segments = normalized.split("/");
        const slug = String(item?.slug || (segments.length > 1 ? segments[segments.length - 1].replace(/\.html$/i, "") : "")).trim();
        return buildStaticPublicHref("pages", slug);
    }

    return "";
}

function resolvePublicHref(item, fallback = "#contact-form") {
    const directUrl = String(item?.route_path || item?.url || "").trim();
    const mappedDirectUrl = mapStaticPublicHref(directUrl, item);

    if (mappedDirectUrl) {
        return mappedDirectUrl;
    }

    if (directUrl) {
        return directUrl;
    }

    const routeKey = String(item?.route_key || "").trim().replace(/^\/+/, "");
    const mappedRouteKey = mapStaticPublicHref(routeKey, item);

    if (mappedRouteKey) {
        return mappedRouteKey;
    }

    if (routeKey) {
        return localizedStaticFile(routeKey);
    }

    const slug = String(item?.slug || "").trim();
    const sourceType = String(item?.source_type || "").trim().toLowerCase();
    const linkedEntityType = String(item?.linked_entity_type || "").trim().toLowerCase();
    const contentType = String(item?.content_type || "").trim().toLowerCase();
    const inferredType = sourceType
        || (item?.sku ? "product" : "")
        || (contentType === "product" ? "product" : "")
        || (contentType === "solution" ? "solution" : "")
        || (contentType === "news" ? "news" : "")
        || (contentType === "case" ? "case" : "")
        || (linkedEntityType === "solution_category" ? "solution" : "")
        || (contentType === "page" ? "page" : "")
        || (String(item?.page_key || "").includes("about") || String(item?.code || "").includes("about") ? "about" : "");

    if (slug && inferredType === "product") {
        return buildStaticPublicHref("products", slug);
    }

    if (slug && inferredType === "solution") {
        return buildStaticPublicHref("solutions", slug);
    }

    if (slug && inferredType === "case") {
        return buildStaticPublicHref("cases", slug);
    }

    if (slug && inferredType === "news") {
        return buildStaticPublicHref("news", slug);
    }

    if (slug) {
        return buildStaticPublicHref("pages", slug);
    }

    if (inferredType === "about") {
        return buildStaticPublicHref("about");
    }

    if (String(item?.code || "").trim().toLowerCase() === "contact") {
        return buildStaticPublicHref("contact");
    }

    return fallback;
}

function resolveContentTitle(item) {
    return String(
        item?.display_title ||
        item?.name ||
        item?.title ||
        item?.name_zh ||
        item?.title_zh ||
        ""
    ).trim();
}

function resolveContentSummary(item) {
    return String(
        item?.display_summary ||
        item?.summary ||
        item?.subtitle ||
        item?.summary_zh ||
        item?.subtitle_zh ||
        item?.description ||
        ""
    ).trim();
}

function resolveFallbackImage(type, index) {
    const pool = homepageFallbackImages[type] || homepageFallbackImages.news;
    return assetPath(pool[index % pool.length]);
}

function resolveContentImage(item, type, index) {
    const candidates = [
        item?.cover_asset_url,
        item?.cover_asset?.public_url,
        item?.cover_image_url,
    ];

    for (const candidate of candidates) {
        const value = String(candidate || "").trim();
        if (value) {
            return assetPath(value);
        }
    }

    return resolveFallbackImage(type, index);
}

function updateImageNode(image, src, alt) {
    if (!image || !src) {
        return;
    }

    image.src = src;
    image.alt = alt || image.alt || "Hanzun content";
    image.setAttribute("data-progressive-media", "");
}

function hydrateSiteConfig(site) {
    publicSiteConfig = site && typeof site === "object" ? site : null;
    window.__HANZUN_PUBLIC_SITE_CONFIG__ = publicSiteConfig;

    if (!publicSiteConfig) {
        return;
    }

    const logoUrl = String(publicSiteConfig.logo_url || "").trim();
    const companyName = String(publicSiteConfig.company_name || "").trim();
    const companySubtitle = String(publicSiteConfig.company_subtitle || "").trim();
    const logoAlt = String(publicSiteConfig.logo_alt || companyName || publicSiteConfig.site_name || "Hanzun").trim();
    const footerText = String(publicSiteConfig.footer_text || "").trim();

    brandLinks.forEach((node) => {
        if (companyName) {
            node.setAttribute("aria-label", companyName);
        }
    });

    [...brandLogos, ...footerBrandLogos].forEach((node) => {
        if (logoUrl) {
            node.src = logoUrl;
        }
        node.alt = logoAlt;
    });

    [...brandTitleNodes, ...footerBrandTitleNodes].forEach((node) => {
        if (companyName) {
            node.textContent = companyName;
        }
    });

    [...brandSubtitleNodes, ...footerBrandSubtitleNodes].forEach((node) => {
        if (!node || String(node.textContent || "").trim() || !companySubtitle) {
            return;
        }

        node.textContent = companySubtitle;
    });

    footerBottomNodes.forEach((node) => {
        if (footerText) {
            node.textContent = footerText;
        }
    });
}

function readPublicSiteConfig() {
    return window.__HANZUN_PUBLIC_SITE_CONFIG__ || publicSiteConfig || null;
}

function bindNavigableCard(node, href, label) {
    if (!node || !href) {
        return;
    }

    node.dataset.publicHref = href;
    node.style.cursor = "pointer";
    node.setAttribute("role", "link");
    node.setAttribute("tabindex", "0");

    if (label) {
        node.setAttribute("aria-label", label);
    }

    if (node.dataset.publicHrefBound === "true") {
        return;
    }

    node.dataset.publicHrefBound = "true";
    node.addEventListener("click", () => {
        const nextHref = node.dataset.publicHref;

        if (nextHref) {
            window.location.href = nextHref;
        }
    });
    node.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }

        event.preventDefault();
        const nextHref = node.dataset.publicHref;

        if (nextHref) {
            window.location.href = nextHref;
        }
    });
}

function homepageSectionByKey(homepage, sectionKey) {
    return Array.isArray(homepage?.sections)
        ? homepage.sections.find((section) => section?.section_key === sectionKey)
        : null;
}

function hydrateHeroSection(homepage) {
    const heroSection = homepageSectionByKey(homepage, "hero");
    const titleNode = document.querySelector(".service-support-copy h2");
    const buttonNode = document.querySelector(".service-support-button");

    if (!heroSection) {
        return;
    }

    if (titleNode && heroSection.title) {
        titleNode.textContent = heroSection.title;
    }

    const ctaText = String(heroSection?.extra_config?.cta_text || heroSection?.content || "").trim();

    if (buttonNode && ctaText) {
        buttonNode.textContent = ctaText;
    }
}

function hydrateSolutionsGrid(items) {
    const cards = Array.from(featuredSolutionsGrid?.querySelectorAll(".delivery-card") || []);

    items.slice(0, cards.length).forEach((item, index) => {
        const card = cards[index];
        const titleNode = card?.querySelector("h3");
        const imageNode = card?.querySelector("img");
        const title = resolveContentTitle(item);

        if (!card || !title) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = title;
        }

        updateImageNode(imageNode, resolveContentImage(item, "solution", index), title);
        bindNavigableCard(card, resolvePublicHref(item, "/solutions"), title);
    });
}

function hydrateProductsShowcase(items) {
    if (!featuredProductsShowcase || !items.length) {
        return;
    }

    const heroCard = featuredProductsShowcase.querySelector(".showcase-feature-card");
    const heroTitle = heroCard?.querySelector("h3");
    const heroKicker = heroCard?.querySelector(".showcase-kicker");
    const heroImage = heroCard?.querySelector("img");
    const firstItem = items[0];

    if (heroCard && firstItem) {
        const heroHeading = resolveContentTitle(firstItem);
        const heroSummary = resolveContentSummary(firstItem);

        if (heroTitle && heroHeading) {
            heroTitle.textContent = heroHeading;
        }

        if (heroKicker && heroSummary) {
            heroKicker.textContent = heroSummary;
        }

        updateImageNode(heroImage, resolveContentImage(firstItem, "product", 0), heroHeading);
        bindNavigableCard(heroCard, resolvePublicHref(firstItem, "/products"), heroHeading);
    }

    const miniCards = Array.from(featuredProductsShowcase.querySelectorAll(".showcase-mini-card"));

    items.slice(1, miniCards.length + 1).forEach((item, index) => {
        const card = miniCards[index];
        const titleNode = card?.querySelector("h3");
        const kickerNode = card?.querySelector(".showcase-kicker");
        const imageNode = card?.querySelector("img");
        const title = resolveContentTitle(item);
        const summary = resolveContentSummary(item);

        if (!card || !title) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = title;
        }

        if (kickerNode && summary) {
            kickerNode.textContent = summary;
        }

        updateImageNode(imageNode, resolveContentImage(item, "product", index + 1), title);
        bindNavigableCard(card, resolvePublicHref(item, "/products"), title);
    });
}

function hydrateCasesBoard(items) {
    if (!featuredCasesBoard) {
        return;
    }

    const caseItems = items.filter((item) => String(item?.content_type || "").toLowerCase() === "case" || String(item?.country_code || "").trim() !== "");

    if (!caseItems.length) {
        return;
    }

    const heroItem = caseItems[0];
    const heroCard = featuredCasesBoard.querySelector(".case-hero-card");
    const heroTitle = heroCard?.querySelector(".case-hero-title");
    const heroImage = heroCard?.querySelector("img");
    const heroFlags = heroCard?.querySelector(".case-hero-flags");
    const heroHeading = resolveContentTitle(heroItem);

    if (heroCard && heroHeading) {
        if (heroTitle) {
            heroTitle.textContent = heroHeading;
        }

        if (heroFlags && heroItem.country_code) {
            const code = String(heroItem.country_code || "").slice(0, 2).toLowerCase();
            heroFlags.innerHTML = `<img src="${assetPath(`assets/images/flags/${escapeHtml(code)}.svg`)}" alt="">`;
        }

        updateImageNode(heroImage, resolveContentImage(heroItem, "case", 0), heroHeading);
        bindNavigableCard(heroCard, resolvePublicHref(heroItem, "/cases"), heroHeading);
    }

    const listCards = Array.from(featuredCasesBoard.querySelectorAll(".case-list-item"));

    caseItems.slice(1, listCards.length + 1).forEach((item, index) => {
        const card = listCards[index];
        const titleNode = card?.querySelector(".case-title-text");
        const flagsNode = card?.querySelector(".case-title-flags");
        const imageNode = card?.querySelector("img");
        const title = resolveContentTitle(item);

        if (!card || !title) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = title;
        }

        if (flagsNode && item.country_code) {
            const code = String(item.country_code || "").slice(0, 2).toLowerCase();
            flagsNode.innerHTML = `<img src="${assetPath(`assets/images/flags/${escapeHtml(code)}.svg`)}" alt="">`;
        }

        updateImageNode(imageNode, resolveContentImage(item, "case", index + 1), title);
        bindNavigableCard(card, resolvePublicHref(item, "/cases"), title);
    });
}

function hydrateNewsGrid(items) {
    const cards = Array.from(featuredNewsGrid?.querySelectorAll(".news-card") || []);
    const newsItems = items.filter((item) => String(item?.content_type || "news").toLowerCase() !== "case");

    newsItems.slice(0, cards.length).forEach((item, index) => {
        const card = cards[index];
        const titleNode = card?.querySelector("h3");
        const tagNode = card?.querySelector(".news-card-tag");
        const imageNode = card?.querySelector("img");
        const title = resolveContentTitle(item);
        const tagText = resolveContentSummary(item);

        if (!card || !title) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = title;
        }

        if (tagNode && tagText) {
            tagNode.textContent = tagText;
        }

        updateImageNode(imageNode, resolveContentImage(item, "news", index), title);
        bindNavigableCard(card, resolvePublicHref(item, "/news"), title);
    });
}

function updateMultiValueNode(container, values) {
    if (!container || !values.length) {
        return;
    }

    container.innerHTML = values.map((value) => `<span>${escapeHtml(value)}</span>`).join("");
}

function hydrateContactSlot(card, items, fallbackHrefPrefix = "") {
    if (!card || !items.length) {
        return;
    }

    const first = items[0];
    const labelNode = card.querySelector("small");
    const strongNode = card.querySelector("strong");
    const hrefValue = String(first.value || "").trim();

    if (labelNode && first.label) {
        labelNode.textContent = first.label;
    }

    updateMultiValueNode(strongNode, items.map((item) => String(item.value || "").trim()).filter(Boolean));

    if (card.tagName === "A" && hrefValue) {
        if (fallbackHrefPrefix) {
            card.href = `${fallbackHrefPrefix}${hrefValue}`;
        } else {
            card.href = hrefValue;
        }
    }
}

function hydrateContactPanels(contact) {
    var allItems = Array.isArray(contact?.items) ? contact.items : [];
    var currentLang = String(body?.dataset?.lang || 'zh').slice(0, 2).toLowerCase();
    var priorityKeys = getRegionalContactKeys(currentLang);
    var filteredItems = allItems.filter(function(item) {
        var fieldKey = String(item.field_key || '').toLowerCase();
        return priorityKeys.indexOf(fieldKey) >= 0;
    });
    if (!filteredItems.length) filteredItems = allItems;

    if (contactGrid) {
        const contactCards = Array.from(contactGrid.querySelectorAll(".contact-card"));
        const pageItems = filteredItems.filter((item) => ["contact_page", "footer", "global", ""].includes(String(item.display_scope || "")));

        hydrateContactSlot(
            contactCards[0],
            pageItems.filter((item) => String(item.field_key) === "email"),
            "mailto:"
        );
        hydrateContactSlot(
            contactCards[1],
            pageItems.filter((item) => String(item.field_key) === "phone"),
            "tel:"
        );
    }

    if (footerContactList) {
        const footerCards = Array.from(footerContactList.querySelectorAll(".footer-brand-contact"));
        const footerItems = filteredItems.filter((item) => ["footer", "contact_page", "global", ""].includes(String(item.display_scope || "")));
        const messageItems = footerItems.filter((item) => !["email", "phone"].includes(String(item.field_key)));

        hydrateContactSlot(
            footerCards[0],
            footerItems.filter((item) => String(item.field_key) === "email"),
            "mailto:"
        );
        hydrateContactSlot(
            footerCards[1],
            footerItems.filter((item) => String(item.field_key) === "phone"),
            "tel:"
        );

        if (footerCards[2] && messageItems.length) {
            const card = footerCards[2];
            const item = messageItems[0];
            const labelNode = card.querySelector("small");
            const strongNode = card.querySelector("strong");
            const fieldKey = String(item.field_key || "").trim().toLowerCase();
            const rawValue = String(item.value || "").trim();
            const digitsOnly = rawValue.replace(/\D/g, "");

            if (labelNode) {
                labelNode.textContent = item.label || item.field_name || item.field_key || "Contact";
            }

            if (strongNode) {
                strongNode.textContent = rawValue;
            }

            if (card.tagName === "A") {
                if (fieldKey === "whatsapp" && digitsOnly) {
                    card.href = `https://wa.me/${digitsOnly}`;
                } else {
                    card.href = rawValue || "#";
                }
            }
        }
    }
}

function hydrateFooterFeaturedColumn(host, heading, items, fallbackHref) {
    if (!host || !items.length) {
        return;
    }

    host.innerHTML = `
        <h3>${escapeHtml(heading)}</h3>
        ${items.slice(0, 6).map((item) => `
            <a href="${escapeHtml(resolvePublicHref(item, fallbackHref))}">${escapeHtml(resolveContentTitle(item))}</a>
        `).join("")}
    `;
}

function buildNavBranchMarkup(item) {
    const children = Array.isArray(item?.children) ? item.children : [];

    return `
        <article class="nav-tree-branch">
            <a class="nav-tree-branch-title" href="${escapeHtml(resolvePublicHref(item, "/products"))}">${escapeHtml(resolveContentTitle(item))}</a>
            <div class="nav-tree-leaf-list">
                ${children.map((child) => `
                    <a href="${escapeHtml(resolvePublicHref(child, "/products"))}">${escapeHtml(resolveContentTitle(child))}</a>
                `).join("")}
            </div>
        </article>
    `;
}

function hydrateNavigationMenus(menus) {
    const headerMenu = Array.isArray(menus) ? menus.find((menu) => String(menu?.menu_position || "") === "header") : null;

    if (!headerMenu || !Array.isArray(headerMenu.items)) {
        return;
    }

    const productMenuItem = headerMenu.items.find((item) => String(item?.code || item?.route_key || "").includes("product"));
    const solutionMenuItem = headerMenu.items.find((item) => String(item?.code || item?.route_key || "").includes("solution"));
    const aboutMenuItem = headerMenu.items.find((item) => String(item?.code || item?.route_key || "").includes("about"));

    if (productMenuItem) {
        const productDirectLink = document.querySelector("[data-product-nav] .nav-link-direct");

        if (productDirectLink) {
            productDirectLink.textContent = productMenuItem.name || productMenuItem.name_zh || productDirectLink.textContent;
            productDirectLink.href = resolvePublicHref(productMenuItem, "/products");
        }

        if (Array.isArray(productMenuItem.children) && productMenuItem.children.length && productViews.length) {
            productTabs.forEach((tab, index) => {
                tab.hidden = index > 0;
                tab.classList.toggle("is-active", index === 0);
                tab.setAttribute("aria-selected", String(index === 0));

                if (index === 0) {
                    const titleNode = tab.querySelector("strong");
                    const noteNode = tab.querySelector("small");

                    if (titleNode) {
                        titleNode.textContent = productMenuItem.name || productMenuItem.name_zh || titleNode.textContent;
                    }

                    if (noteNode) {
                        noteNode.textContent = getLocalizedRuntimeCopy("按分类快速浏览", "Browse by CMS categories");
                    }
                }
            });

            productViews.forEach((view, index) => {
                view.hidden = index > 0;
                view.classList.toggle("is-active", index === 0);

                if (index === 0) {
                    view.innerHTML = `<div class="nav-tree-branch-grid">${productMenuItem.children.map(buildNavBranchMarkup).join("")}</div>`;
                }
            });

            renderMobileProductAccordion();
            setProductTab(productTabs[0]?.dataset.productTab || "factory");
        }
    }

    if (solutionMenuItem) {
        const solutionDirectLink = document.querySelectorAll(".nav-item-submenu .nav-link-direct")[0];
        const solutionPanel = document.querySelectorAll("[data-nav-dropdown-panel]")[0];

        if (solutionDirectLink) {
            solutionDirectLink.textContent = solutionMenuItem.name || solutionMenuItem.name_zh || solutionDirectLink.textContent;
            solutionDirectLink.href = resolvePublicHref(solutionMenuItem, "/solutions");
        }

        if (solutionPanel && Array.isArray(solutionMenuItem.children) && solutionMenuItem.children.length) {
            solutionPanel.innerHTML = `
                <div class="nav-submenu-list">
                    ${solutionMenuItem.children.map((item) => `
                        <a href="${escapeHtml(resolvePublicHref(item, "/solutions"))}">${escapeHtml(resolveContentTitle(item))}</a>
                    `).join("")}
                </div>
            `;
        }
    }

    if (aboutMenuItem) {
        const aboutLink = document.querySelector('.site-nav > a[href$="/about.html"], .site-nav > a[href="about.html"]');

        if (aboutLink) {
            aboutLink.textContent = aboutMenuItem.name || aboutMenuItem.name_zh || aboutLink.textContent;
            aboutLink.href = resolvePublicHref(aboutMenuItem, "/about");
        }
    }
}

function rebindNavInteractions() {
    // Simple pages: nav only contains <a> links, no complex interactions to rebind.
    // Complex pages (with mega menu) use hydrateNavigationMenus which doesn't replace DOM.
    // Mobile menu and lang switcher are bound once at init and survive nav DOM replacement.
}

function renderNavigationFromApi(items) {
    const lang = currentPageLanguage();
    const isEn = lang === "en";

    function resolveNavTitle(item) {
        return String(item.display_title || item.name || item.title || item.name_zh || item.title_zh || "").trim();
    }

    function resolveNavHref(item, fallback) {
        if (item.type === "custom_url") {
            return String(item.url || item.route_path || "").trim() || fallback;
        }
        return resolvePublicHref(item, fallback);
    }

    function renderLeaf(item, isChild) {
        const title = resolveNavTitle(item);
        const href = resolveNavHref(item, isChild ? "#" : "index.html");
        return `<a href="${escapeHtml(href)}" data-zh="${escapeHtml(item.name_zh || title)}" data-en="${escapeHtml(item.name || title)}">${escapeHtml(title)}</a>`;
    }

    function renderDropdown(item) {
        const title = resolveNavTitle(item);
        const children = Array.isArray(item.children) ? item.children : [];
        const href = resolveNavHref(item, "#");
        return `
            <div class="nav-item nav-item-submenu" data-nav-dropdown>
                <div class="nav-link-split">
                    <a class="nav-link-direct" href="${escapeHtml(href)}" data-zh="${escapeHtml(item.name_zh || title)}" data-en="${escapeHtml(item.name || title)}">${escapeHtml(title)}</a>
                    <button class="nav-link-button nav-link-toggle" type="button" aria-expanded="false" aria-label="${isEn ? 'Toggle submenu' : '切换子菜单'}">
                        <span class="nav-link-arrow" aria-hidden="true">
                            <svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
                        </span>
                    </button>
                </div>
                <div class="nav-dropdown-panel" data-nav-dropdown-panel>
                    <div class="nav-submenu-list">
                        ${children.map((child) => renderLeaf(child, true)).join("")}
                    </div>
                </div>
            </div>`;
    }

    function renderMega(item) {
        const title = resolveNavTitle(item);
        const children = Array.isArray(item.children) ? item.children : [];
        const href = resolveNavHref(item, "#");
        return `
            <div class="nav-item nav-item-mega" data-product-nav>
                <div class="nav-link-split">
                    <a class="nav-link-direct" href="${escapeHtml(href)}" data-zh="${escapeHtml(item.name_zh || title)}" data-en="${escapeHtml(item.name || title)}">${escapeHtml(title)}</a>
                    <button class="nav-link-button nav-link-toggle" type="button" data-product-trigger aria-expanded="false" aria-label="${isEn ? 'Toggle product catalog' : '切换产品分类'}">
                        <span class="nav-link-arrow" aria-hidden="true">
                            <svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
                        </span>
                    </button>
                </div>
                <div class="nav-mega-panel" data-product-panel>
                    <div class="nav-tree">
                        <div class="nav-tree-views">
                            <section class="nav-tree-view is-active">
                                <div class="nav-tree-branch-grid">
                                    ${children.map(buildNavBranchMarkup).join("")}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    return items.map((item) => {
        const type = String(item.type || "plain").trim();
        if (type === "dropdown" || type === "flyout") {
            const children = Array.isArray(item.children) ? item.children : [];
            if (children.length) {
                return renderDropdown(item);
            }
        }
        if (type === "mega" || type === "auto_category_tree") {
            const children = Array.isArray(item.children) ? item.children : [];
            if (children.length) {
                return renderMega(item);
            }
        }
        // Default: plain or custom_url
        return renderLeaf(item, false);
    }).join("");
}

async function loadNavigation(requestedCode) {
    try {
        const siteNav = document.querySelector(".site-nav");
        if (siteNav?.dataset.staticNav === "1") {
            return;
        }

        const result = await getPublicApi(`/api/site/navigation?lang=${encodeURIComponent(toApiLanguageCode(requestedCode || "zh"))}`);
        const headerMenu = Array.isArray(result?.data)
            ? result.data.find((m) => String(m?.menu_position || "") === "header")
            : null;

        if (!headerMenu || !Array.isArray(headerMenu.items) || !headerMenu.items.length) {
            return;
        }

        if (!siteNav) return;

        siteNav.innerHTML = renderNavigationFromApi(headerMenu.items);
        rebindNavInteractions();
    } catch (error) {
        // API unavailable - keep original hardcoded nav as fallback
    }
}

function setHydratingState(isHydrating) {
    if (isHydrating) {
        document.documentElement.setAttribute('data-hydrating', '');
    } else {
        document.documentElement.removeAttribute('data-hydrating');
        document.documentElement.setAttribute('data-hydrated', '');
    }
}

async function hydratePublicSite(requestedCode) {
    if (isStaticGeneratedPublicPage) {
        applyLanguage(requestedCode || body?.dataset.lang || "zh", {
            persistMode: hasExplicitPublicLanguageChoice() ? "manual" : "auto",
        });
        populateSalesContactMenus();
        initProgressiveMedia();
        setHydratingState(false);
        return;
    }

    setHydratingState(true);
    const FOUC_TIMEOUT = setTimeout(() => setHydratingState(false), 3000);
    const requestVersion = ++publicBootstrapRequestVersion;

    try {
        const result = await getPublicApi(`/api/site/bootstrap?lang=${encodeURIComponent(toApiLanguageCode(requestedCode || body?.dataset.lang || "zh"))}`);

        if (requestVersion !== publicBootstrapRequestVersion) {
            return;
        }

        const payload = result?.data || {};
        const homepage = payload.homepage || {};
        const featuredProducts = homepageSectionByKey(homepage, "featured_products")?.items || [];
        const featuredSolutions = homepageSectionByKey(homepage, "featured_solutions")?.items || [];
        const featuredCases = homepageSectionByKey(homepage, "customer_cases")?.items
            || homepageSectionByKey(homepage, "featured_cases")?.items
            || homepageSectionByKey(homepage, "case_list")?.items
            || [];
        const featuredNews = homepageSectionByKey(homepage, "company_news")?.items
            || homepageSectionByKey(homepage, "news_list")?.items
            || [];
        const resolvedCode = result?.meta?.language?.resolved_code || toApiLanguageCode(requestedCode || "zh");
        const requestedNormalized = normalizedLang(requestedCode || body?.dataset.lang || "zh");
        const resolvedNormalized = normalizedLang(resolvedCode);

        hydrateSiteConfig(payload.site);

        const siteTitle = String(payload.site?.site_title || "").trim();
        const siteMetaDesc = String(payload.site?.meta_description || "").trim();
        if (siteTitle) {
            document.title = siteTitle;
        }
        if (siteMetaDesc) {
            const metaDesc = document.querySelector("#meta-description");
            if (metaDesc) {
                metaDesc.setAttribute("content", siteMetaDesc);
            }
            if (typeof setMetaContent === "function") {
                setMetaContent("og:description", siteMetaDesc);
                setMetaContent("og:site_name", String(payload.site?.site_name || "HANZUN").trim());
                setMetaContent("og:type", "website");
                if (siteTitle) {
                    setMetaContent("og:title", siteTitle);
                }
            }
        }
        const preferredCode = normalizedLang(resolvePreferredRuntimeLanguage(payload.site, requestedCode || resolvedCode));
        if (preferredCode !== requestedNormalized && preferredCode !== resolvedNormalized) {
            hydratePublicSite(preferredCode);
            return;
        }

        applyLanguage(resolvedCode, {
            persistMode: hasExplicitPublicLanguageChoice() ? "manual" : "auto",
        });
        loadNavigation(resolvedCode);
        hydrateHeroSection(homepage);
        hydrateSolutionsGrid(featuredSolutions);
        hydrateProductsShowcase(featuredProducts);
        hydrateCasesBoard(featuredCases);
        hydrateNewsGrid(featuredNews);
        hydrateContactPanels(payload.contact);
        hydrateFooterFeaturedColumn(
            footerFeaturedProducts,
            getLocalizedRuntimeCopy("热门产品", "Popular Products"),
            featuredProducts,
            "/products"
        );
        hydrateFooterFeaturedColumn(
            footerFeaturedSolutions,
            getLocalizedRuntimeCopy("热门生产线", "Popular Production Lines"),
            featuredSolutions,
            "/solutions"
        );
        initProgressiveMedia();
        populateSalesContactMenus();

        // T1: Hero image
        const heroImg = document.querySelector('.hero-image');
        if (heroImg && payload.site?.hero_image_url) {
            heroImg.src = payload.site.hero_image_url;
            heroImg.alt = payload.site?.hero_image_alt || heroImg.alt;
        }

        // T1: Notice banner
        const noticeSection = document.querySelector('#notice-banner');
        if (noticeSection) {
            const nImg = noticeSection.querySelector('img');
            const nTitle = noticeSection.querySelector('.notice-banner-copy span');
            const nContent = noticeSection.querySelector('.notice-banner-copy strong');
            if (nImg && payload.site?.notice_image_url) nImg.src = payload.site.notice_image_url;
            if (nTitle && payload.site?.notice_title) nTitle.textContent = payload.site.notice_title;
            if (nContent && payload.site?.notice_content) nContent.textContent = payload.site.notice_content;
        }

        // T2: Factory video
        const video = document.querySelector('[data-progressive-video]');
        if (video && payload.site?.enterprise_video_url) {
            video.setAttribute('data-src', payload.site.enterprise_video_url);
            if (video.classList.contains('is-loaded')) {
                video.src = payload.site.enterprise_video_url;
            }
        }

        // T3: Homepage certificates
        if (typeof hydrateCertificatesGrid === "function") {
            hydrateCertificatesGrid(payload.certificates || []);
        }

        // T4: Homepage team
        if (typeof hydrateTeamStrip === "function") {
            hydrateTeamStrip(payload.team_members || []);
        }

        // T6: hreflang tags
        if (Array.isArray(payload.languages) && payload.languages.length) {
            document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(function(el) { el.remove(); });
            var currentPath = window.location.pathname.replace(/^\/[a-z]{2}(?=\/)/i, '');
            var head = document.head;
            payload.languages.forEach(function(lang) {
                if (!lang.is_enabled) return;
                var code = String(lang.code || '').slice(0, 2).toLowerCase();
                var link = document.createElement('link');
                link.rel = 'alternate';
                link.hreflang = code;
                link.href = window.location.origin + '/' + code + (currentPath || '/');
                head.appendChild(link);
            });
            // x-default -> Chinese version
            var defLink = document.createElement('link');
            defLink.rel = 'alternate';
            defLink.hreflang = 'x-default';
            defLink.href = window.location.origin + '/zh' + (currentPath || '/');
            head.appendChild(defLink);
        }
    } catch (error) {
        return;
    } finally {
        clearTimeout(FOUC_TIMEOUT);
        setHydratingState(false);
    }
}

function hydrateCertificatesGrid(certificates) {
    var grid = document.querySelector('.metrics-cert-grid');
    if (!grid || !certificates || !certificates.length) return;

    grid.innerHTML = certificates.slice(0, 5).map(function(cert) {
        var name = cert.name || cert.name_zh || '';
        var img = cert.image_asset_url || cert.image_asset?.public_url || assetPath('assets/images/certificates/cert-1.png');
        return '<article class="metrics-cert-card">' +
            '<figure class="metrics-cert-media">' +
            '<img src="' + escapeHtml(img) + '" alt="' + escapeHtml(name) + '" loading="lazy" decoding="async" data-progressive-media>' +
            '</figure><span>' + escapeHtml(name) + '</span></article>';
    }).join('');

    if (typeof initProgressiveMedia === 'function') initProgressiveMedia();
}

function hydrateTeamStrip(members) {
    var track = document.querySelector('[data-loop-track]');
    if (!track || !members || !members.length) return;

    track.innerHTML = members.map(function(m) {
        var name = m.name || m.name_zh || '';
        var avatar = m.avatar_asset_url || m.avatar_asset?.public_url || assetPath('assets/images/team/default.png');
        var email = m.email || '';
        var phone = m.phone || '';
        var whatsapp = m.whatsapp || '';
        return '<article class="sales-card">' +
            '<figure class="sales-avatar"><img src="' + escapeHtml(avatar) + '" alt="' + escapeHtml(name) + '" loading="lazy" decoding="async" data-progressive-media>' +
            '<figcaption class="sales-name-bar"><strong>' + escapeHtml(name) + '</strong></figcaption></figure>' +
            '<div class="sales-copy">' +
            (email ? '<a class="sales-contact-link sales-contact-email" href="mailto:' + escapeHtml(email) + '">' + escapeHtml(getLocalizedRuntimeCopy('邮箱', 'Email')) + '</a>' : '') +
            (phone ? '<a class="sales-contact-link sales-contact-phone" href="tel:' + escapeHtml(phone) + '">' + escapeHtml(getLocalizedRuntimeCopy('电话', 'Phone')) + '</a>' : '') +
            (whatsapp ? '<a class="sales-contact-link sales-contact-whatsapp" href="https://wa.me/' + escapeHtml(whatsapp.replace(/\D/g, '')) + '">WhatsApp</a>' : '') +
            '</div></article>';
    }).join('');

    if (typeof initProgressiveMedia === 'function') initProgressiveMedia();
}

function renderMobileProductAccordion() {
    if (!mobileProductAccordion || !productTabs.length || !productViews.length) {
        return;
    }

    const sections = Array.from(productTabs).map((tab, index) => {
        const tabName = tab.dataset.productTab;
        const view = Array.from(productViews).find((item) => item.dataset.productView === tabName);

        if (!view) {
            return "";
        }

        const label = tab.querySelector("strong")?.textContent?.trim() || tab.textContent.trim();
        const note = tab.querySelector("small")?.textContent?.trim() || "";
        const branches = Array.from(view.querySelectorAll(".nav-tree-branch")).map((branch, branchIndex) => {
            const trigger = branch.querySelector(".nav-tree-branch-title");
            const links = Array.from(branch.querySelectorAll(".nav-tree-leaf-list a")).map((link) => `
                <a href="${link.getAttribute("href") || "#"}">${link.textContent.trim()}</a>
            `).join("");

            return `
                <article class="mobile-product-branch">
                    <button class="mobile-product-branch-trigger" type="button" aria-expanded="false">
                        <span>${trigger?.textContent?.trim() || ""}</span>
                    </button>
                    <div class="mobile-product-links" hidden>
                        ${links}
                    </div>
                </article>
            `;
        }).join("");

        return `
            <section class="mobile-product-section">
                <button class="mobile-product-section-trigger" type="button" aria-expanded="false">
                    <span class="mobile-product-section-copy">
                        <strong>${label}</strong>
                        <small>${note}</small>
                    </span>
                </button>
                <div class="mobile-product-section-body" hidden>
                    ${branches}
                </div>
            </section>
        `;
    }).join("");

    mobileProductAccordion.innerHTML = sections;
    mobileProductAccordion.hidden = !isMobileProductAccordion();
}

function initCertificateStage(stage) {
    const cards = Array.from(stage.querySelectorAll(".certificate-card"));

    if (cards.length < 2) {
        return;
    }

    let index = 0;
    let paused = false;

    function paint() {
        cards.forEach((card, cardIndex) => {
            card.classList.remove("is-active", "is-prev", "is-next", "is-hidden");

            if (cardIndex === index) {
                card.classList.add("is-active");
            } else if (cardIndex === (index - 1 + cards.length) % cards.length) {
                card.classList.add("is-prev");
            } else if (cardIndex === (index + 1) % cards.length) {
                card.classList.add("is-next");
            } else {
                card.classList.add("is-hidden");
            }
        });
    }

    function tick() {
        if (!paused) {
            index = (index + 1) % cards.length;
            paint();
        }
    }

    stage.addEventListener("mouseenter", () => {
        paused = true;
    });

    stage.addEventListener("mouseleave", () => {
        paused = false;
    });

    stage.addEventListener("focusin", () => {
        paused = true;
    });

    stage.addEventListener("focusout", () => {
        paused = false;
    });

    paint();
    window.setInterval(tick, 2800);
}

function closeDropdown() {
    if (!dropdown || !dropdownTrigger) {
        return;
    }

    dropdown.classList.remove("open");
    dropdownTrigger.setAttribute("aria-expanded", "false");
}

function closeProductMenu() {
    if (!productNav || !productTrigger) {
        return;
    }

    productNav.classList.remove("open");
    productTrigger.setAttribute("aria-expanded", "false");
}

function clearProductHoverCloseTimer() {
    if (productHoverCloseTimer) {
        window.clearTimeout(productHoverCloseTimer);
        productHoverCloseTimer = null;
    }
}

function scheduleProductMenuClose() {
    clearProductHoverCloseTimer();
    productHoverCloseTimer = window.setTimeout(() => {
        closeProductMenu();
        productHoverCloseTimer = null;
    }, 320);
}

function openProductMenu() {
    if (!productNav || !productTrigger) {
        return;
    }

    productNav.classList.add("open");
    productTrigger.setAttribute("aria-expanded", "true");
    syncProductBranches(getActiveProductTabName());
}

function setNavDropdownState(item, open) {
    if (!item) {
        return;
    }

    const trigger = item.querySelector("[data-nav-dropdown-trigger]");

    item.classList.toggle("open", open);

    if (trigger) {
        trigger.setAttribute("aria-expanded", String(open));
    }
}

function clearNavDropdownCloseTimer(item) {
    const timer = navHoverCloseTimers.get(item);

    if (timer) {
        window.clearTimeout(timer);
        navHoverCloseTimers.delete(item);
    }
}

function scheduleNavDropdownClose(item) {
    clearNavDropdownCloseTimer(item);
    const timer = window.setTimeout(() => {
        setNavDropdownState(item, false);
        navHoverCloseTimers.delete(item);
    }, 180);
    navHoverCloseTimers.set(item, timer);
}

function closeNavDropdowns(exceptItem = null) {
    if (!navDropdownItems.length) {
        return;
    }

    navDropdownItems.forEach((item) => {
        if (item === exceptItem) {
            return;
        }

        setNavDropdownState(item, false);
    });
}

function setMenuState(open) {
    if (!menu || !menuToggle) {
        return;
    }

    menu.classList.toggle("open", open);
    menuToggle.setAttribute("aria-expanded", String(open));
    body.classList.toggle("menu-open", open);

    if (!open) {
        closeProductMenu();
        closeNavDropdowns();
        closeDropdown();
    }
}

function isMobileProductAccordion() {
    return Boolean(mobileFabMedia && mobileFabMedia.matches);
}

function setProductBranch(branch, open) {
    const trigger = branch?.querySelector(".nav-tree-branch-title");
    const leafList = branch?.querySelector(".nav-tree-leaf-list");

    if (!branch || !trigger || !leafList) {
        return;
    }

    branch.classList.toggle("open", open);
    trigger.setAttribute("aria-expanded", String(open));
    leafList.hidden = !open;
    leafList.style.display = open ? "grid" : "none";
}

function resetProductBranch(branch) {
    const trigger = branch?.querySelector(".nav-tree-branch-title");
    const leafList = branch?.querySelector(".nav-tree-leaf-list");

    if (!branch || !trigger || !leafList) {
        return;
    }

    branch.classList.remove("open");
    trigger.setAttribute("aria-expanded", "false");
    leafList.hidden = false;
    leafList.style.display = "";
}

function collapseProductBranches(scope) {
    if (!scope) {
        return;
    }

    scope.querySelectorAll(".nav-tree-branch").forEach((branch) => {
        setProductBranch(branch, false);
    });
}

function syncProductBranches(tabName) {
    if (!isMobileProductAccordion()) {
        productViews.forEach((view) => {
            view.querySelectorAll(".nav-tree-branch").forEach((branch) => {
                resetProductBranch(branch);
            });
        });
        return;
    }

    productViews.forEach((view) => {
        const isActive = view.dataset.productView === tabName;
        collapseProductBranches(view);
        view.querySelectorAll(".nav-tree-branch-title").forEach((trigger) => {
            trigger.setAttribute("aria-expanded", "false");
        });

        if (!isActive) {
            return;
        }
    });
}

function getActiveProductTabName() {
    return Array.from(productTabs).find((tab) => tab.classList.contains("is-active"))?.dataset.productTab || "factory";
}

function setProductTab(tabName) {
    if (!productTabs.length || !productViews.length) {
        return;
    }

    productTabs.forEach((tab) => {
        const isActive = tab.dataset.productTab === tabName;
        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-selected", String(isActive));
    });

    productViews.forEach((view) => {
        view.classList.toggle("is-active", view.dataset.productView === tabName);
    });

    syncProductBranches(tabName);
}

function closeContactFab() {
    if (!contactFab || !contactTrigger) {
        return;
    }

    closeContactChoosers();
    contactFab.classList.remove("open");
    contactFab.dataset.open = "false";
    const floatingMenu = contactFab.querySelector("[data-contact-menu]");
    if (floatingMenu) {
        floatingMenu.style.setProperty("opacity", "0", "important");
        floatingMenu.style.setProperty("visibility", "hidden", "important");
        floatingMenu.style.setProperty("pointer-events", "none", "important");
        floatingMenu.style.setProperty("transform", "translateY(8px)", "important");
    }
    contactTrigger.setAttribute("aria-expanded", "false");
}

function openContactFab() {
    if (!contactFab || !contactTrigger) {
        return;
    }

    contactFab.classList.add("open");
    contactFab.dataset.open = "true";
    const floatingMenu = contactFab.querySelector("[data-contact-menu]");
    if (floatingMenu) {
        floatingMenu.style.setProperty("opacity", "1", "important");
        floatingMenu.style.setProperty("visibility", "visible", "important");
        floatingMenu.style.setProperty("pointer-events", "auto", "important");
        floatingMenu.style.setProperty("transform", "translateY(0)", "important");
    }
    contactTrigger.setAttribute("aria-expanded", "true");
    contactFab.classList.remove("attention");
    syncContactFabVisibility();
}

function setContactChooserState(chooser, open) {
    if (!chooser) {
        return;
    }

    const trigger = chooser.querySelector("[data-contact-chooser-trigger]");
    const menu = chooser.querySelector("[data-contact-chooser-menu]");

    chooser.classList.toggle("open", open);

    if (trigger) {
        trigger.setAttribute("aria-expanded", String(open));
    }

    if (menu) {
        menu.hidden = !open;
    }
}

function isFabContactChooser(chooser) {
    return Boolean(chooser?.closest("[data-contact-fab]"));
}

function closeContactChoosers(exceptChooser = null) {
    if (!contactChoosers.length) {
        return;
    }

    contactChoosers.forEach((chooser) => {
        if (chooser === exceptChooser) {
            return;
        }

        setContactChooserState(chooser, false);
    });
}

function syncBackToTopVisibility() {
    if (!backToTopButton) {
        return;
    }

    const shouldShow = window.scrollY > 420;
    backToTopButton.classList.toggle("visible", shouldShow);
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: "smooth",
    });
}

function syncContactFabVisibility() {
    if (!contactFab) {
        return;
    }

    if (!mobileFabMedia || !mobileFabMedia.matches) {
        contactFab.classList.add("is-active");
        return;
    }

    const isDetailPage = Boolean(document.querySelector("[data-public-detail-root]"));
    const shouldShow = isDetailPage || window.scrollY > 560 || contactFab.classList.contains("open");
    contactFab.classList.toggle("is-active", shouldShow);
}

function getLocalizedSupportText(zhText, enText) {
    return getContentLanguage(normalizedLang(body.dataset.lang || "zh")) === "en" ? enText : zhText;
}

function syncSupportDefaultMessagesMarkup() {
    if (!supportMessages) {
        supportDefaultMessagesMarkup = "";
        return;
    }

    supportDefaultMessagesMarkup = supportMessages.innerHTML;
}

syncRuntimeLanguages();

function openSupportPanel(prefill = "") {
    if (!supportPanel) {
        return;
    }

    closeWechatPanel();
    supportPanel.hidden = false;
    body.classList.add("support-open");
    void hydrateSupportConversation();

    if (supportInput) {
        if (prefill) {
            supportInput.value = prefill;
        }

        window.setTimeout(() => {
            supportInput.focus();
            supportInput.setSelectionRange?.(supportInput.value.length, supportInput.value.length);
        }, 40);
    }
}

function closeSupportPanel() {
    if (!supportPanel) {
        return;
    }

    supportPanel.hidden = true;
    body.classList.remove("support-open");
}

function scrollSupportConversationToBottom() {
    if (!supportMessages) {
        return;
    }

    supportMessages.scrollTop = supportMessages.scrollHeight;
}

function restoreSupportComposerFocus() {
    if (!supportInput) {
        return;
    }

    window.setTimeout(() => {
        supportInput.focus();
        supportInput.setSelectionRange?.(supportInput.value.length, supportInput.value.length);
    }, 40);
}

function setSupportComposerBusy(isBusy) {
    supportState.sending = isBusy;

    if (supportInput) {
        supportInput.disabled = isBusy;
    }
    if (supportSubmitButton) {
        supportSubmitButton.disabled = isBusy;
    }
    if (supportForm) {
        supportForm.setAttribute("aria-busy", isBusy ? "true" : "false");
    }
    if (supportSubmitLabel) {
        supportSubmitLabel.textContent = isBusy
            ? getLocalizedSupportText("发送中...", "Sending...")
            : getLocalizedSupportText("发送", "Send");
    }
}

function setSupportComposerStatus(message = "") {
    if (!supportStatus) {
        return;
    }

    const content = String(message || "").trim();
    supportStatus.textContent = content;
    supportStatus.hidden = content === "";
}

function resetSupportConversation() {
    if (!supportMessages) {
        return;
    }

    supportMessages.innerHTML = supportDefaultMessagesMarkup;
    supportState.noticeNodes.clear();
    scrollSupportConversationToBottom();
}

function normalizeSupportSources(sources) {
    if (!Array.isArray(sources)) {
        return [];
    }

    return sources
        .map((item) => ({
            title: String(item && item.title ? item.title : "").trim(),
            sourceType: String(item && item.source_type ? item.source_type : "").trim(),
        }))
        .filter((item) => item.title || item.sourceType)
        .slice(0, 3);
}

function localizeSupportSourceType(value) {
    const normalized = String(value || "").trim().toLowerCase();
    if (!normalized) {
        return "";
    }

    if (normalized === "product") {
        return getLocalizedSupportText("产品资料", "Product");
    }
    if (normalized === "solution") {
        return getLocalizedSupportText("方案资料", "Solution");
    }
    if (normalized === "article") {
        return getLocalizedSupportText("相关文章", "Article");
    }

    return "";
}

function formatSupportMessageTime(value) {
    const raw = String(value || "").trim();
    if (!raw) {
        return "";
    }

    const matched = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (!matched) {
        return "";
    }

    const parsed = new Date(
        Number(matched[1]),
        Number(matched[2]) - 1,
        Number(matched[3]),
        Number(matched[4]),
        Number(matched[5]),
        Number(matched[6] || 0)
    );

    if (Number.isNaN(parsed.getTime())) {
        return "";
    }

    const now = new Date();
    const sameYear = parsed.getFullYear() === now.getFullYear();
    const locale = getContentLanguage(normalizedLang(body?.dataset.lang || "zh")) === "en" ? "en-GB" : "zh-CN";
    const options = sameYear
        ? { month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" }
        : { year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" };

    try {
        return new Intl.DateTimeFormat(locale, options).format(parsed).replace(",", "");
    } catch (error) {
        const pad = (number) => String(number).padStart(2, "0");
        const yearPrefix = sameYear ? "" : `${parsed.getFullYear()}-`;
        return `${yearPrefix}${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())} ${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
    }
}

function createSupportMessageNode(message) {
    if (!message || !supportMessages) {
        return null;
    }

    const type = String(message.type || "").trim();
    const article = document.createElement("article");
    article.className = type === "system-status"
        ? "support-message support-message-system-status"
        : `support-message support-message-${type}`;

    if (type === "system-status") {
        const bubble = document.createElement("div");
        bubble.className = "support-status-bubble";

        const copy = document.createElement("p");
        copy.textContent = String(message.content || "").trim();
        bubble.appendChild(copy);

        if (typeof message.action === "function") {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "support-retry-button";
            button.textContent = String(message.actionLabel || getLocalizedSupportText("重试", "Retry")).trim();
            button.addEventListener("click", message.action);
            bubble.appendChild(button);
        }

        article.appendChild(bubble);
        return article;
    }

    const bubble = document.createElement("div");
    bubble.className = "support-bubble";

    const header = document.createElement("div");
    header.className = "support-message-meta";

    const title = document.createElement("strong");
    title.textContent = String(message.title || "").trim();
    header.appendChild(title);

    const formattedTime = formatSupportMessageTime(message.createdAt);
    if (formattedTime) {
        const time = document.createElement("time");
        time.className = "support-message-time";
        time.dateTime = String(message.createdAt || "").trim();
        time.textContent = formattedTime;
        header.appendChild(time);
    }

    bubble.appendChild(header);

    const content = document.createElement("p");
    content.textContent = String(message.content || "").trim();
    bubble.appendChild(content);

    if (type === "assistant") {
        const sources = normalizeSupportSources(message.sources);
        if (sources.length) {
            const list = document.createElement("ul");
            list.className = "support-source-list";
            sources.forEach((item) => {
                const sourceItem = document.createElement("li");
                sourceItem.className = "support-source-item";

                const sourceTitle = document.createElement("span");
                sourceTitle.className = "support-source-title";
                sourceTitle.textContent = item.title || getLocalizedSupportText("参考资料", "Reference");
                sourceItem.appendChild(sourceTitle);

                const sourceTypeLabel = localizeSupportSourceType(item.sourceType);
                if (sourceTypeLabel) {
                    const sourceType = document.createElement("span");
                    sourceType.className = "support-source-type";
                    sourceType.textContent = sourceTypeLabel;
                    sourceItem.appendChild(sourceType);
                }

                list.appendChild(sourceItem);
            });
            bubble.appendChild(list);
        }
    }

    article.appendChild(bubble);
    return article;
}

function appendSupportMessage(message) {
    if (!supportMessages) {
        return null;
    }

    const node = createSupportMessageNode(message);
    if (!node) {
        return null;
    }

    supportMessages.appendChild(node);
    scrollSupportConversationToBottom();
    return node;
}

function appendSupportConversationMessage(role, content, options = {}) {
    const normalizedRole = role === "assistant" ? "assistant" : "user";
    const title = normalizedRole === "assistant"
        ? getLocalizedSupportText("客服助手", "Support Assistant")
        : getLocalizedSupportText("访客", "Visitor");

    return appendSupportMessage({
        type: normalizedRole,
        title,
        content,
        createdAt: options.createdAt || new Date().toISOString().slice(0, 19).replace("T", " "),
        sources: normalizedRole === "assistant" ? options.sources : [],
    });
}

function clearSupportNotice(key) {
    const existing = supportState.noticeNodes.get(key);
    if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
    }
    supportState.noticeNodes.delete(key);
}

function upsertSupportStatusMessage(key, content, options = {}) {
    clearSupportNotice(key);
    const node = appendSupportMessage({
        type: "system-status",
        content,
        action: options.action,
        actionLabel: options.actionLabel,
    });
    if (node) {
        supportState.noticeNodes.set(key, node);
    }
    return node;
}

function showSupportInquiryNotice(inquiryId) {
    const normalizedInquiryId = Number(inquiryId || 0);
    if (normalizedInquiryId <= 0) {
        return;
    }

    supportState.lastInquiryId = normalizedInquiryId;
    upsertSupportStatusMessage(
        `inquiry-${normalizedInquiryId}`,
        getLocalizedSupportText(
            "已生成询盘，销售团队将根据您留下的信息继续跟进。",
            "An inquiry has been created. Our sales team will follow up based on the details you shared."
        )
    );
}

function renderSupportHistory(response) {
    if (!supportMessages) {
        return;
    }

    const historyMessages = Array.isArray(response && response.messages) ? response.messages : [];
    resetSupportConversation();

    if (!historyMessages.length) {
        if (Number(response && response.inquiry_id || 0) > 0) {
            showSupportInquiryNotice(response.inquiry_id);
        }
        scrollSupportConversationToBottom();
        return;
    }

    supportMessages.innerHTML = "";
    supportState.noticeNodes.clear();

    historyMessages.forEach((item) => {
        const role = String(item && item.role ? item.role : "").trim();
        const content = String(item && item.content ? item.content : "").trim();
        if (!content || (role !== "user" && role !== "assistant")) {
            return;
        }

        appendSupportConversationMessage(role, content, {
            createdAt: item.created_at,
            sources: role === "assistant" ? item.sources : [],
        });
    });

    if (Number(response && response.inquiry_id || 0) > 0) {
        showSupportInquiryNotice(response.inquiry_id);
    }
}

async function hydrateSupportConversation(force = false) {
    if (!supportMessages) {
        return null;
    }

    const sessionCode = currentSupportSessionCode();
    if (!sessionCode) {
        supportState.hydratedSessionCode = "";
        supportState.lastFailedMessage = "";
        if (force) {
            resetSupportConversation();
        }
        return null;
    }

    if (!force && supportState.hydratedSessionCode === sessionCode) {
        return null;
    }

    if (supportState.hydratePromise) {
        return supportState.hydratePromise;
    }

    supportState.hydratePromise = (async () => {
        setSupportComposerStatus(getLocalizedSupportText("正在恢复对话...", "Restoring conversation..."));

        try {
            const response = await postPublicApi("/api/ai/session", {
                client_id: ensureSupportClientId(),
                session_code: sessionCode,
            });
            renderSupportHistory(response);
            supportState.hydratedSessionCode = String(response.session_code || sessionCode).trim();
            setSupportComposerStatus(getLocalizedSupportText("已恢复上次对话。", "Conversation restored."));
            window.setTimeout(() => {
                if (!supportState.sending) {
                    setSupportComposerStatus("");
                }
            }, 1400);

            return response;
        } catch (error) {
            if (Number(error && error.code || 0) === 40401) {
                safeSessionStorageRemove(supportSessionStorageKey);
                resetSupportConversation();
                supportState.hydratedSessionCode = "";
                setSupportComposerStatus(getLocalizedSupportText("未找到上次对话，已为您开启新会话。", "The previous conversation was unavailable, so a new session is ready."));
                return null;
            }

            setSupportComposerStatus(getLocalizedSupportText("暂时无法恢复历史对话，您仍可继续发送消息。", "History is temporarily unavailable, but you can keep chatting."));
            return null;
        } finally {
            supportState.hydratePromise = null;
        }
    })();

    return supportState.hydratePromise;
}

async function retryLastSupportMessage() {
    if (!supportState.lastFailedMessage || supportState.sending) {
        return;
    }

    clearSupportNotice("retry");
    await submitSupportMessageLive(supportState.lastFailedMessage, { skipUserAppend: true });
}

async function submitSupportMessageLive(message, options = {}) {
    const trimmed = String(message || "").trim();

    if (!trimmed) {
        setSupportComposerStatus(getLocalizedSupportText("请输入问题后再发送。", "Enter a question before sending."));
        restoreSupportComposerFocus();
        return;
    }

    if (supportState.sending) {
        return;
    }

    if (currentSupportSessionCode() && supportState.hydratedSessionCode !== currentSupportSessionCode()) {
        await hydrateSupportConversation();
    }

    clearSupportNotice("retry");

    if (!options.skipUserAppend) {
        appendSupportConversationMessage("user", trimmed);
    }

    setSupportComposerBusy(true);
    setSupportComposerStatus(getLocalizedSupportText("客服助手正在整理回复...", "Support assistant is preparing a reply..."));

    try {
        const response = await postPublicApi("/api/ai/chat", {
            client_id: ensureSupportClientId(),
            session_code: currentSupportSessionCode(),
            message: trimmed,
            path: currentSupportPath(),
            title: document.title,
            referrer: document.referrer,
            language: currentSupportLanguage(),
            utm_source: currentUtmSource(),
        });

        supportState.hydratedSessionCode = String(response.session_code || currentSupportSessionCode()).trim();
        supportState.lastFailedMessage = "";

        appendSupportConversationMessage(
            "assistant",
            response.assistant_reply || getLocalizedSupportText(
                "已收到您的消息，我们会继续整理需求并尽快回复您。",
                "Your message has been received. We will continue organizing the requirement and reply shortly."
            ),
            { sources: response.sources }
        );

        if (Number(response.inquiry_id || 0) > 0) {
            showSupportInquiryNotice(response.inquiry_id);
        }

        setSupportComposerStatus(getLocalizedSupportText("消息已发送。", "Message sent."));
        window.setTimeout(() => {
            if (!supportState.sending) {
                setSupportComposerStatus("");
            }
        }, 1200);
    } catch (error) {
        supportState.lastFailedMessage = trimmed;
        setSupportComposerStatus(getLocalizedSupportText("发送失败，您可以重试或继续补充需求。", "Message failed to send. Retry or continue sharing details."));
        upsertSupportStatusMessage(
            "retry",
            getLocalizedSupportText(
                "当前消息未成功送达，点击重试后会继续使用同一会话发送。",
                "The last message did not go through. Retry will send it again in the same session."
            ),
            {
                action: retryLastSupportMessage,
                actionLabel: getLocalizedSupportText("重试", "Retry"),
            }
        );
    } finally {
        if (supportInput) {
            supportInput.value = "";
        }

        setSupportComposerBusy(false);
        restoreSupportComposerFocus();
    }
}

function ensureLeadFormStatusNode(form) {
    let statusNode = form.querySelector("[data-lead-form-status]");
    if (statusNode) {
        return statusNode;
    }

    statusNode = document.createElement("p");
    statusNode.className = "lead-form-status";
    statusNode.setAttribute("data-lead-form-status", "");
    const actionsNode = form.querySelector(".lead-form-actions");
    if (actionsNode) {
        actionsNode.appendChild(statusNode);
    } else {
        form.appendChild(statusNode);
    }

    return statusNode;
}

function setLeadFormStatus(form, message, type = "default") {
    const statusNode = ensureLeadFormStatusNode(form);
    statusNode.textContent = message || "";
    statusNode.dataset.state = type;
}

function collectLeadFormPayload(form) {
    const formData = new FormData(form);

    return {
        name: String(formData.get("name") || "").trim(),
        phone: String(formData.get("phone") || "").trim(),
        email: String(formData.get("email") || "").trim(),
        message: String(formData.get("message") || "").trim(),
    };
}

function buildLeadFormMessage(payload) {
    const lines = [];

    if (payload.name) {
        lines.push(`Name: ${payload.name}`);
    }
    if (payload.phone) {
        lines.push(`Phone: ${payload.phone}`);
    }
    if (payload.email) {
        lines.push(`Email: ${payload.email}`);
    }
    if (payload.message) {
        lines.push(`Requirement: ${payload.message}`);
    }

    lines.push(`Source Page: ${document.title} (${currentSupportPath()})`);

    return lines.join("\n");
}

function buildLeadFormSummary(payload) {
    const summary = [payload.name, payload.phone, payload.email, payload.message].filter(Boolean);
    if (summary.length > 0) {
        return getLocalizedSupportText("已提交联系表单：", "Lead form submitted: ") + summary.join(" / ");
    }

    return getLocalizedSupportText("已提交联系表单", "Lead form submitted");
}

function setLeadFormSubmitting(form, submitting) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) {
        return;
    }

    submitButton.disabled = submitting;
    submitButton.dataset.submitting = submitting ? "1" : "0";
    submitButton.textContent = submitting
        ? getLocalizedSupportText("提交中...", "Submitting...")
        : getLocalizedSupportText("提交联系信息", "Submit Inquiry");
}

async function submitLeadFormLive(form) {
    const payload = collectLeadFormPayload(form);

    const errors = [];
    if (!payload.name) {
        errors.push(getLocalizedSupportText("请填写姓名", "Please enter your name"));
    }
    if (!payload.email) {
        errors.push(getLocalizedSupportText("请填写邮箱", "Please enter your email"));
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
        errors.push(getLocalizedSupportText("邮箱格式不正确", "Invalid email format"));
    }

    if (errors.length > 0) {
        setLeadFormStatus(form, errors.join("; "), "error");
        return;
    }

    setLeadFormSubmitting(form, true);
    setLeadFormStatus(
        form,
        getLocalizedSupportText("正在提交...", "Submitting..."),
        "pending"
    );

    try {
        await postPublicApi("/api/site/lead", {
            name: payload.name,
            phone: payload.phone,
            email: payload.email,
            message: payload.message,
        });

        form.reset();
        setLeadFormStatus(
            form,
            getLocalizedSupportText("提交成功，我们会尽快与您联系。", "Submitted successfully. We will get back to you soon."),
            "success"
        );
    } catch (error) {
        setLeadFormStatus(
            form,
            getLocalizedSupportText("提交失败，请稍后重试。", "Submission failed. Please try again later."),
            "error"
        );
    } finally {
        setLeadFormSubmitting(form, false);
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            window.setTimeout(function () {
                submitButton.disabled = false;
            }, 3000);
        }
    }
}

function openWechatPanel() {
    if (!wechatPanel) {
        return;
    }

    wechatPanel.hidden = false;
    body.classList.add("wechat-open");
}

function closeWechatPanel() {
    if (!wechatPanel) {
        return;
    }

    wechatPanel.hidden = true;
    body.classList.remove("wechat-open");
}

async function copyTextValue(value) {
    if (!value) {
        return false;
    }

    try {
        await navigator.clipboard.writeText(value);
        return true;
    } catch (_) {
        const input = document.createElement("input");
        input.value = value;
        document.body.appendChild(input);
        input.select();
        input.setSelectionRange(0, input.value.length);

        try {
            document.execCommand("copy");
            document.body.removeChild(input);
            return true;
        } catch (error) {
            document.body.removeChild(input);
            return false;
        }
    }
}

function applyLanguage(code, options = {}) {
    const mapped = normalizedLang(code);
    const contentLang = getContentLanguage(mapped);
    const langInfo = getLanguageMap().get(mapped) || getLanguageMap().get("zh");
    const siteTitle = String(publicSiteConfig?.site_title || "").trim();
    const siteDescription = String(publicSiteConfig?.meta_description || "").trim();
    const persistMode = String(options.persistMode || "auto").trim() || "auto";
    const pageMeta = {
        zh: {
            title: "涵尊机械 | 烘焙设备定制与整线交付",
            description: "涵尊机械专注烘焙设备定制、单机设备、功能模组与整线交付，服务蛋糕、面包、夹心、切割及食品加工项目。",
        },
        en: {
            title: "Shanghai Hanzun Industrial Co., Ltd. | Bakery Equipment R&D and Line Delivery",
            description: "Shanghai Hanzun Industrial Co., Ltd. focuses on bakery equipment R&D, custom machinery, functional modules, and integrated line delivery for cake, bread, filling, cutting, and food processing projects.",
        },
    };

    body.dataset.lang = mapped;
    html.lang = langInfo.htmlLang;

    document.querySelectorAll("[data-zh]").forEach((node) => {
        node.textContent = node.dataset[contentLang] || node.dataset.zh;
    });

    document.querySelectorAll("[data-zh-placeholder]").forEach((node) => {
        const placeholder = node.dataset[`${contentLang}Placeholder`] || node.dataset.zhPlaceholder || "";
        node.setAttribute("placeholder", placeholder);
    });

    document.querySelectorAll("[data-zh-prompt]").forEach((node) => {
        node.dataset.supportPrompt = node.dataset[`${contentLang}Prompt`] || node.dataset.zhPrompt || "";
    });

    if (dropdownLabel) {
        dropdownLabel.textContent = getLanguageLabel(mapped);
    }

    if (dropdownFlag) {
        dropdownFlag.innerHTML = getFlagBadgeSvg(mapped);
        dropdownFlag.setAttribute("title", langInfo.country);
    }

    langMenu?.querySelectorAll("[data-lang-option]").forEach((button) => {
        button.classList.toggle("active", button.dataset.langOption === mapped);
    });

    if (!isStaticGeneratedPublicPage || siteTitle) {
        document.title = siteTitle || pageMeta[contentLang].title;
    }

    if (metaDescription && (!isStaticGeneratedPublicPage || siteDescription)) {
        metaDescription.setAttribute("content", siteDescription || pageMeta[contentLang].description);
    }

    if (persistMode === "manual" || persistMode === "auto") {
        safeStorageSet("hanzun-lang", mapped);
        safeStorageSet("hanzun-lang-source", persistMode);
    }

    if (languageMenuRendered) {
        renderLanguageMenu();
    }
    renderMobileProductAccordion();
    syncSupportDefaultMessagesMarkup();
}

function animateCounter(node) {
    if (node.dataset.animated === "true") {
        return;
    }

    node.dataset.animated = "true";
    const target = Number(node.dataset.count || 0);
    const suffix = node.dataset.suffix || "";
    const duration = 1500;
    const start = performance.now();

    function frame(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.round(target * eased);
        node.textContent = `${value}${suffix}`;

        if (progress < 1) {
            requestAnimationFrame(frame);
        } else {
            node.textContent = `${target}${suffix}`;
        }
    }

    requestAnimationFrame(frame);
}

function initLoopStrip(strip) {
    const track = strip.querySelector("[data-loop-track]");

    if (!track) {
        return;
    }

    const items = Array.from(track.children);

    if (items.length < 2) {
        return;
    }

    const stepDelay = Number(strip.dataset.loopStepDelay || 2800);
    let paused = false;
    let stepTimer = null;
    let isAnimating = false;

    function getGap() {
        return Number.parseFloat(window.getComputedStyle(track).gap || "0") || 0;
    }

    function getStepDistance() {
        const first = track.firstElementChild;

        if (!first) {
            return 0;
        }

        return first.getBoundingClientRect().width + getGap();
    }

    function resetTrackPosition() {
        track.style.transition = "none";
        track.style.transform = "translate3d(0, 0, 0)";
    }

    function stopLoop() {
        if (stepTimer) {
            window.clearInterval(stepTimer);
            stepTimer = null;
        }
    }

    function stepLoop() {
        if (paused || isAnimating) {
            return;
        }

        const distance = getStepDistance();

        if (!distance) {
            return;
        }

        isAnimating = true;
        track.style.transition = "transform 560ms cubic-bezier(0.22, 0.61, 0.36, 1)";
        track.style.transform = `translate3d(${-distance}px, 0, 0)`;
    }

    function startLoop() {
        stopLoop();

        stepTimer = window.setInterval(stepLoop, stepDelay);
    }

    function rebuildLoop() {
        stopLoop();
        isAnimating = false;
        resetTrackPosition();
        window.setTimeout(stepLoop, Math.min(stepDelay, 900));
        startLoop();
    }

    track.addEventListener("transitionend", (event) => {
        if (event.target !== track || event.propertyName !== "transform") {
            return;
        }

        const first = track.firstElementChild;

        if (first) {
            track.appendChild(first);
        }

        resetTrackPosition();
        void track.offsetWidth;
        track.style.transition = "";
        isAnimating = false;
    });

    window.addEventListener("resize", () => {
        rebuildLoop();
    }, { passive: true });
    window.addEventListener("load", rebuildLoop, { once: true });

    strip.addEventListener("mouseenter", () => {
        paused = true;
        stopLoop();
    });

    strip.addEventListener("mouseleave", () => {
        paused = false;
        startLoop();
    });

    strip.addEventListener("focusin", () => {
        paused = true;
        stopLoop();
    });

    strip.addEventListener("focusout", () => {
        paused = false;
        startLoop();
    });

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopLoop();
            return;
        }

        startLoop();
    });

    rebuildLoop();
}

function trackVisit() {
    postPublicApi("/api/visitor-events", {
        client_id: ensureSupportClientId(),
        session_code: currentSupportSessionCode(),
        path: currentSupportPath(),
        title: document.title,
        referrer: document.referrer,
        language: currentSupportLanguage(),
    }, true).catch(() => {});
}

enhanceSectionHeadingActions();
hydrateStaticCategoryFilters();

if (dropdown && dropdownTrigger) {
    dropdownTrigger.addEventListener("click", () => {
        const open = dropdown.classList.toggle("open");
        dropdownTrigger.setAttribute("aria-expanded", String(open));
        if (open) {
            closeProductMenu();
            ensureLanguageMenuRendered();
            requestAnimationFrame(() => {
                if (menu && mobileFabMedia?.matches) {
                    menu.scrollTo({
                        top: Math.max(dropdown.offsetTop - 8, 0),
                        behavior: "smooth",
                    });
                    return;
                }

                langMenu?.scrollIntoView({
                    block: "nearest",
                    inline: "nearest",
                    behavior: "smooth",
                });
            });
        }
    });

    document.addEventListener("click", (event) => {
        if (!dropdown.contains(event.target)) {
            closeDropdown();
        }
    });
}

if (productNav && productTrigger && productPanel) {
    const productSplit = productNav.querySelector(".nav-link-split");

    productTrigger.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const open = !productNav.classList.contains("open");

        if (open) {
            openProductMenu();
            closeDropdown();
            closeNavDropdowns();
            return;
        }

        closeProductMenu();
    });

    productSplit?.addEventListener("click", (event) => {
        if (!mobileFabMedia?.matches) {
            return;
        }

        if (event.target.closest("[data-product-trigger]")) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const open = !productNav.classList.contains("open");

        if (open) {
            openProductMenu();
            closeDropdown();
            closeNavDropdowns();
            return;
        }

        closeProductMenu();
    });

    productNav.addEventListener("mouseenter", () => {
        if (mobileFabMedia?.matches) {
            return;
        }

        clearProductHoverCloseTimer();
        openProductMenu();
        closeDropdown();
        closeNavDropdowns();
    });

    productNav.addEventListener("mouseleave", () => {
        if (mobileFabMedia?.matches) {
            return;
        }

        scheduleProductMenuClose();
    });

    productNav.addEventListener("focusin", () => {
        if (mobileFabMedia?.matches) {
            return;
        }

        clearProductHoverCloseTimer();
        openProductMenu();
        closeDropdown();
        closeNavDropdowns();
    });

    productNav.addEventListener("focusout", (event) => {
        if (mobileFabMedia?.matches) {
            return;
        }

        if (productNav.contains(event.relatedTarget)) {
            return;
        }

        scheduleProductMenuClose();
    });

    productTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            setProductTab(tab.dataset.productTab);
        });
    });

    productPanel.querySelectorAll(".nav-tree-root, .nav-tree-leaf-list a").forEach((link) => {
        link.addEventListener("click", () => {
            closeProductMenu();
        });
    });

    mobileProductAccordion?.addEventListener("click", (event) => {
        const sectionTrigger = event.target.closest(".mobile-product-section-trigger");
        const branchTrigger = event.target.closest(".mobile-product-branch-trigger");
        const productLink = event.target.closest(".mobile-product-links a");

        if (productLink) {
            closeProductMenu();
            return;
        }

        if (branchTrigger) {
            event.preventDefault();
            const branch = branchTrigger.closest(".mobile-product-branch");
            const section = branch?.closest(".mobile-product-section");
            const links = branch?.querySelector(".mobile-product-links");
            const nextOpen = !branch?.classList.contains("is-open");

            section?.querySelectorAll(".mobile-product-branch").forEach((item) => {
                item.classList.remove("is-open");
                item.querySelector(".mobile-product-branch-trigger")?.setAttribute("aria-expanded", "false");
                const itemLinks = item.querySelector(".mobile-product-links");
                if (itemLinks) {
                    itemLinks.hidden = true;
                }
            });

            if (branch && links && nextOpen) {
                branch.classList.add("is-open");
                branchTrigger.setAttribute("aria-expanded", "true");
                links.hidden = false;
            }

            return;
        }

        if (sectionTrigger) {
            event.preventDefault();
            const section = sectionTrigger.closest(".mobile-product-section");
            const body = section?.querySelector(".mobile-product-section-body");
            const nextOpen = !section?.classList.contains("is-open");

            mobileProductAccordion?.querySelectorAll(".mobile-product-section").forEach((item) => {
                item.classList.remove("is-open");
                item.querySelector(".mobile-product-section-trigger")?.setAttribute("aria-expanded", "false");
                const itemBody = item.querySelector(".mobile-product-section-body");
                if (itemBody) {
                    itemBody.hidden = true;
                    itemBody.style.display = "none";
                }
            });

            if (section && body && nextOpen) {
                section.classList.add("is-open");
                sectionTrigger.setAttribute("aria-expanded", "true");
                body.hidden = false;
                body.style.display = "grid";
            }
        }
    });

    productBranches.forEach((branch) => {
        const trigger = branch.querySelector(".nav-tree-branch-title");

        if (!trigger) {
            return;
        }

        trigger.setAttribute("aria-expanded", "false");

        trigger.addEventListener("click", (event) => {
            if (!isMobileProductAccordion()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const view = branch.closest("[data-product-view]");
            const siblings = view ? Array.from(view.querySelectorAll(".nav-tree-branch")) : [];
            const nextState = !branch.classList.contains("open");

            siblings.forEach((item) => setProductBranch(item, false));

            setProductBranch(branch, nextState);
        });
    });

    document.addEventListener("click", (event) => {
        if (!productNav.contains(event.target)) {
            closeProductMenu();
        }
    });
}

if (navDropdownItems.length) {
    navDropdownItems.forEach((item) => {
        const trigger = item.querySelector("[data-nav-dropdown-trigger]");
        const panel = item.querySelector("[data-nav-dropdown-panel]");
        const split = item.querySelector(".nav-link-split");

        if (!trigger || !panel) {
            return;
        }

        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();

            const nextOpen = !item.classList.contains("open");
            closeNavDropdowns(item);

            if (nextOpen) {
                closeDropdown();
                closeProductMenu();
            }

            setNavDropdownState(item, nextOpen);
        });

        split?.addEventListener("click", (event) => {
            if (!mobileFabMedia?.matches) {
                return;
            }

            if (event.target.closest("[data-nav-dropdown-trigger]")) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const nextOpen = !item.classList.contains("open");
            closeNavDropdowns(item);

            if (nextOpen) {
                closeDropdown();
                closeProductMenu();
            }

            setNavDropdownState(item, nextOpen);
        });

        item.addEventListener("mouseenter", () => {
            if (mobileFabMedia?.matches) {
                return;
            }

            clearNavDropdownCloseTimer(item);
            closeDropdown();
            closeProductMenu();
            closeNavDropdowns(item);
            setNavDropdownState(item, true);
        });

        item.addEventListener("mouseleave", () => {
            if (mobileFabMedia?.matches) {
                return;
            }

            scheduleNavDropdownClose(item);
        });

        item.addEventListener("focusin", () => {
            if (mobileFabMedia?.matches) {
                return;
            }

            clearNavDropdownCloseTimer(item);
            closeDropdown();
            closeProductMenu();
            closeNavDropdowns(item);
            setNavDropdownState(item, true);
        });

        item.addEventListener("focusout", (event) => {
            if (mobileFabMedia?.matches) {
                return;
            }

            if (item.contains(event.relatedTarget)) {
                return;
            }

            scheduleNavDropdownClose(item);
        });

        panel.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                closeNavDropdowns();
            });
        });
    });

    document.addEventListener("click", (event) => {
        if (Array.from(navDropdownItems).some((item) => item.contains(event.target))) {
            return;
        }

        closeNavDropdowns();
    });
}

if (contactFab && contactTrigger) {
    if (!mobileFabMedia || !mobileFabMedia.matches) {
        contactFab.classList.add("attention");
    }

    populateSalesContactMenus();

    contactChoosers.forEach((chooser) => {
        const trigger = chooser.querySelector("[data-contact-chooser-trigger]");
        const options = chooser.querySelectorAll(".float-option");

        if (!trigger) {
            return;
        }

        const openChooser = (event) => {
            event.preventDefault();
            event.stopPropagation();

            const nextOpen = !chooser.classList.contains("open");
            closeContactChoosers(chooser);
            setContactChooserState(chooser, nextOpen);

            if (nextOpen && isFabContactChooser(chooser)) {
                openContactFab();
            }
        };

        trigger.addEventListener("click", openChooser);
        trigger.addEventListener("contextmenu", openChooser);

        options.forEach((option) => {
            option.addEventListener("click", () => {
                closeContactChoosers();
                closeContactFab();
                syncContactFabVisibility();
            });
        });
    });

    contactTrigger.addEventListener("click", () => {
        closeContactChoosers();
        const open = contactFab.classList.contains("open");
        if (open) {
            closeContactFab();
        } else {
            openContactFab();
        }
    });

    document.addEventListener("click", (event) => {
        const clickedInsideChooser = Array.from(contactChoosers).some((chooser) => chooser.contains(event.target));

        if (clickedInsideChooser || contactFab.contains(event.target)) {
            return;
        }

        closeContactChoosers();
        closeContactFab();
        syncContactFabVisibility();
    });
}

if (backToTopButtons.length) {
    backToTopButtons.forEach((button) => {
        button.addEventListener("click", scrollToTop);
    });
}

bindHeroAnchorButtons();
syncInitialHeroHash();

if (backToTopButton) {
    window.addEventListener("scroll", syncBackToTopVisibility, { passive: true });
    syncBackToTopVisibility();
}

if (contactFab) {
    window.addEventListener("scroll", syncContactFabVisibility, { passive: true });
    window.addEventListener("resize", syncContactFabVisibility, { passive: true });
    syncContactFabVisibility();
}

if (supportTriggers.length) {
    supportTriggers.forEach((button) => {
        button.addEventListener("click", () => {
            closeContactChoosers();
            closeContactFab();
            openSupportPanel();
        });
    });
}

if (supportCloseButtons.length) {
    supportCloseButtons.forEach((button) => {
        button.addEventListener("click", () => {
            closeSupportPanel();
        });
    });
}

if (supportPromptButtons.length) {
    supportPromptButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const prompt = button.dataset.supportPrompt || button.textContent.trim();
            openSupportPanel(prompt);
        });
    });
}

if (supportForm && supportInput) {
    supportForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        await submitSupportMessageLive(supportInput.value);
    });
}

if (leadForms.length) {
    leadForms.forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            await submitLeadFormLive(form);
        });
    });
}

if (wechatTrigger) {
    wechatTrigger.addEventListener("click", () => {
        closeSupportPanel();
        openWechatPanel();
    });
}

if (wechatCloseButtons.length) {
    wechatCloseButtons.forEach((button) => {
        button.addEventListener("click", () => {
            closeWechatPanel();
        });
    });
}

if (wechatCopyButton) {
    wechatCopyButton.addEventListener("click", async () => {
        const value = wechatCopyButton.dataset.copyValue || "";
        const label = wechatCopyButton.querySelector("span");
        const success = await copyTextValue(value);
        const isEnglish = getContentLanguage(normalizedLang(body.dataset.lang || "zh")) === "en";

        if (!label) {
            return;
        }

        const resetText = isEnglish ? "Copy WeChat ID" : "复制微信号";
        label.textContent = success ? (isEnglish ? "Copied" : "已复制") : resetText;
        window.setTimeout(() => {
            label.textContent = resetText;
        }, 1800);
    });
}

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeSupportPanel();
        closeWechatPanel();
    }
});

window.addEventListener("resize", () => {
    syncProductBranches(getActiveProductTabName());
    if (mobileProductAccordion) {
        mobileProductAccordion.hidden = !isMobileProductAccordion();
    }
}, { passive: true });

langMenu?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-lang-option]");

    if (!button) {
        return;
    }

    if (isStaticGeneratedPublicPage) {
        safeStorageSet("hanzun-lang", normalizedLang(button.dataset.langOption));
        safeStorageSet("hanzun-lang-source", "manual");
        window.location.href = currentLocalizedStaticUrlForLanguage(button.dataset.langOption);
        return;
    }

    applyLanguage(button.dataset.langOption, { persistMode: "manual" });
    hydratePublicSite(button.dataset.langOption);
    closeDropdown();
});

if (menuToggle && menu) {
    menuToggle.addEventListener("click", () => {
        setMenuState(!menu.classList.contains("open"));
    });

    menu.addEventListener("click", (event) => {
        const link = event.target.closest('a[href^="#"]');
        const hash = link?.getAttribute("href") || "";

        if (!link || !hash || hash === "#") {
            return;
        }

        if (!scrollToAnchorTarget(hash, true)) {
            return;
        }

        event.preventDefault();
    });

    menu.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", (event) => {
            if (mobileFabMedia?.matches && link.closest(".nav-link-split")) {
                return;
            }

            setMenuState(false);
        });
    });

    document.addEventListener("click", (event) => {
        if (!menu.classList.contains("open")) {
            return;
        }

        if (menu.contains(event.target) || menuToggle.contains(event.target)) {
            return;
        }

        setMenuState(false);
    });
}

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeDropdown();
        closeProductMenu();
        closeNavDropdowns();
        closeContactFab();
        setMenuState(false);
    }
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (!entry.isIntersecting) {
            return;
        }

        entry.target.classList.add("is-visible");

        if (entry.target.matches("[data-count]")) {
            animateCounter(entry.target);
        }
    });
}, { threshold: 0.18 });

revealItems.forEach((item) => observer.observe(item));
counters.forEach((counter) => observer.observe(counter));
loopStrips.forEach((strip) => initLoopStrip(strip));
if (certificateStage) {
    initCertificateStage(certificateStage);
}

initProgressiveMedia();
initLazyVideo();
const initialLanguage = resolveInitialPublicLanguage();
applyLanguage(initialLanguage, { persistMode: "none" });
ensureLanguageMenuRendered();
hydratePublicSite(initialLanguage);
setProductTab("factory");
trackVisit();
void hydrateSupportConversation();
