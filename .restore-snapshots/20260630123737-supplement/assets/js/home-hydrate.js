/**
 * home-hydrate.js — 首页板块水合
 *
 * 仅首页加载。负责：
 *   - hydratePublicSite（拉取 /api/site/bootstrap 水合首页所有板块）
 *   - hydrateHeroSection / hydrateSolutionsGrid / hydrateProductsShowcase
 *   - hydrateCasesBoard / hydrateNewsGrid
 *   - hydrateContactPanels / hydrateFooterFeaturedColumn / hydrateSiteConfig
 *   - trackStaticDetailPageView（详情页埋点，仅当 URL 匹配详情页时执行）
 *   - applyRuntimePayload / loadNavigation 桥接
 *
 * 依赖 site-runtime.js + site-nav.js + home-marquee.js（共用 HanzunRuntime）。
 * 注意：详情页（products/solutions/news/cases 的 *.html）也会加载本文件以触发
 * trackStaticDetailPageView，但 hydratePublicSite 内部会通过 isStaticGeneratedPublicPage
 * 短路掉首页专属的水合逻辑，仅做 bootstrap 拉取 + 语言应用。
 */
(function () {
    "use strict";
    var R = window.HanzunRuntime;
    if (!R) return;

    var body = R.body;
    var isStaticGeneratedPublicPage = R.isStaticGeneratedPublicPage;

    /* ───────────────────────── 内容字段解析（与 site-nav.js 共享语义） ─────────────────────────
       这里复制一份本地实现，避免与 site-nav 形成强耦合；解析规则与原 future.js 完全一致。 */
    function resolveContentTitle(item) {
        return String(
            (item && item.display_title) || (item && item.name) || (item && item.title) ||
            (item && item.name_zh) || (item && item.title_zh) || ""
        ).trim();
    }
    function resolveContentSummary(item) {
        return String(
            (item && item.display_summary) || (item && item.summary) || (item && item.subtitle) ||
            (item && item.summary_zh) || (item && item.subtitle_zh) || (item && item.description) || ""
        ).trim();
    }
    function resolveFallbackImage(type, index) {
        var pool = R.homepageFallbackImages[type] || R.homepageFallbackImages.news;
        return R.assetPath(pool[index % pool.length]);
    }
    function resolveContentImage(item, type, index) {
        var candidates = [(item && item.cover_asset_url), (item && item.cover_asset && item.cover_asset.public_url), (item && item.cover_image_url)];
        for (var i = 0; i < candidates.length; i++) {
            var value = String(candidates[i] || "").trim();
            if (value) return R.assetPath(value);
        }
        return resolveFallbackImage(type, index);
    }
    function buildStaticPublicHref(type, slug) {
        var normalizedType = String(type || "").trim().toLowerCase();
        var normalizedSlug = String(slug || "").trim();
        if (normalizedType === "about") return R.localizedStaticFile("about.html");
        if (normalizedType === "contact") return R.localizedStaticFile("about.html") + "#contact";
        if (normalizedType === "products") return normalizedSlug ? R.localizedStaticFile("products/" + encodeURIComponent(normalizedSlug) + ".html") : R.localizedStaticFile("products.html");
        if (normalizedType === "solutions") return normalizedSlug ? R.localizedStaticFile("solutions/" + encodeURIComponent(normalizedSlug) + ".html") : R.localizedStaticFile("solutions.html");
        if (normalizedType === "news") return normalizedSlug ? R.localizedStaticFile("news/" + encodeURIComponent(normalizedSlug) + ".html") : R.localizedStaticFile("news.html");
        if (normalizedType === "cases") return normalizedSlug ? R.localizedStaticFile("cases/" + encodeURIComponent(normalizedSlug) + ".html") : R.localizedStaticFile("cases.html");
        if (normalizedType === "pages") return normalizedSlug ? R.localizedStaticFile("pages/" + encodeURIComponent(normalizedSlug) + ".html") : R.localizedStaticFile("about.html");
        return "";
    }
    function mapStaticPublicHref(candidate, item) {
        var route = String(candidate || "").trim();
        if (!route) return "";
        var normalized = route.replace(/^https?:\/\/[^/]+/i, "").replace(/^\/+/, "").replace(/^[a-z]{2}\//i, "").replace(/^#/, "");
        if (!normalized) return "";
        if (normalized === "about" || normalized === "contact") return buildStaticPublicHref(normalized);
        var sections = ["products", "solutions", "news", "cases", "pages"];
        for (var i = 0; i < sections.length; i++) {
            var s = sections[i];
            if (normalized === s || normalized.indexOf(s + "/") === 0) {
                var segs = normalized.split("/");
                var slug = String((item && item.slug) || (segs.length > 1 ? segs[segs.length - 1].replace(/\.html$/i, "") : "")).trim();
                return buildStaticPublicHref(s, slug);
            }
        }
        return "";
    }
    function resolvePublicHref(item, fallback) {
        fallback = fallback || "#contact-form";
        var directUrl = String((item && item.route_path) || (item && item.url) || "").trim();
        var mappedDirectUrl = mapStaticPublicHref(directUrl, item);
        if (mappedDirectUrl) return mappedDirectUrl;
        if (directUrl) return directUrl;
        var routeKey = String((item && item.route_key) || "").trim().replace(/^\/+/, "");
        var mappedRouteKey = mapStaticPublicHref(routeKey, item);
        if (mappedRouteKey) return mappedRouteKey;
        if (routeKey) return R.localizedStaticFile(routeKey);
        var slug = String((item && item.slug) || "").trim();
        var sourceType = String((item && item.source_type) || "").trim().toLowerCase();
        var linkedEntityType = String((item && item.linked_entity_type) || "").trim().toLowerCase();
        var contentType = String((item && item.content_type) || "").trim().toLowerCase();
        var inferredType = sourceType
            || (item && item.sku ? "product" : "")
            || (contentType === "product" ? "product" : "")
            || (contentType === "solution" ? "solution" : "")
            || (contentType === "news" ? "news" : "")
            || (contentType === "case" ? "case" : "")
            || (linkedEntityType === "solution_category" ? "solution" : "")
            || (contentType === "page" ? "page" : "")
            || (String((item && item.page_key) || "").indexOf("about") >= 0 || String((item && item.code) || "").indexOf("about") >= 0 ? "about" : "");
        if (slug && inferredType === "product") return buildStaticPublicHref("products", slug);
        if (slug && inferredType === "solution") return buildStaticPublicHref("solutions", slug);
        if (slug && inferredType === "case") return buildStaticPublicHref("cases", slug);
        if (slug && inferredType === "news") return buildStaticPublicHref("news", slug);
        if (slug) return buildStaticPublicHref("pages", slug);
        if (inferredType === "about") return buildStaticPublicHref("about");
        if (String((item && item.code) || "").trim().toLowerCase() === "contact") return buildStaticPublicHref("contact");
        return fallback;
    }

    /* ───────────────────────── 通用 DOM 工具 ───────────────────────── */
    function updateImageNode(image, src, alt) {
        if (!image || !src) return;
        image.src = src;
        image.alt = alt || image.alt || "Hanzun content";
        image.setAttribute("data-progressive-media", "");
    }

    function bindNavigableCard(node, href, label) {
        if (!node || !href) return;
        node.dataset.publicHref = href;
        node.style.cursor = "pointer";
        node.setAttribute("role", "link");
        node.setAttribute("tabindex", "0");
        if (label) node.setAttribute("aria-label", label);
        if (node.dataset.publicHrefBound === "true") return;
        node.dataset.publicHrefBound = "true";
        node.addEventListener("click", function () {
            var nextHref = node.dataset.publicHref;
            if (nextHref) window.location.href = nextHref;
        });
        node.addEventListener("keydown", function (event) {
            if (event.key !== "Enter" && event.key !== " ") return;
            event.preventDefault();
            var nextHref = node.dataset.publicHref;
            if (nextHref) window.location.href = nextHref;
        });
    }

    function homepageSectionByKey(homepage, sectionKey) {
        return Array.isArray(homepage && homepage.sections)
            ? homepage.sections.find(function (s) { return (s && s.section_key) === sectionKey; })
            : null;
    }

    function updateMultiValueNode(container, values) {
        if (!container || !values.length) return;
        container.innerHTML = values.map(function (v) { return "<span>" + R.escapeHtml(v) + "</span>"; }).join("");
    }

    /* ───────────────────────── setHydratingState ───────────────────────── */
    function setHydratingState(isHydrating) {
        if (isHydrating) document.documentElement.setAttribute("data-hydrating", "");
        else {
            document.documentElement.removeAttribute("data-hydrating");
            document.documentElement.setAttribute("data-hydrated", "");
        }
    }

    /* ───────────────────────── applyRuntimePayload ───────────────────────── */
    function applyRuntimePayload(payload) {
        if (!payload || typeof payload !== "object") return;
        R.publicSiteConfig(payload);
        window.__HANZUN_PUBLIC_RUNTIME__ = payload;
        // 触发 syncRuntimeLanguages（在 site-runtime 内部状态）
        if (typeof R.syncRuntimeLanguages === "function") R.syncRuntimeLanguages();
        if (R.languageMenuRendered() && typeof R.renderLanguageMenu === "function") R.renderLanguageMenu();
    }

    /* ───────────────────────── hydrateSiteConfig ───────────────────────── */
    function hydrateSiteConfig(site) {
        var siteConfig = site && typeof site === "object" ? site : null;
        window.__HANZUN_PUBLIC_SITE_CONFIG__ = siteConfig;
        if (!siteConfig) return;

        var logoUrl = String(siteConfig.logo_url || "").trim();
        var companyName = String(siteConfig.company_name || "").trim();
        var companySubtitle = String(siteConfig.company_subtitle || "").trim();
        var logoAlt = String(siteConfig.logo_alt || companyName || siteConfig.site_name || "Hanzun").trim();
        var footerText = String(siteConfig.footer_text || "").trim();

        R.brandLinks.forEach(function (node) {
            if (companyName) node.setAttribute("aria-label", companyName);
        });
        Array.prototype.forEach.call(R.brandLogos, function (node) {
            if (logoUrl) node.src = logoUrl;
            node.alt = logoAlt;
        });
        Array.prototype.forEach.call(R.footerBrandLogos, function (node) {
            if (logoUrl) node.src = logoUrl;
            node.alt = logoAlt;
        });
        Array.prototype.forEach.call(R.brandTitleNodes, function (node) {
            if (companyName) node.textContent = companyName;
        });
        Array.prototype.forEach.call(R.footerBrandTitleNodes, function (node) {
            if (companyName) node.textContent = companyName;
        });
        Array.prototype.forEach.call(R.brandSubtitleNodes, function (node) {
            if (!node || String(node.textContent || "").trim() || !companySubtitle) return;
            node.textContent = companySubtitle;
        });
        Array.prototype.forEach.call(R.footerBrandSubtitleNodes, function (node) {
            if (!node || String(node.textContent || "").trim() || !companySubtitle) return;
            node.textContent = companySubtitle;
        });
        Array.prototype.forEach.call(R.footerBottomNodes, function (node) {
            if (footerText) node.textContent = footerText;
        });
    }

    /* ───────────────────────── hydrateHeroSection ───────────────────────── */
    function hydrateHeroSection(homepage) {
        var heroSection = homepageSectionByKey(homepage, "hero");
        var titleNode = document.querySelector(".service-support-copy h2");
        var buttonNode = document.querySelector(".service-support-button");
        if (!heroSection) return;
        if (titleNode && heroSection.title) titleNode.textContent = heroSection.title;
        var ctaText = String((heroSection && heroSection.extra_config && heroSection.extra_config.cta_text) || (heroSection && heroSection.content) || "").trim();
        if (buttonNode && ctaText) buttonNode.textContent = ctaText;
    }

    /* ───────────────────────── hydrateSolutionsGrid ───────────────────────── */
    function hydrateSolutionsGrid(items) {
        var cards = Array.from((R.featuredSolutionsGrid && R.featuredSolutionsGrid.querySelectorAll(".delivery-card")) || []);
        items.slice(0, cards.length).forEach(function (item, index) {
            var card = cards[index];
            var titleNode = card && card.querySelector("h3");
            var imageNode = card && card.querySelector("img");
            var title = resolveContentTitle(item);
            if (!card || !title) return;
            if (titleNode) titleNode.textContent = title;
            updateImageNode(imageNode, resolveContentImage(item, "solution", index), title);
            bindNavigableCard(card, resolvePublicHref(item, "/solutions"), title);
        });
    }

    /* ───────────────────────── hydrateProductsShowcase ───────────────────────── */
    function hydrateProductsShowcase(items) {
        if (!R.featuredProductsShowcase || !items.length) return;
        var heroCard = R.featuredProductsShowcase.querySelector(".showcase-feature-card");
        var heroTitle = heroCard && heroCard.querySelector("h3");
        var heroKicker = heroCard && heroCard.querySelector(".showcase-kicker");
        var heroImage = heroCard && heroCard.querySelector("img");
        var firstItem = items[0];
        if (heroCard && firstItem) {
            var heroHeading = resolveContentTitle(firstItem);
            var heroSummary = resolveContentSummary(firstItem);
            if (heroTitle && heroHeading) heroTitle.textContent = heroHeading;
            if (heroKicker && heroSummary) heroKicker.textContent = heroSummary;
            updateImageNode(heroImage, resolveContentImage(firstItem, "product", 0), heroHeading);
            bindNavigableCard(heroCard, resolvePublicHref(firstItem, "/products"), heroHeading);
        }
        var miniCards = Array.from(R.featuredProductsShowcase.querySelectorAll(".showcase-mini-card"));
        items.slice(1, miniCards.length + 1).forEach(function (item, index) {
            var card = miniCards[index];
            var titleNode = card && card.querySelector("h3");
            var kickerNode = card && card.querySelector(".showcase-kicker");
            var imageNode = card && card.querySelector("img");
            var title = resolveContentTitle(item);
            var summary = resolveContentSummary(item);
            if (!card || !title) return;
            if (titleNode) titleNode.textContent = title;
            if (kickerNode && summary) kickerNode.textContent = summary;
            updateImageNode(imageNode, resolveContentImage(item, "product", index + 1), title);
            bindNavigableCard(card, resolvePublicHref(item, "/products"), title);
        });
    }

    /* ───────────────────────── hydrateCasesBoard ───────────────────────── */
    function hydrateCasesBoard(items) {
        if (!R.featuredCasesBoard) return;
        var caseItems = items.filter(function (item) {
            return String((item && item.content_type) || "").toLowerCase() === "case" || String((item && item.country_code) || "").trim() !== "";
        });
        if (!caseItems.length) return;
        var heroItem = caseItems[0];
        var heroCard = R.featuredCasesBoard.querySelector(".case-hero-card");
        var heroTitle = heroCard && heroCard.querySelector(".case-hero-title");
        var heroImage = heroCard && heroCard.querySelector("img");
        var heroFlags = heroCard && heroCard.querySelector(".case-hero-flags");
        var heroHeading = resolveContentTitle(heroItem);
        if (heroCard && heroHeading) {
            if (heroTitle) heroTitle.textContent = heroHeading;
            if (heroFlags && heroItem.country_code) {
                var code = String(heroItem.country_code || "").slice(0, 2).toLowerCase();
                heroFlags.innerHTML = '<img src="' + R.assetPath("assets/images/flags/" + R.escapeHtml(code) + ".svg") + '" alt="">';
            }
            updateImageNode(heroImage, resolveContentImage(heroItem, "case", 0), heroHeading);
            bindNavigableCard(heroCard, resolvePublicHref(heroItem, "/cases"), heroHeading);
        }
        var listCards = Array.from(R.featuredCasesBoard.querySelectorAll(".case-list-item"));
        caseItems.slice(1, listCards.length + 1).forEach(function (item, index) {
            var card = listCards[index];
            var titleNode = card && card.querySelector(".case-title-text");
            var flagsNode = card && card.querySelector(".case-title-flags");
            var imageNode = card && card.querySelector("img");
            var title = resolveContentTitle(item);
            if (!card || !title) return;
            if (titleNode) titleNode.textContent = title;
            if (flagsNode && item.country_code) {
                var code = String(item.country_code || "").slice(0, 2).toLowerCase();
                flagsNode.innerHTML = '<img src="' + R.assetPath("assets/images/flags/" + R.escapeHtml(code) + ".svg") + '" alt="">';
            }
            updateImageNode(imageNode, resolveContentImage(item, "case", index + 1), title);
            bindNavigableCard(card, resolvePublicHref(item, "/cases"), title);
        });
    }

    /* ───────────────────────── hydrateNewsGrid ───────────────────────── */
    function hydrateNewsGrid(items) {
        var cards = Array.from((R.featuredNewsGrid && R.featuredNewsGrid.querySelectorAll(".news-card")) || []);
        var newsItems = items.filter(function (item) { return String((item && item.content_type) || "news").toLowerCase() !== "case"; });
        newsItems.slice(0, cards.length).forEach(function (item, index) {
            var card = cards[index];
            var titleNode = card && card.querySelector("h3");
            var tagNode = card && card.querySelector(".news-card-tag");
            var imageNode = card && card.querySelector("img");
            var title = resolveContentTitle(item);
            var tagText = resolveContentSummary(item);
            if (!card || !title) return;
            if (titleNode) titleNode.textContent = title;
            if (tagNode && tagText) tagNode.textContent = tagText;
            updateImageNode(imageNode, resolveContentImage(item, "news", index), title);
            bindNavigableCard(card, resolvePublicHref(item, "/news"), title);
        });
    }

    /* ───────────────────────── hydrateContactSlot / hydrateContactPanels ───────────────────────── */
    function hydrateContactSlot(card, items, fallbackHrefPrefix) {
        if (!card || !items.length) return;
        var first = items[0];
        var labelNode = card.querySelector("small");
        var strongNode = card.querySelector("strong");
        var hrefValue = String(first.value || "").trim();
        if (labelNode && first.label) labelNode.textContent = first.label;
        updateMultiValueNode(strongNode, items.map(function (it) { return String(it.value || "").trim(); }).filter(Boolean));
        if (card.tagName === "A" && hrefValue) {
            card.href = fallbackHrefPrefix ? fallbackHrefPrefix + hrefValue : hrefValue;
        }
    }

    function hydrateContactPanels(contact) {
        var allItems = Array.isArray(contact && contact.items) ? contact.items : [];
        var currentLang = String((body && body.dataset && body.dataset.lang) || "zh").slice(0, 2).toLowerCase();
        var priorityKeys = R.getRegionalContactKeys(currentLang);
        var filteredItems = allItems.filter(function (item) {
            var fieldKey = String(item.field_key || "").toLowerCase();
            return priorityKeys.indexOf(fieldKey) >= 0;
        });
        if (!filteredItems.length) filteredItems = allItems;

        if (R.contactGrid) {
            var contactCards = Array.from(R.contactGrid.querySelectorAll(".contact-card"));
            var pageItems = filteredItems.filter(function (item) {
                return ["contact_page", "footer", "global", ""].indexOf(String(item.display_scope || "")) >= 0;
            });
            hydrateContactSlot(contactCards[0], pageItems.filter(function (item) { return String(item.field_key) === "email"; }), "mailto:");
            hydrateContactSlot(contactCards[1], pageItems.filter(function (item) { return String(item.field_key) === "phone"; }), "tel:");
        }

        if (R.footerContactList) {
            var footerCards = Array.from(R.footerContactList.querySelectorAll(".footer-brand-contact"));
            var footerItems = filteredItems.filter(function (item) {
                return ["footer", "contact_page", "global", ""].indexOf(String(item.display_scope || "")) >= 0;
            });
            var messageItems = footerItems.filter(function (item) { return ["email", "phone"].indexOf(String(item.field_key)) < 0; });
            hydrateContactSlot(footerCards[0], footerItems.filter(function (item) { return String(item.field_key) === "email"; }), "mailto:");
            hydrateContactSlot(footerCards[1], footerItems.filter(function (item) { return String(item.field_key) === "phone"; }), "tel:");
            if (footerCards[2] && messageItems.length) {
                var card = footerCards[2];
                var item = messageItems[0];
                var labelNode = card.querySelector("small");
                var strongNode = card.querySelector("strong");
                var fieldKey = String(item.field_key || "").trim().toLowerCase();
                var rawValue = String(item.value || "").trim();
                var digitsOnly = rawValue.replace(/\D/g, "");
                if (labelNode) labelNode.textContent = item.label || item.field_name || item.field_key || "Contact";
                if (strongNode) strongNode.textContent = rawValue;
                if (card.tagName === "A") {
                    if (fieldKey === "whatsapp" && digitsOnly) card.href = "https://wa.me/" + digitsOnly;
                    else card.href = rawValue || "#";
                }
            }
        }
    }

    /* ───────────────────────── hydrateFooterFeaturedColumn ───────────────────────── */
    function hydrateFooterFeaturedColumn(host, heading, items, fallbackHref) {
        if (!host || !items.length) return;
        host.innerHTML = "<h3>" + R.escapeHtml(heading) + "</h3>" +
            items.slice(0, 6).map(function (item) {
                return '<a href="' + R.escapeHtml(resolvePublicHref(item, fallbackHref)) + '">' + R.escapeHtml(resolveContentTitle(item)) + '</a>';
            }).join("");
    }

    /* ───────────────────────── hydratePublicSite（主入口） ───────────────────────── */
    async function hydratePublicSite(requestedCode) {
        if (isStaticGeneratedPublicPage) {
            var staticRequestedCode = requestedCode || (body && body.dataset.lang) || "zh";
            if (typeof R.applyLanguage === "function") {
                R.applyLanguage(staticRequestedCode, {
                    persistMode: R.hasExplicitPublicLanguageChoice() ? "manual" : "auto",
                });
            }
            try {
                var payload = await R.getPublicApi("/api/site/bootstrap?lang=" + encodeURIComponent(R.toApiLanguageCode(staticRequestedCode)));
                if (payload && typeof payload === "object") applyRuntimePayload(payload);
            } catch (e) {
                console.warn("Failed to hydrate static runtime payload.", e);
            }
            if (typeof R.populateSalesContactMenus === "function") R.populateSalesContactMenus();
            else populateSalesContactMenusLocal();
            R.initProgressiveMedia();
            setHydratingState(false);
            return;
        }

        setHydratingState(true);
        var FOUC_TIMEOUT = setTimeout(function () { setHydratingState(false); }, 3000);
        var requestVersion = (R.publicBootstrapRequestVersion() || 0) + 1;
        R.publicBootstrapRequestVersion(requestVersion);

        try {
            var result = await R.getPublicApi("/api/site/bootstrap?lang=" + encodeURIComponent(R.toApiLanguageCode(requestedCode || (body && body.dataset.lang) || "zh")));
            if (requestVersion !== R.publicBootstrapRequestVersion()) return;

            var payload = (result && result.data) || {};
            var homepage = payload.homepage || {};
            var featuredProducts = (homepageSectionByKey(homepage, "featured_products") || {}).items || [];
            var featuredSolutions = (homepageSectionByKey(homepage, "featured_solutions") || {}).items || [];
            var featuredCases = (homepageSectionByKey(homepage, "customer_cases") || {}).items
                || (homepageSectionByKey(homepage, "featured_cases") || {}).items
                || (homepageSectionByKey(homepage, "case_list") || {}).items
                || [];
            var featuredNews = (homepageSectionByKey(homepage, "company_news") || {}).items
                || (homepageSectionByKey(homepage, "news_list") || {}).items
                || [];
            var resolvedCode = (result && result.meta && result.meta.language && result.meta.language.resolved_code) || R.toApiLanguageCode(requestedCode || "zh");
            var requestedNormalized = R.normalizedLang(requestedCode || (body && body.dataset.lang) || "zh");
            var resolvedNormalized = R.normalizedLang(resolvedCode);

            applyRuntimePayload(payload);
            hydrateSiteConfig(payload.site);

            var siteTitle = String((payload.site && payload.site.site_title) || "").trim();
            var siteMetaDesc = String((payload.site && payload.site.meta_description) || "").trim();
            if (siteTitle) document.title = siteTitle;
            if (siteMetaDesc) {
                var metaDesc = document.querySelector("#meta-description");
                if (metaDesc) metaDesc.setAttribute("content", siteMetaDesc);
                R.setMetaContent("og:description", siteMetaDesc);
                R.setMetaContent("og:site_name", String((payload.site && payload.site.site_name) || "HANZUN").trim());
                R.setMetaContent("og:type", "website");
                if (siteTitle) R.setMetaContent("og:title", siteTitle);
            }
            var preferredCode = R.normalizedLang(R.resolvePreferredRuntimeLanguage(payload.site, requestedCode || resolvedCode));
            if (preferredCode !== requestedNormalized && preferredCode !== resolvedNormalized) {
                hydratePublicSite(preferredCode);
                return;
            }

            R.applyLanguage(resolvedCode, { persistMode: R.hasExplicitPublicLanguageChoice() ? "manual" : "auto" });
            if (typeof R.loadNavigation === "function") R.loadNavigation(resolvedCode);
            hydrateHeroSection(homepage);
            hydrateSolutionsGrid(featuredSolutions);
            hydrateProductsShowcase(featuredProducts);
            hydrateCasesBoard(featuredCases);
            hydrateNewsGrid(featuredNews);
            hydrateContactPanels(payload.contact);
            hydrateFooterFeaturedColumn(R.footerFeaturedProducts, R.getLocalizedRuntimeCopy("热门产品", "Popular Products"), featuredProducts, "/products");
            hydrateFooterFeaturedColumn(R.footerFeaturedSolutions, R.getLocalizedRuntimeCopy("热门生产线", "Popular Production Lines"), featuredSolutions, "/solutions");
            R.initProgressiveMedia();
            populateSalesContactMenusLocal();

            // T1: Hero image
            var heroImg = document.querySelector(".hero-image");
            if (heroImg && payload.site && payload.site.hero_image_url) {
                heroImg.src = payload.site.hero_image_url;
                heroImg.alt = (payload.site && payload.site.hero_image_alt) || heroImg.alt;
            }

            // T1: Notice banner
            var noticeSection = document.querySelector("#notice-banner");
            if (noticeSection) {
                var nImg = noticeSection.querySelector("img");
                var nTitle = noticeSection.querySelector(".notice-banner-copy span");
                var nContent = noticeSection.querySelector(".notice-banner-copy strong");
                if (nImg && payload.site && payload.site.notice_image_url) nImg.src = payload.site.notice_image_url;
                if (nTitle && payload.site && payload.site.notice_title) nTitle.textContent = payload.site.notice_title;
                if (nContent && payload.site && payload.site.notice_content) nContent.textContent = payload.site.notice_content;
            }

            // T2: Factory video
            var video = document.querySelector("[data-progressive-video]");
            if (video && payload.site && payload.site.enterprise_video_url) {
                video.setAttribute("data-src", payload.site.enterprise_video_url);
                if (video.classList.contains("is-loaded")) video.src = payload.site.enterprise_video_url;
            }

            // T3: Homepage certificates
            if (typeof R.hydrateCertificatesGrid === "function") R.hydrateCertificatesGrid(payload.certificates || []);

            // T4: Homepage team
            if (typeof R.hydrateTeamStrip === "function") R.hydrateTeamStrip(payload.team_members || []);

            // T6: hreflang tags
            if (Array.isArray(payload.languages) && payload.languages.length) {
                document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(function (el) { el.remove(); });
                var currentPath = window.location.pathname.replace(/^\/[a-z]{2}(?=\/)/i, "");
                var head = document.head;
                payload.languages.forEach(function (lang) {
                    if (!lang.is_enabled) return;
                    var code = String(lang.code || "").slice(0, 2).toLowerCase();
                    var link = document.createElement("link");
                    link.rel = "alternate";
                    link.hreflang = code;
                    link.href = window.location.origin + "/" + code + (currentPath || "/");
                    head.appendChild(link);
                });
                var defLink = document.createElement("link");
                defLink.rel = "alternate";
                defLink.hreflang = "x-default";
                defLink.href = window.location.origin + "/zh" + (currentPath || "/");
                head.appendChild(defLink);
            }
        } catch (e) {
            return;
        } finally {
            clearTimeout(FOUC_TIMEOUT);
            setHydratingState(false);
        }
    }
    R.hydratePublicSite = hydratePublicSite;

    /* ───────────────────────── populateSalesContactMenus 本地版（兜底） ───────────────────────── */
    function populateSalesContactMenusLocal() {
        var contacts = Array.from(document.querySelectorAll(".sales-card")).map(function (card) {
            var nameNode = card.querySelector(".sales-name-bar strong");
            var name = (nameNode && nameNode.textContent.trim()) || "";
            var links = Array.from(card.querySelectorAll('a[href^="mailto:"], a[href^="tel:"]'));
            var emailLink = links.find(function (l) { return (l.getAttribute("href") || "").indexOf("mailto:") === 0; });
            var phoneLink = links.find(function (l) { return (l.getAttribute("href") || "").indexOf("tel:") === 0; });
            var emailHref = (emailLink && emailLink.getAttribute("href")) || "";
            var phoneHref = (phoneLink && phoneLink.getAttribute("href")) || "";
            return {
                name: name,
                email: emailHref.replace(/^mailto:/i, "").trim(),
                emailHref: emailHref,
                phone: phoneHref.replace(/^tel:/i, "").trim(),
                phoneHref: phoneHref,
            };
        }).filter(function (c) { return c.name && (c.email || c.phone); });

        if (!contacts.length) return;
        document.querySelectorAll("[data-contact-list]").forEach(function (menu) {
            if (menu.dataset.staticContactMenu === "1" || menu.querySelector("a")) return;
            var type = menu.dataset.contactList;
            var key = type === "phone" ? "phone" : "email";
            var hrefKey = key === "phone" ? "phoneHref" : "emailHref";
            var nextMarkup = contacts
                .filter(function (c) { return c[key] && c[hrefKey]; })
                .map(function (c) {
                    return '<a class="float-option float-option-inline" href="' + R.escapeHtml(c[hrefKey]) + '"><strong>' + R.escapeHtml(c.name + ": " + c[key]) + '</strong></a>';
                })
                .join("");
            if (nextMarkup) menu.innerHTML = nextMarkup;
        });
    }
    R.populateSalesContactMenus = populateSalesContactMenusLocal;

    /* ───────────────────────── trackStaticDetailPageView（详情页埋点） ───────────────────────── */
    function resolveStaticDetailPageMeta() {
        var pathname = String(window.location.pathname || "").replace(/\\/g, "/");
        var decodedPath;
        try { decodedPath = decodeURIComponent(pathname); } catch (e) { decodedPath = pathname; }
        var normalizedPath = decodedPath.replace(/^\/[a-z]{2}(?=\/)/i, "").replace(/^\/+/, "");
        var match = normalizedPath.match(/^(products|solutions|news|cases)\/([^/?#]+?)(?:\.html)?$/i);
        if (!match) return null;
        var section = String(match[1] || "").toLowerCase();
        var slug = String(match[2] || "").trim();
        if (!slug) return null;
        var configMap = {
            products: { entityType: "product", endpoint: "/api/site/products/" },
            solutions: { entityType: "solution", endpoint: "/api/site/solutions/" },
            news: { entityType: "news", endpoint: "/api/site/news/" },
            cases: { entityType: "case", endpoint: "/api/site/cases/" },
        };
        return configMap[section] ? { entityType: configMap[section].entityType, endpoint: configMap[section].endpoint, slug: slug } : null;
    }

    async function trackStaticDetailPageView() {
        if (R.staticDetailViewTracked()) return;
        var detailMeta = resolveStaticDetailPageMeta();
        if (!detailMeta) return;
        R.staticDetailViewTracked(true);

        var detail = {};
        try {
            var languageCode = encodeURIComponent(R.toApiLanguageCode((body && body.dataset.lang) || "zh"));
            var detailResponse = await R.getPublicApi(detailMeta.endpoint + encodeURIComponent(detailMeta.slug) + "?lang=" + languageCode);
            if (detailResponse && typeof detailResponse === "object") detail = detailResponse.data || detailResponse || {};
        } catch (e) {
            // 保留 detail 为空，继续走 slug 兜底埋点
        }

        var fallbackVisitorCode = R.ensureSupportClientId();
        var entityType = detailMeta.entityType === "news" || detailMeta.entityType === "case"
            ? String((detail && detail.content_type) || detailMeta.entityType).trim().toLowerCase()
            : detailMeta.entityType;
        var entityId = Number((detail && detail.id) || 0);
        var pagePath = R.currentSupportPath();
        var supportLanguage = R.currentSupportLanguage();
        var visitorCode = R.currentSupportSessionCode() || fallbackVisitorCode;

        var payload = {
            visitor_code: visitorCode,
            client_id: fallbackVisitorCode,
            entity_type: entityType,
            entity_id: Number.isFinite(entityId) ? entityId : 0,
            slug: detailMeta.slug,
            page: pagePath,
            source_page: pagePath,
            language_code: supportLanguage,
            title: String((detail && detail.title) || (detail && detail.name) || ""),
        };

        var sendWithBeacon = function () {
            try {
                var pageviewApiUrl = R.publicApiUrl("/api/site/pageview");
                if (typeof navigator.sendBeacon !== "function" || !pageviewApiUrl) return false;
                var blob = new Blob([JSON.stringify(payload)], { type: "application/json" });
                return navigator.sendBeacon(pageviewApiUrl, blob);
            } catch (e) { return false; }
        };

        try {
            await R.postPublicApi("/api/site/pageview", payload, true);
        } catch (e) {
            if (sendWithBeacon()) return;
            R.staticDetailViewTracked(false);
        }
    }

    /* ───────────────────────── 启动 ───────────────────────── */
    var initialLanguage = R.resolveInitialPublicLanguage();
    if (isStaticGeneratedPublicPage) {
        // 静态生成的页面（首页/列表页/详情页/单页）都触发 hydratePublicSite
        // 它内部会根据页面是否首页（featuredSolutionsGrid 等存在与否）决定是否跑首页专属水合
        hydratePublicSite(initialLanguage);
    }
    void trackStaticDetailPageView();
})();
