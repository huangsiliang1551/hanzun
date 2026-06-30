/**
 * site-runtime.js — 公共核心基础库
 *
 * 所有页面（首页/列表页/详情页/单页）都需要加载。
 * 提供：DOM 引用、localStorage 包装、语言元数据、API 请求、
 *       client-id / session-code、trackVisit、initProgressiveMedia、initLazyVideo、
 *       滚动锚点、reveal/counters observer。
 *
 * 暴露到 window.HanzunRuntime，被 site-nav / site-aside / home-* 模块复用。
 * 本文件不依赖任何其他 JS，必须在其他模块之前加载。
 */
(function () {
    "use strict";

    var body = document.body;
    var html = document.documentElement;

    /* ───────────────────────── DOM 引用 ───────────────────────── */
    var menuToggle = document.querySelector("[data-menu-toggle]");
    var menu = document.querySelector("[data-menu]");
    var dropdown = document.querySelector("[data-lang-dropdown]");
    var dropdownTrigger = document.querySelector("[data-lang-trigger]");
    var dropdownLabel = document.querySelector("[data-lang-label]");
    var dropdownFlag = document.querySelector("[data-lang-flag]");
    var langMenu = document.querySelector("[data-lang-menu]");
    var productNav = document.querySelector("[data-product-nav]");
    var productTrigger = document.querySelector("[data-product-trigger]");
    var productPanel = document.querySelector("[data-product-panel]");
    var navDropdownItems = document.querySelectorAll("[data-nav-dropdown]");
    var productTabs = document.querySelectorAll("[data-product-tab]");
    var megaNavItems = document.querySelectorAll("[data-mega-nav]");
    var productViews = document.querySelectorAll("[data-product-view]");
    var productBranches = document.querySelectorAll(".nav-tree-branch");
    var mobileProductAccordion = productPanel ? document.createElement("div") : null;
    var revealItems = document.querySelectorAll(".reveal");
    var counters = document.querySelectorAll("[data-count]");
    var loopStrips = document.querySelectorAll("[data-loop-strip]");
    var certificateStage = document.querySelector("[data-certificate-stage]");
    var contactFab = document.querySelector("[data-contact-fab]");
    var contactTrigger = document.querySelector("[data-contact-trigger]");
    var contactChoosers = document.querySelectorAll("[data-contact-chooser]");
    var backToTopButton = document.querySelector("[data-back-to-top]");
    var backToTopButtons = document.querySelectorAll("[data-back-to-top], [data-back-to-top-dock]");
    var supportPanel = document.querySelector("[data-support-panel]");
    var supportTriggers = document.querySelectorAll("[data-support-trigger]");
    var supportCloseButtons = document.querySelectorAll("[data-support-close]");
    var supportForm = document.querySelector("[data-support-form]");
    var supportInput = document.querySelector("[data-support-input]");
    var supportMessages = document.querySelector("[data-support-messages]");
    var supportStatus = document.querySelector("[data-support-status]");
    var supportSubmitButton = document.querySelector("[data-support-submit]");
    var supportSubmitLabel = document.querySelector("[data-support-submit-label]");
    var supportPromptButtons = document.querySelectorAll("[data-support-prompt]");
    var leadForms = document.querySelectorAll(".lead-form");
    var wechatPanel = document.querySelector("[data-wechat-panel]");
    var wechatTrigger = document.querySelector("[data-wechat-trigger]");
    var wechatCloseButtons = document.querySelectorAll("[data-wechat-close]");
    var wechatCopyButton = document.querySelector("[data-wechat-copy]");
    var metaDescription = document.querySelector("#meta-description");
    var mobileFabMedia = window.matchMedia ? window.matchMedia("(max-width: 860px)") : null;
    var heroAnchorButtons = document.querySelectorAll('.hero-actions a[href^="#"]');
    var featuredSolutionsGrid = document.querySelector("[data-home-featured-solutions]");
    var featuredProductsShowcase = document.querySelector("[data-home-featured-products]");
    var featuredCasesBoard = document.querySelector("[data-home-featured-cases]");
    var featuredNewsGrid = document.querySelector("[data-home-featured-news]");
    var contactGrid = document.querySelector("[data-contact-grid]");
    var footerContactList = document.querySelector("[data-footer-contact-list]");
    var footerFeaturedProducts = document.querySelector("[data-footer-featured-products]");
    var footerFeaturedSolutions = document.querySelector("[data-footer-featured-solutions]");
    var brandLinks = document.querySelectorAll(".brand");
    var isStaticGeneratedPublicPage = Boolean(body && body.dataset.forceLang);

    var brandLogos = document.querySelectorAll(".brand img");
    var brandTitleNodes = document.querySelectorAll(".brand .brand-copy strong");
    var brandSubtitleNodes = document.querySelectorAll(".brand .brand-copy span");
    var footerBrandLogos = document.querySelectorAll(".footer-brand-logo");
    var footerBrandTitleNodes = document.querySelectorAll(".footer-brand-title strong");
    var footerBrandSubtitleNodes = document.querySelectorAll(".footer-brand-subtitle");
    var footerBottomNodes = document.querySelectorAll(".footer-redesign-bottom span");
    var productHoverCloseTimer = null;
    var navHoverCloseTimers = new WeakMap();
    var publicSiteConfig = null;
    var languages = [];
    var languageMenuRendered = false;
    var publicBootstrapRequestVersion = 0;
    var staticDetailViewTracked = false;

    /* ───────────────────────── 语言元数据 ───────────────────────── */
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

    var languageDisplayMeta = {
        zh: { country: "China", native: "中文", content: "zh", htmlLang: "zh-CN", continent: "asia", flagCode: "cn" },
        en: { country: "United Kingdom", native: "English", content: "en", htmlLang: "en-GB", continent: "europe", flagCode: "gb" },
        es: { country: "Spain", native: "Español", content: "es", htmlLang: "es-ES", continent: "europe", flagCode: "es" },
        hi: { country: "India", native: "हिन्दी", content: "hi", htmlLang: "hi-IN", continent: "asia", flagCode: "in" },
        ar: { country: "United Arab Emirates", native: "العربية", content: "ar", htmlLang: "ar-AE", continent: "asia", flagCode: "ae" },
        fr: { country: "France", native: "Français", content: "fr", htmlLang: "fr-FR", continent: "europe", flagCode: "fr" },
        de: { country: "Germany", native: "Deutsch", content: "de", htmlLang: "de-DE", continent: "europe", flagCode: "de" },
        ja: { country: "Japan", native: "日本語", content: "ja", htmlLang: "ja-JP", continent: "asia", flagCode: "jp" },
        pt: { country: "Portugal", native: "Português", content: "pt", htmlLang: "pt-PT", continent: "europe", flagCode: "pt" },
        ru: { country: "Russia", native: "Русский", content: "ru", htmlLang: "ru-RU", continent: "europe", flagCode: "ru" },
        it: { country: "Italy", native: "Italiano", content: "it", htmlLang: "it-IT", continent: "europe", flagCode: "it" },
        ko: { country: "South Korea", native: "한국어", content: "ko", htmlLang: "ko-KR", continent: "asia", flagCode: "kr" },
        tr: { country: "Turkey", native: "Türkçe", content: "tr", htmlLang: "tr-TR", continent: "asia", flagCode: "tr" },
        nl: { country: "Netherlands", native: "Nederlands", content: "nl", htmlLang: "nl-NL", continent: "europe", flagCode: "nl" },
        pl: { country: "Poland", native: "Polski", content: "pl", htmlLang: "pl-PL", continent: "europe", flagCode: "pl" },
        vi: { country: "Vietnam", native: "Tiếng Việt", content: "vi", htmlLang: "vi-VN", continent: "asia", flagCode: "vn" },
        th: { country: "Thailand", native: "ไทย", content: "th", htmlLang: "th-TH", continent: "asia", flagCode: "th" },
        sv: { country: "Sweden", native: "Svenska", content: "sv", htmlLang: "sv-SE", continent: "europe", flagCode: "se" },
        id: { country: "Indonesia", native: "Bahasa Indonesia", content: "id", htmlLang: "id-ID", continent: "asia", flagCode: "id" },
        el: { country: "Greece", native: "Ελληνικά", content: "el", htmlLang: "el-GR", continent: "europe", flagCode: "gr" },
        cs: { country: "Czech Republic", native: "Čeština", content: "cs", htmlLang: "cs-CZ", continent: "europe", flagCode: "cz" },
        hu: { country: "Hungary", native: "Magyar", content: "hu", htmlLang: "hu-HU", continent: "europe", flagCode: "hu" },
        ro: { country: "Romania", native: "Română", content: "ro", htmlLang: "ro-RO", continent: "europe", flagCode: "ro" },
        uk: { country: "Ukraine", native: "Українська", content: "uk", htmlLang: "uk-UA", continent: "europe", flagCode: "ua" },
        ms: { country: "Malaysia", native: "Bahasa Melayu", content: "ms", htmlLang: "ms-MY", continent: "asia", flagCode: "my" },
    };

    var languageFlagCodes = {
        zh: "cn", en: "gb", "ar-SA": "sa", "bg-BG": "bg", "hr-HR": "hr", "cs-CZ": "cz",
        "da-DK": "dk", "nl-NL": "nl", "fi-FI": "fi", "fr-FR": "fr", "de-DE": "de",
        "el-GR": "gr", "hi-IN": "in", "it-IT": "it", "ja-JP": "jp", "ko-KR": "kr",
        "no-NO": "no", "pt-PT": "pt", "ro-RO": "ro", "ru-RU": "ru", "es-ES": "es",
        "sv-SE": "se", "id-ID": "id", "sr-RS": "rs", "vi-VN": "vn", "hu-HU": "hu",
        "th-TH": "th", "tr-TR": "tr", "fa-IR": "ir", "sw-TZ": "tz", "bn-BD": "bd",
        "bs-BA": "ba", "lo-LA": "la", la: "va", "mr-IN": "in", "mn-MN": "mn",
        "ta-IN": "in", "te-IN": "in", "ml-IN": "in", "si-LK": "lk", "ur-PK": "pk",
    };

    /* ───────────────────────── 通用工具 ───────────────────────── */
    function normalizeLanguageCode(code) {
        var value = String(code || "").trim().toLowerCase().replace(/_/g, "-");
        if (!value) return "";
        if (value.startsWith("zh")) return "zh";
        return value.split("-")[0] || value;
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function assetPath(path) {
        var value = String(path || "").trim();
        if (!value) return "";
        if (/^(https?:)?\/\//i.test(value) || value.startsWith("/")) return value;
        return "/" + value.replace(/^\/+/, "");
    }

    function setMetaContent(name, value) {
        if (!value) return;
        var attr = name.startsWith("og:") ? "property" : "name";
        var escaped = CSS.escape(name);
        var el = document.querySelector('meta[' + attr + '="' + escaped + '"]');
        if (!el) {
            el = document.createElement("meta");
            el.setAttribute(attr, name);
            document.head.appendChild(el);
        }
        el.setAttribute("content", value);
    }

    function setCanonicalUrl(url) {
        if (!url) return;
        var el = document.querySelector("link[rel='canonical']");
        if (!el) {
            el = document.createElement("link");
            el.setAttribute("rel", "canonical");
            document.head.appendChild(el);
        }
        el.setAttribute("href", url);
    }

    /* ───────────────────────── localStorage 包装 ───────────────────────── */
    function safeStorageGet(key) {
        try { return window.localStorage ? window.localStorage.getItem(key) : null; }
        catch (e) { return null; }
    }
    function safeStorageSet(key, value) {
        try { if (window.localStorage) window.localStorage.setItem(key, value); }
        catch (e) {}
    }
    function safeStorageRemove(key) {
        try { if (window.localStorage) window.localStorage.removeItem(key); }
        catch (e) {}
    }

    /* ───────────────────────── sessionStorage 包装 ───────────────────────── */
    var supportClientStorageKey = "hanzun-client-id";
    var supportSessionStorageKey = "hanzun-support-session";
    var supportSessionFallbackStorageKey = "hanzun-support-session-fallback";
    var supportSessionStorageAvailable = null;

    function resolveSessionStorageFallbackKey(key) {
        return key === supportSessionStorageKey ? supportSessionFallbackStorageKey : "";
    }

    function hasUsableSessionStorage() {
        if (typeof supportSessionStorageAvailable === "boolean") return supportSessionStorageAvailable;
        try {
            if (!window.sessionStorage) { supportSessionStorageAvailable = false; return false; }
            var probeKey = "__hanzun_session_probe__";
            window.sessionStorage.setItem(probeKey, "1");
            window.sessionStorage.removeItem(probeKey);
            supportSessionStorageAvailable = true;
            return true;
        } catch (e) {
            supportSessionStorageAvailable = false;
            return false;
        }
    }

    function safeSessionStorageGet(key) {
        try {
            if (hasUsableSessionStorage()) return window.sessionStorage.getItem(key);
        } catch (e) { supportSessionStorageAvailable = false; }
        var fk = resolveSessionStorageFallbackKey(key);
        return fk ? safeStorageGet(fk) : null;
    }

    function safeSessionStorageSet(key, value) {
        try {
            if (hasUsableSessionStorage()) {
                window.sessionStorage.setItem(key, value);
                var fk = resolveSessionStorageFallbackKey(key);
                if (fk) safeStorageRemove(fk);
                return;
            }
        } catch (e) { supportSessionStorageAvailable = false; }
        var fk = resolveSessionStorageFallbackKey(key);
        if (fk) safeStorageSet(fk, value);
    }

    function safeSessionStorageRemove(key) {
        try { if (hasUsableSessionStorage()) window.sessionStorage.removeItem(key); }
        catch (e) { supportSessionStorageAvailable = false; }
        var fk = resolveSessionStorageFallbackKey(key);
        if (fk) safeStorageRemove(fk);
    }

    function ensureSupportClientId() {
        var clientId = safeStorageGet(supportClientStorageKey);
        if (clientId) return clientId;
        clientId = Date.now() + "-" + Math.random().toString(16).slice(2);
        safeStorageSet(supportClientStorageKey, clientId);
        return clientId;
    }

    function currentSupportSessionCode() {
        // 优先从 localStorage 读取（跨页面/跨标签/跨访问保持）
        var code = safeStorageGet(supportSessionStorageKey);
        if (code) return code;
        // 降级查 sessionStorage（旧数据兼容）
        return safeSessionStorageGet(supportSessionStorageKey) || "";
    }

    function currentSupportLanguage() {
        return toApiLanguageCode(normalizedLang(body ? body.dataset.lang : "zh"));
    }

    function currentSupportCountry() {
        var candidates = [];
        if (Array.isArray(navigator.languages)) candidates.push.apply(candidates, navigator.languages);
        if (navigator.language) candidates.push(navigator.language);
        candidates.push(document.documentElement.lang || (body && body.dataset && body.dataset.lang) || "");
        for (var i = 0; i < candidates.length; i++) {
            var locale = String(candidates[i] || "").split(";")[0].trim();
            if (!locale) continue;
            var parts = locale.split(/[-_]/).filter(Boolean);
            if (parts.length < 2) continue;
            for (var j = parts.length - 1; j >= 1; j--) {
                var region = String(parts[j] || "").trim().toUpperCase();
                if (/^[A-Z]{2}$/.test(region)) return region;
            }
        }
        return "";
    }

    function currentSupportContentLanguage() {
        return getContentLanguage(normalizedLang(body ? body.dataset.lang : "zh")) === "en" ? "en" : "zh";
    }

    function currentSupportPath() {
        return window.location.pathname + window.location.search;
    }

    function currentUtmSource() {
        try { return new URLSearchParams(window.location.search).get("utm_source") || ""; }
        catch (e) { return ""; }
    }

    function getLocalizedSupportText(zhText, enText) {
        return getContentLanguage(normalizedLang(body ? body.dataset.lang : "zh")) === "en" ? enText : zhText;
    }

    function getLocalizedRuntimeCopy(zhText, enText) {
        return currentSupportContentLanguage() === "en" ? enText : zhText;
    }

    /* ───────────────────────── 语言映射 ───────────────────────── */
    function buildPresetLanguages() {
        return Object.entries(languageDisplayMeta).map(function (entry, index, items) {
            var code = entry[0];
            var meta = entry[1];
            return {
                code: code,
                flagCode: String(meta.flagCode || code).slice(0, 2).toLowerCase(),
                flag: getFlagEmoji(meta.flagCode || code),
                country: String(meta.country || code.toUpperCase()).trim(),
                native: String(meta.native || code.toUpperCase()).trim(),
                name: String(meta.native || code.toUpperCase()).trim(),
                content: String(meta.content || code).trim(),
                htmlLang: String(meta.htmlLang || code + "-" + String((meta.flagCode || code).slice(0, 2)).toUpperCase()).trim(),
                continent: String(meta.continent || "global").trim(),
                sort: items.length - index,
            };
        });
    }

    languages = buildPresetLanguages();

    function readPublicRuntimeConfig() {
        if (publicSiteConfig) return publicSiteConfig;
        var runtimeNode = document.getElementById("hanzun-public-runtime");
        if (runtimeNode) {
            try {
                var parsed = JSON.parse(String(runtimeNode.textContent || "").trim() || "null");
                if (parsed && typeof parsed === "object") {
                    publicSiteConfig = parsed;
                    return publicSiteConfig;
                }
            } catch (e) {
                console.warn("Failed to parse public runtime config.", e);
            }
        }
        if (window.__HANZUN_PUBLIC_RUNTIME__ && typeof window.__HANZUN_PUBLIC_RUNTIME__ === "object") {
            publicSiteConfig = window.__HANZUN_PUBLIC_RUNTIME__;
            return publicSiteConfig;
        }
        return null;
    }

    function syncRuntimeLanguages() {
        var runtime = readPublicRuntimeConfig();
        var runtimeLanguages = Array.isArray(runtime && runtime.languages) ? runtime.languages : [];
        if (!runtimeLanguages.length) return;
        languages = runtimeLanguages.map(function (item) {
            var code = normalizeLanguageCode(item.code) || "zh";
            var meta = languageDisplayMeta[code] || {};
            var flagCode = String(item.flag_code || meta.flagCode || languageFlagCodes[code] || code).slice(0, 2).toLowerCase();
            return {
                code: code,
                flagCode: flagCode,
                flag: getFlagEmoji(flagCode),
                country: String(item.country || meta.country || String(item.name || "").trim() || code.toUpperCase()).trim(),
                native: String(item.native || meta.native || String(item.name || "").trim() || code.toUpperCase()).trim(),
                name: String(item.name || meta.native || code.toUpperCase()).trim(),
                content: String(item.content || meta.content || code).trim(),
                htmlLang: String(item.htmlLang || meta.htmlLang || code + "-" + String((meta.flagCode || code).slice(0, 2)).toUpperCase()).trim(),
                continent: String(item.continent || meta.continent || "global").trim(),
                sort: Number(item.sort || 0),
            };
        }).sort(function (a, b) { return Number(b.sort || 0) - Number(a.sort || 0); });
    }

    function syncStaticAlternateLanguages() {
        var runtime = readPublicRuntimeConfig();
        var runtimeLanguages = Array.isArray(runtime && runtime.languages) ? runtime.languages : [];
        if (!isStaticGeneratedPublicPage || runtimeLanguages.length || languages.length > 2) return;
        var alternateCodes = Array.from(document.querySelectorAll('link[rel="alternate"][hreflang]'))
            .map(function (node) { return String(node.getAttribute("hreflang") || "").trim().toLowerCase(); })
            .filter(function (code) { return code && code !== "x-default"; })
            .map(function (code) { return code.slice(0, 2); })
            .filter(function (code, index, items) { return items.indexOf(code) === index; });
        if (alternateCodes.length <= 2) return;
        languages = alternateCodes.map(function (code, index) {
            var existing = languages.find(function (it) { return it.code === code; }) || {};
            var meta = languageDisplayMeta[code] || {};
            var flagCode = String(existing.flagCode || meta.flagCode || code).slice(0, 2).toLowerCase();
            return {
                code: code,
                flagCode: flagCode,
                flag: getFlagEmoji(flagCode),
                country: String(existing.country || meta.country || code.toUpperCase()).trim(),
                native: String(existing.native || meta.native || existing.name || code.toUpperCase()).trim(),
                name: String(existing.name || meta.native || code.toUpperCase()).trim(),
                content: String(existing.content || meta.content || code).trim(),
                htmlLang: String(existing.htmlLang || meta.htmlLang || code + "-" + String((meta.flagCode || code).slice(0, 2)).toUpperCase()).trim(),
                continent: String(existing.continent || meta.continent || "global").trim(),
                sort: Number(existing.sort || (alternateCodes.length - index)),
            };
        });
    }

    function buildLanguageMap() { return new Map(languages.map(function (it) { return [it.code, it]; })); }
    function getLanguageMap() { return buildLanguageMap(); }

    function normalizedLang(code) {
        var value = String(code || "").trim();
        if (getLanguageMap().has(value)) return value;
        var normalized = normalizeLanguageCode(value);
        if (normalized && getLanguageMap().has(normalized)) return normalized;
        for (var i = 0; i < languages.length; i++) {
            var c = languages[i];
            if (normalized === c.code.toLowerCase() || normalized.startsWith(c.code.slice(0, 2).toLowerCase())) return c.code;
        }
        return getLanguageMap().has("zh") ? "zh" : (languages[0] ? languages[0].code : "zh");
    }

    function getContentLanguage(code) { return (getLanguageMap().get(code) || {}).content || "en"; }

    function getLanguageLabel(code) {
        var normalized = normalizedLang(code);
        var lang = getLanguageMap().get(normalized);
        return String((lang && lang.native) || (lang && lang.name) || normalized.toUpperCase()).trim();
    }

    function getFlagCode(code) {
        var normalizedCode = normalizedLang(code);
        var lang = getLanguageMap().get(normalizedCode);
        return String((lang && lang.flagCode) || languageFlagCodes[normalizedCode] || "cn").slice(0, 2).toLowerCase();
    }

    function getFlagEmoji(countryCode) {
        var normalized = String(countryCode || "CN").slice(0, 2).toUpperCase();
        if (!/^[A-Z]{2}$/.test(normalized)) return "🏳️";
        return String.fromCodePoint(normalized.charCodeAt(0) + 127397, normalized.charCodeAt(1) + 127397);
    }

    function getFlagSymbol(code) {
        var normalizedCode = normalizedLang(code);
        var lang = getLanguageMap().get(normalizedCode);
        var flagCode = String((lang && lang.flagCode) || languageFlagCodes[normalizedCode] || "cn").slice(0, 2).toLowerCase();
        var normalized = String(flagCode || "CN").slice(0, 2).toUpperCase();
        if (!/^[A-Z]{2}$/.test(normalized)) return String.fromCodePoint(0x1F3F3, 0xFE0F);
        return String.fromCodePoint(normalized.charCodeAt(0) + 127397, normalized.charCodeAt(1) + 127397);
    }

    function getFlagBadgeSvg(value) {
        var inputCode = typeof value === "string" ? value : String((value && value.code) || "");
        var lang = typeof value === "object" && value
            ? value
            : (getLanguageMap().get(normalizedLang(inputCode)) || getLanguageMap().get("zh"));
        var directFlagCode = String((value && value.flagCode) || "").slice(0, 2).toLowerCase();
        var flagCode = directFlagCode || getFlagCode(inputCode);
        var fallback = typeof value === "object" && value ? getFlagEmoji(flagCode) : getFlagSymbol(inputCode);
        var escapedFallback = escapeHtml(fallback);
        var altText = escapeHtml(lang.country);
        return '<img src="' + assetPath('assets/images/flags/' + flagCode + '.svg') + '" alt="' + altText + ' flag" loading="lazy" decoding="async" data-flag-fallback="' + escapedFallback + '">';
    }

    function toApiLanguageCode(code) {
        var normalized = String(code || "").trim().toLowerCase();
        if (!normalized) return "zh";
        if (normalized.startsWith("zh")) return "zh";
        return normalized.slice(0, 2);
    }

    function currentStaticLanguagePrefix() {
        return "/" + toApiLanguageCode((body && body.dataset.lang) || "zh");
    }

    function localizedStaticFile(path) {
        var normalized = String(path || "").trim().replace(/^\/+/, "");
        return currentStaticLanguagePrefix() + "/" + normalized;
    }

    function currentLocalizedStaticUrlForLanguage(code) {
        var targetCode = toApiLanguageCode(code || ((body && body.dataset.lang) || "zh"));
        var pathname = String(window.location.pathname || "/").replace(/\\/g, "/");
        var stripped = pathname.replace(/^\/[a-z]{2}(?=\/)/i, "") || "/index.html";
        return "/" + targetCode + stripped + (window.location.hash || "");
    }

    function detectBrowserLanguage() {
        var candidates = [];
        if (Array.isArray(navigator.languages)) candidates.push.apply(candidates, navigator.languages);
        if (navigator.language) candidates.push(navigator.language);
        for (var i = 0; i < candidates.length; i++) {
            var mapped = normalizedLang(candidates[i]);
            if (mapped) return mapped;
        }
        return "zh";
    }

    function readStoredPublicLanguage() {
        return String(safeStorageGet("hanzun-lang") || "").trim();
    }
    function readStoredPublicLanguageSource() {
        return String(safeStorageGet("hanzun-lang-source") || "").trim();
    }
    function hasStoredManualLanguageChoice() {
        return readStoredPublicLanguage() !== "" && readStoredPublicLanguageSource() === "manual";
    }
    function hasExplicitPublicLanguageChoice() {
        return String((body && body.dataset.forceLang) || "").trim() !== "" || hasStoredManualLanguageChoice();
    }

    function resolveInitialPublicLanguage() {
        var forced = String((body && body.dataset.forceLang) || "").trim();
        if (forced) return forced;
        var urlLang = new URLSearchParams(window.location.search).get('lang');
        if (urlLang) {
            var normalized = normalizedLang(urlLang);
            if (normalized) return normalized;
        }
        if (hasStoredManualLanguageChoice()) {
            var stored = readStoredPublicLanguage();
            if (stored) return stored;
        }
        var detected = String(detectBrowserLanguage() || "").trim();
        if (detected) return detected;
        return String((body && body.dataset.lang) || "zh").trim() || "zh";
    }

    function resolvePreferredRuntimeLanguage(site, currentCode) {
        var forced = String((body && body.dataset.forceLang) || "").trim();
        if (forced) return forced;
        if (hasStoredManualLanguageChoice()) return readStoredPublicLanguage();
        var strategy = String((site && site.language_strategy) || "ua-first").trim();
        var defaultLanguage = String((site && site.default_language) || "").trim();
        if (strategy === "default-first" && defaultLanguage) return defaultLanguage;
        return String(currentCode || readStoredPublicLanguage() || detectBrowserLanguage() || defaultLanguage || (body && body.dataset.lang) || "zh").trim() || "zh";
    }

    function readPublicSiteConfig() {
        return window.__HANZUN_PUBLIC_SITE_CONFIG__ || publicSiteConfig || null;
    }

    /* ───────────────────────── API 请求 ───────────────────────── */
    function readConfiguredPublicApiBase() {
        var runtime = readPublicRuntimeConfig();
        var configured = [runtime && runtime.public_api_base, runtime && runtime.publicApiBase, runtime && runtime.api_base, runtime && runtime.apiBase]
            .find(function (v) { return typeof v === "string" && v.trim() !== ""; });
        return configured ? configured.replace(/\/+$/, "") : "";
    }

    function isPrivatePreviewHostname(hostname) {
        var value = String(hostname || "").trim().toLowerCase();
        if (!value) return false;
        if (value === "localhost" || value === "0.0.0.0" || value === "::1" || value === "[::1]") return true;
        if (/^127(?:\.\d{1,3}){3}$/.test(value)) return true;
        if (/^10(?:\.\d{1,3}){3}$/.test(value)) return true;
        if (/^192\.168(?:\.\d{1,3}){2}$/.test(value)) return true;
        if (/^172\.(1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2}$/.test(value)) return true;
        return false;
    }

    function resolvePublicApiOrigin() {
        var configuredBase = readConfiguredPublicApiBase();
        if (configuredBase) return configuredBase;
        var location = window.location;
        if (!location || !/^https?:$/i.test(location.protocol || "")) return "";
        if (String(location.port || "") === "8091" && isPrivatePreviewHostname(location.hostname)) {
            return location.protocol + "//" + location.hostname + ":8080";
        }
        return "";
    }

    function publicApiUrl(path) {
        var value = String(path || "").trim();
        if (!value) return "";
        if (/^(https?:)?\/\//i.test(value)) return value;
        var normalized = value.startsWith("/") ? value : "/" + value;
        var origin = resolvePublicApiOrigin();
        return origin ? origin + normalized : normalized;
    }

    function requestPublicApi(path, options) {
        options = options || {};
        var url = publicApiUrl(path);
        var method = String(options.method || "GET").toUpperCase();
        var headers = options.headers && typeof options.headers === "object" ? options.headers : {};
        var reqBody = Object.prototype.hasOwnProperty.call(options, "body") ? options.body : undefined;

        if (typeof fetch === "function") {
            return fetch(url, {
                method: method,
                headers: headers,
                body: reqBody,
                keepalive: Boolean(options.keepalive),
            });
        }

        return new Promise(function (resolve, reject) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                Object.entries(headers).forEach(function (entry) {
                    if (entry[1] !== undefined && entry[1] !== null) xhr.setRequestHeader(entry[0], String(entry[1]));
                });
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    resolve({
                        ok: xhr.status >= 200 && xhr.status < 300,
                        status: xhr.status,
                        json: async function () { return JSON.parse(String(xhr.responseText || "null")); },
                        text: async function () { return String(xhr.responseText || ""); },
                    });
                };
                xhr.onerror = function () { reject(new Error("Request failed")); };
                xhr.send(reqBody === undefined ? null : reqBody);
            } catch (e) { reject(e); }
        });
    }

    async function postPublicApi(path, payload, keepalive) {
        var response = await requestPublicApi(path, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            keepalive: Boolean(keepalive),
        });
        var result = await response.json().catch(function () { return null; });
        if (!response.ok || !result || Number(result.code) !== 0) {
            var error = new Error(result && typeof result.message === "string" ? result.message : "Request failed");
            error.code = (result && Number(result.code)) || 0;
            throw error;
        }
        if (result.data && result.data.session_code) {
            // 存 localStorage 保证跨页面/跨标签/跨访问不丢
            safeStorageSet(supportSessionStorageKey, result.data.session_code);
            // 同步写 sessionStorage 兼容旧逻辑
            safeSessionStorageSet(supportSessionStorageKey, result.data.session_code);
        }
        return result.data || {};
    }

    async function getPublicApi(path) {
        var response = await requestPublicApi(path, { method: "GET", headers: { Accept: "application/json" } });
        var result = await response.json().catch(function () { return null; });
        if (!response.ok || !result || Number(result.code) !== 0) {
            var error = new Error(result && typeof result.message === "string" ? result.message : "Request failed");
            error.code = (result && Number(result.code)) || 0;
            throw error;
        }
        return result;
    }

    function trackVisit() {
        postPublicApi("/api/visitor-events", {
            client_id: ensureSupportClientId(),
            session_code: currentSupportSessionCode(),
            path: currentSupportPath(),
            title: document.title,
            referrer: document.referrer,
            language: currentSupportLanguage(),
            country_code: currentSupportCountry(),
        }, true).catch(function () {});
    }

    /* ───────────────────────── 媒体渐进加载 ───────────────────────── */
    var progressiveMediaWrapperSelector = [
        ".delivery-media", ".showcase-feature-media", ".showcase-mini-media",
        ".notice-banner-media", ".metrics-dashboard-visual", ".metrics-cert-media",
        ".case-hero-media", ".case-list-media", ".news-media",
        ".sales-avatar", ".wechat-card-qr",
    ].join(", ");

    function getProgressiveMediaWrapper(image) {
        return image.closest(progressiveMediaWrapperSelector) || image.parentElement;
    }

    function initProgressiveMedia() {
        document.querySelectorAll("img[data-progressive-media]").forEach(function (image) {
            var wrapper = getProgressiveMediaWrapper(image);
            if (!image.hasAttribute("decoding")) image.setAttribute("decoding", "async");
            if (!image.hasAttribute("loading")) image.setAttribute("loading", "lazy");
            image.classList.add("progressive-blur");

            var markReady = function () {
                image.classList.add("is-loaded");
                image.classList.remove("progressive-blur");
                if (wrapper) wrapper.classList.add("media-ready");
            };
            var markError = function () {
                image.classList.add("is-error");
                image.classList.remove("progressive-blur");
                if (wrapper) wrapper.classList.add("media-ready");
            };

            if (image.complete && image.naturalWidth > 0) { markReady(); return; }
            if (wrapper) wrapper.classList.remove("media-ready");
            image.addEventListener("load", markReady, { once: true });
            image.addEventListener("error", markError, { once: true });
        });
    }

    function initLazyVideo() {
        var video = document.querySelector("video[data-progressive-video]");
        if (!video) return;
        var source = String(video.getAttribute("data-src") || video.getAttribute("src") || "").trim();
        if (video.hasAttribute("src")) video.removeAttribute("src");
        video.setAttribute("preload", "none");

        var loadVideo = function () {
            if (!source || video.getAttribute("src")) return;
            video.src = source;
            video.setAttribute("preload", "metadata");
            video.classList.add("video-ready");
            video.load();
        };

        video.addEventListener("play", function () { loadVideo(); }, { once: true });
        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) { loadVideo(); observer.unobserve(video); }
                });
            }, { rootMargin: "200px" });
            observer.observe(video);
        } else {
            loadVideo();
        }
    }

    /* ───────────────────────── 滚动锚点 ───────────────────────── */
    function getAnchorOffset() {
        var header = document.querySelector(".site-header");
        return ((header && header.offsetHeight) || 0) + 18;
    }

    function scrollToAnchorTarget(hash, updateHistory, behavior) {
        if (!hash || !hash.startsWith("#")) return false;
        var target = document.querySelector(hash);
        if (!target) return false;
        var targetTop = target.getBoundingClientRect().top + window.scrollY - getAnchorOffset();
        window.scrollTo({ top: Math.max(0, targetTop), behavior: behavior || "smooth" });
        if (updateHistory) window.history.pushState(null, "", hash);
        return true;
    }

    function bindHeroAnchorButtons() {
        if (!heroAnchorButtons.length) return;
        heroAnchorButtons.forEach(function (link) {
            link.addEventListener("click", function (event) {
                var hash = link.getAttribute("href");
                if (!scrollToAnchorTarget(hash, true)) return;
                event.preventDefault();
            });
        });
    }

    function syncInitialHeroHash() {
        if (window.location.hash !== "#contact" && window.location.hash !== "#clients") return;
        window.setTimeout(function () { scrollToAnchorTarget(window.location.hash, false, "auto"); }, 120);
    }

    /* ───────────────────────── reveal / counters observer ───────────────────────── */
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            if (entry.target.matches("[data-count]") && typeof HanzunRuntime.animateCounter === "function") {
                HanzunRuntime.animateCounter(entry.target);
            }
        });
    }, { threshold: 0.18 });

    revealItems.forEach(function (item) { observer.observe(item); });
    counters.forEach(function (c) { observer.observe(c); });

    function hydrateStaticCategoryFilters() {
        var filterRoot = document.querySelector('[data-category-filter-root="1"]');
        if (!filterRoot) return;
        var categoryButtons = Array.from(filterRoot.querySelectorAll("[data-category-slug]"));
        var categoryCards = Array.from(document.querySelectorAll("[data-category-card]"));
        var activeSlug = String(new URLSearchParams(window.location.search).get("category") || "").trim().toLowerCase();
        var hasMatchingButton = activeSlug !== "" && categoryButtons.some(function (b) { return String(b.dataset.categorySlug || "").trim().toLowerCase() === activeSlug; });
        var effectiveSlug = hasMatchingButton ? activeSlug : "";
        var allButton = filterRoot.querySelector('[data-category-all="1"]');
        if (allButton) {
            allButton.classList.toggle("is-active", effectiveSlug === "");
            if (effectiveSlug !== "") allButton.removeAttribute("aria-current");
            else allButton.setAttribute("aria-current", "page");
        }
        categoryButtons.forEach(function (button) {
            var buttonSlug = String(button.dataset.categorySlug || "").trim().toLowerCase();
            var isActive = effectiveSlug !== "" && buttonSlug === effectiveSlug;
            button.classList.toggle("is-active", isActive);
            if (isActive) button.setAttribute("aria-current", "page");
            else button.removeAttribute("aria-current");
        });
        categoryCards.forEach(function (card) {
            var cardSlug = String(card.dataset.categorySlug || "").trim().toLowerCase();
            var matches = effectiveSlug === "" || cardSlug === effectiveSlug;
            card.hidden = !matches;
            card.style.display = matches ? "" : "none";
        });
    }

    /* ───────────────────────── 启动 ───────────────────────── */
    syncRuntimeLanguages();
    syncStaticAlternateLanguages();
    bindHeroAnchorButtons();
    syncInitialHeroHash();
    hydrateStaticCategoryFilters();
    initProgressiveMedia();
    initLazyVideo();
    trackVisit();

    /* ───────────────────────── 暴露 API ───────────────────────── */
    window.HanzunRuntime = {
        // DOM 引用
        body: body, html: html,
        menuToggle: menuToggle, menu: menu,
        dropdown: dropdown, dropdownTrigger: dropdownTrigger,
        dropdownLabel: dropdownLabel, dropdownFlag: dropdownFlag, langMenu: langMenu,
        productNav: productNav, productTrigger: productTrigger, productPanel: productPanel,
        navDropdownItems: navDropdownItems, productTabs: productTabs,
        megaNavItems: megaNavItems, productViews: productViews, productBranches: productBranches,
        mobileProductAccordion: mobileProductAccordion,
        revealItems: revealItems, counters: counters,
        loopStrips: loopStrips, certificateStage: certificateStage,
        contactFab: contactFab, contactTrigger: contactTrigger, contactChoosers: contactChoosers,
        backToTopButton: backToTopButton, backToTopButtons: backToTopButtons,
        supportPanel: supportPanel, supportTriggers: supportTriggers,
        supportCloseButtons: supportCloseButtons, supportForm: supportForm,
        supportInput: supportInput, supportMessages: supportMessages,
        supportStatus: supportStatus, supportSubmitButton: supportSubmitButton,
        supportSubmitLabel: supportSubmitLabel, supportPromptButtons: supportPromptButtons,
        leadForms: leadForms,
        wechatPanel: wechatPanel, wechatTrigger: wechatTrigger,
        wechatCloseButtons: wechatCloseButtons, wechatCopyButton: wechatCopyButton,
        metaDescription: metaDescription, mobileFabMedia: mobileFabMedia,
        heroAnchorButtons: heroAnchorButtons,
        featuredSolutionsGrid: featuredSolutionsGrid, featuredProductsShowcase: featuredProductsShowcase,
        featuredCasesBoard: featuredCasesBoard, featuredNewsGrid: featuredNewsGrid,
        contactGrid: contactGrid, footerContactList: footerContactList,
        footerFeaturedProducts: footerFeaturedProducts, footerFeaturedSolutions: footerFeaturedSolutions,
        brandLinks: brandLinks,
        brandLogos: brandLogos, brandTitleNodes: brandTitleNodes, brandSubtitleNodes: brandSubtitleNodes,
        footerBrandLogos: footerBrandLogos, footerBrandTitleNodes: footerBrandTitleNodes,
        footerBrandSubtitleNodes: footerBrandSubtitleNodes, footerBottomNodes: footerBottomNodes,
        isStaticGeneratedPublicPage: isStaticGeneratedPublicPage,

        // 状态
        productHoverCloseTimer: function (v) { if (arguments.length) productHoverCloseTimer = v; return productHoverCloseTimer; },
        navHoverCloseTimers: navHoverCloseTimers,
        publicSiteConfig: function (v) { if (arguments.length) publicSiteConfig = v; return publicSiteConfig; },
        languages: function (v) { if (arguments.length) languages = v; return languages; },
        languageMenuRendered: function (v) { if (arguments.length) languageMenuRendered = v; return languageMenuRendered; },
        publicBootstrapRequestVersion: function (v) { if (arguments.length) publicBootstrapRequestVersion = v; return publicBootstrapRequestVersion; },
        staticDetailViewTracked: function (v) { if (arguments.length) staticDetailViewTracked = v; return staticDetailViewTracked; },

        // 常量
        languageDisplayMeta: languageDisplayMeta,
        languageFlagCodes: languageFlagCodes,
        supportClientStorageKey: supportClientStorageKey,
        supportSessionStorageKey: supportSessionStorageKey,
        homepageFallbackImages: {
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
        },

        // 工具函数
        escapeHtml: escapeHtml,
        assetPath: assetPath,
        setMetaContent: setMetaContent,
        setCanonicalUrl: setCanonicalUrl,
        safeStorageGet: safeStorageGet,
        safeStorageSet: safeStorageSet,
        safeStorageRemove: safeStorageRemove,
        safeSessionStorageGet: safeSessionStorageGet,
        safeSessionStorageSet: safeSessionStorageSet,
        safeSessionStorageRemove: safeSessionStorageRemove,
        getRegionalContactKeys: getRegionalContactKeys,
        normalizeLanguageCode: normalizeLanguageCode,
        normalizedLang: normalizedLang,
        getLanguageMap: getLanguageMap,
        getContentLanguage: getContentLanguage,
        getLanguageLabel: getLanguageLabel,
        getFlagCode: getFlagCode,
        getFlagEmoji: getFlagEmoji,
        getFlagSymbol: getFlagSymbol,
        getFlagBadgeSvg: getFlagBadgeSvg,
        getLanguageGroupLabel: function (group) {
            var contentLang = getContentLanguage(normalizedLang(body ? body.dataset.lang : "zh"));
            return contentLang === "zh" ? group.zh : group.en;
        },
        toApiLanguageCode: toApiLanguageCode,
        currentStaticLanguagePrefix: currentStaticLanguagePrefix,
        localizedStaticFile: localizedStaticFile,
        currentLocalizedStaticUrlForLanguage: currentLocalizedStaticUrlForLanguage,
        detectBrowserLanguage: detectBrowserLanguage,
        readStoredPublicLanguage: readStoredPublicLanguage,
        readStoredPublicLanguageSource: readStoredPublicLanguageSource,
        hasStoredManualLanguageChoice: hasStoredManualLanguageChoice,
        hasExplicitPublicLanguageChoice: hasExplicitPublicLanguageChoice,
        resolveInitialPublicLanguage: resolveInitialPublicLanguage,
        resolvePreferredRuntimeLanguage: resolvePreferredRuntimeLanguage,
        readPublicRuntimeConfig: readPublicRuntimeConfig,
        readPublicSiteConfig: readPublicSiteConfig,
        publicApiUrl: publicApiUrl,
        requestPublicApi: requestPublicApi,
        postPublicApi: postPublicApi,
        getPublicApi: getPublicApi,
        trackVisit: trackVisit,
        initProgressiveMedia: initProgressiveMedia,
        initLazyVideo: initLazyVideo,
        getAnchorOffset: getAnchorOffset,
        scrollToAnchorTarget: scrollToAnchorTarget,
        ensureSupportClientId: ensureSupportClientId,
        currentSupportSessionCode: currentSupportSessionCode,
        currentSupportLanguage: currentSupportLanguage,
        currentSupportCountry: currentSupportCountry,
        currentSupportContentLanguage: currentSupportContentLanguage,
        currentSupportPath: currentSupportPath,
        currentUtmSource: currentUtmSource,
        getLocalizedSupportText: getLocalizedSupportText,
        getLocalizedRuntimeCopy: getLocalizedRuntimeCopy,

        // 占位，由其他模块覆盖
        applyLanguage: null,
        renderLanguageMenu: null,
        ensureLanguageMenuRendered: null,
        setMenuState: null,
        openProductMenu: null,
        closeProductMenu: null,
        setProductTab: null,
        isMobileProductAccordion: null,
        syncProductBranches: null,
        getActiveProductTabName: null,
        closeDropdown: null,
        closeContactFab: null,
        openContactFab: null,
        closeContactChoosers: null,
        setContactChooserState: null,
        syncContactFabVisibility: null,
        syncBackToTopVisibility: null,
        scrollToTop: function () { window.scrollTo({ top: 0, behavior: "smooth" }); },
        openSupportPanel: null,
        closeSupportPanel: null,
        closeWechatPanel: null,
        openWechatPanel: null,
        hydrateSupportConversation: null,
        submitSupportMessageLive: null,
        submitLeadFormLive: null,
        hydratePublicSite: null,
        initLoopStrip: null,
        initCertificateStage: null,
        animateCounter: null,
        hydrateTeamStrip: null,
        hydrateCertificatesGrid: null,
    };
})();
