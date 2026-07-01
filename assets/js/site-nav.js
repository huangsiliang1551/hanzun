/**
 * site-nav.js — 导航菜单模块
 *
 * 所有页面加载。负责：
 *   - 顶部汉堡菜单开关（menuToggle / menu）
 *   - 语言下拉（dropdown / langMenu / applyLanguage）
 *   - 产品 mega nav（hover / click / 移动端手风琴）
 *   - 通用 mega nav（products/solutions/news/cases）
 *   - nav-dropdown 子菜单
 *   - loadNavigation（从 API 拉菜单数据）
 *   - renderLanguageMenu / ensureLanguageMenuRendered
 *   - index.template.html 原内联菜单脚本（mega-panel 显隐 + 手风琴 + 卡片头）
 *
 * 依赖 site-runtime.js，通过 window.HanzunRuntime 调用公共 API。
 */
(function () {
    "use strict";
    var R = window.HanzunRuntime;
    if (!R) return;

    var body = R.body;
    var html = R.html;
    var menuToggle = R.menuToggle;
    var menu = R.menu;
    var dropdown = R.dropdown;
    var dropdownTrigger = R.dropdownTrigger;
    var dropdownLabel = R.dropdownLabel;
    var dropdownFlag = R.dropdownFlag;
    var langMenu = R.langMenu;
    var productNav = R.productNav;
    var productTrigger = R.productTrigger;
    var productPanel = R.productPanel;
    var navDropdownItems = R.navDropdownItems;
    var productTabs = R.productTabs;
    var megaNavItems = R.megaNavItems;
    var productViews = R.productViews;
    var productBranches = R.productBranches;
    var mobileProductAccordion = R.mobileProductAccordion;
    var mobileFabMedia = R.mobileFabMedia;
    var isStaticGeneratedPublicPage = R.isStaticGeneratedPublicPage;

    /* ───────────────────────── 移动端产品手风琴 ───────────────────────── */
    if (mobileProductAccordion) {
        mobileProductAccordion.className = "mobile-product-accordion";
        mobileProductAccordion.hidden = true;
        if (productPanel) productPanel.appendChild(mobileProductAccordion);
    }

    /* ───────────────────────── 语言菜单 ───────────────────────── */
    function renderLanguageMenu() {
        if (!langMenu) return;
        var activeLang = R.normalizedLang((body && body.dataset.lang) || "zh");
        var languages = R.languages();
        langMenu.innerHTML =
            '<div class="lang-group-grid">' +
            languages.map(function (lang) {
                var activeAttr = lang.code === activeLang ? ' class="active"' : "";
                return '<button type="button" data-lang-option="' + R.escapeHtml(lang.code) + '"' + activeAttr + '>' +
                    '<span class="lang-option-flag" aria-hidden="true">' + R.getFlagBadgeSvg(lang) + '</span>' +
                    '<span class="lang-option-name"><strong>' + R.escapeHtml(R.getLanguageLabel(lang.code)) + '</strong></span>' +
                    '</button>';
            }).join("") +
            '</div>';
    }
    R.renderLanguageMenu = renderLanguageMenu;

    function ensureLanguageMenuRendered() {
        if (R.languageMenuRendered()) return;
        renderLanguageMenu();
        R.languageMenuRendered(true);
    }
    R.ensureLanguageMenuRendered = ensureLanguageMenuRendered;

    /* ───────────────────────── applyLanguage ───────────────────────── */
    function applyLanguage(code, options) {
        options = options || {};
        var mapped = R.normalizedLang(code);
        var contentLang = R.getContentLanguage(mapped);
        var langInfo = R.getLanguageMap().get(mapped) || R.getLanguageMap().get("zh");
        var publicSiteConfig = R.publicSiteConfig();
        var siteTitle = String((publicSiteConfig && publicSiteConfig.site_title) || "").trim();
        var siteDescription = String((publicSiteConfig && publicSiteConfig.meta_description) || "").trim();
        var persistMode = String(options.persistMode || "auto").trim() || "auto";
        var pageMeta = {
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

        document.querySelectorAll("[data-zh]").forEach(function (node) {
            node.textContent = node.dataset[contentLang] || node.dataset.zh;
        });
        document.querySelectorAll("[data-zh-placeholder]").forEach(function (node) {
            var placeholder = node.dataset[contentLang + "Placeholder"] || node.dataset.zhPlaceholder || "";
            node.setAttribute("placeholder", placeholder);
        });
        document.querySelectorAll("[data-zh-prompt]").forEach(function (node) {
            node.dataset.supportPrompt = node.dataset[contentLang + "Prompt"] || node.dataset.zhPrompt || "";
        });

        if (dropdownLabel) dropdownLabel.textContent = R.getLanguageLabel(mapped);
        if (dropdownFlag) {
            dropdownFlag.innerHTML = R.getFlagBadgeSvg(mapped);
            dropdownFlag.setAttribute("title", langInfo.country);
        }

        if (langMenu) {
            langMenu.querySelectorAll("[data-lang-option]").forEach(function (button) {
                button.classList.toggle("active", button.dataset.langOption === mapped);
            });
        }

        if (!isStaticGeneratedPublicPage || siteTitle) {
            document.title = siteTitle || pageMeta[contentLang].title;
        }
        if (R.metaDescription && (!isStaticGeneratedPublicPage || siteDescription)) {
            R.metaDescription.setAttribute("content", siteDescription || pageMeta[contentLang].description);
        }

        if (persistMode === "manual" || persistMode === "auto") {
            R.safeStorageSet("hanzun-lang", mapped);
            R.safeStorageSet("hanzun-lang-source", persistMode);
        }

        if (R.languageMenuRendered()) renderLanguageMenu();
        renderMobileProductAccordion();
        if (typeof R.syncSupportDefaultMessagesMarkup === "function") R.syncSupportDefaultMessagesMarkup();
    }
    R.applyLanguage = applyLanguage;

    /* ───────────────────────── 移动端产品手风琴渲染 ───────────────────────── */
    function isMobileProductAccordion() {
        return Boolean(mobileFabMedia && mobileFabMedia.matches);
    }
    R.isMobileProductAccordion = isMobileProductAccordion;

    function renderMobileProductAccordion() {
        if (!mobileProductAccordion || !productTabs.length || !productViews.length) return;
        var sections = Array.from(productTabs).map(function (tab, index) {
            var tabName = tab.dataset.productTab;
            var view = Array.from(productViews).find(function (item) { return item.dataset.productView === tabName; });
            if (!view) return "";

            var label = (tab.querySelector("strong") && tab.querySelector("strong").textContent.trim()) || tab.textContent.trim();
            var note = (tab.querySelector("small") && tab.querySelector("small").textContent.trim()) || "";
            var branches = Array.from(view.querySelectorAll(".nav-tree-branch")).map(function (branch) {
                var trigger = branch.querySelector(".nav-tree-branch-title");
                var links = Array.from(branch.querySelectorAll(".nav-tree-leaf-list a")).map(function (link) {
                    return '<a href="' + (link.getAttribute("href") || "#") + '">' + link.textContent.trim() + '</a>';
                }).join("");
                return '<article class="mobile-product-branch">' +
                    '<button class="mobile-product-branch-trigger" type="button" aria-expanded="false">' +
                    '<span>' + ((trigger && trigger.textContent.trim()) || "") + '</span></button>' +
                    '<div class="mobile-product-links" hidden>' + links + '</div></article>';
            }).join("");

            return '<section class="mobile-product-section">' +
                '<button class="mobile-product-section-trigger" type="button" aria-expanded="false">' +
                '<span class="mobile-product-section-copy"><strong>' + label + '</strong><small>' + note + '</small></span>' +
                '</button><div class="mobile-product-section-body" hidden>' + branches + '</div></section>';
        }).join("");

        mobileProductAccordion.innerHTML = sections;
        mobileProductAccordion.hidden = !isMobileProductAccordion();
    }

    /* ───────────────────────── 产品菜单状态 ───────────────────────── */
    function setProductBranch(branch, open) {
        var trigger = branch && branch.querySelector(".nav-tree-branch-title");
        var leafList = branch && branch.querySelector(".nav-tree-leaf-list");
        if (!branch || !trigger || !leafList) return;
        branch.classList.toggle("open", open);
        trigger.setAttribute("aria-expanded", String(open));
        leafList.hidden = !open;
        leafList.style.display = open ? "grid" : "none";
    }

    function resetProductBranch(branch) {
        var trigger = branch && branch.querySelector(".nav-tree-branch-title");
        var leafList = branch && branch.querySelector(".nav-tree-leaf-list");
        if (!branch || !trigger || !leafList) return;
        branch.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
        leafList.hidden = false;
        leafList.style.display = "";
    }

    function collapseProductBranches(scope) {
        if (!scope) return;
        scope.querySelectorAll(".nav-tree-branch").forEach(function (branch) { setProductBranch(branch, false); });
    }

    function syncProductBranches(tabName) {
        if (!isMobileProductAccordion()) {
            productViews.forEach(function (view) {
                view.querySelectorAll(".nav-tree-branch").forEach(function (branch) { resetProductBranch(branch); });
            });
            return;
        }
        productViews.forEach(function (view) {
            var isActive = view.dataset.productView === tabName;
            collapseProductBranches(view);
            view.querySelectorAll(".nav-tree-branch-title").forEach(function (trigger) {
                trigger.setAttribute("aria-expanded", "false");
            });
        });
    }
    R.syncProductBranches = syncProductBranches;

    function getActiveProductTabName() {
        var activeTab = Array.from(productTabs).find(function (tab) { return tab.classList.contains("is-active"); });
        return (activeTab && activeTab.dataset && activeTab.dataset.productTab) || "factory";
    }
    R.getActiveProductTabName = getActiveProductTabName;

    function setProductTab(tabName) {
        if (!productTabs.length || !productViews.length) return;
        productTabs.forEach(function (tab) {
            var isActive = tab.dataset.productTab === tabName;
            tab.classList.toggle("is-active", isActive);
            tab.setAttribute("aria-selected", String(isActive));
        });
        productViews.forEach(function (view) {
            view.classList.toggle("is-active", view.dataset.productView === tabName);
        });
        syncProductBranches(tabName);
    }
    R.setProductTab = setProductTab;

    /* ───────────────────────── 旧版 product menu / mega nav ───────────────────────── */
    function closeProductMenu() {
        if (productNav) {
            productNav.classList.remove("open");
            if (productTrigger) productTrigger.setAttribute("aria-expanded", "false");
        }
        megaNavItems.forEach(function (item) { closeMegaNav(item); });
    }
    R.closeProductMenu = closeProductMenu;

    function openProductMenu() {
        if (productNav) {
            productNav.classList.add("open");
            if (productTrigger) productTrigger.setAttribute("aria-expanded", "true");
        }
        syncProductBranches(getActiveProductTabName());
    }
    R.openProductMenu = openProductMenu;

    function clearProductHoverCloseTimer() {
        var t = R.productHoverCloseTimer();
        if (t) { window.clearTimeout(t); R.productHoverCloseTimer(null); }
    }
    function scheduleProductMenuClose() {
        clearProductHoverCloseTimer();
        var t = window.setTimeout(function () { closeProductMenu(); R.productHoverCloseTimer(null); }, 320);
        R.productHoverCloseTimer(t);
    }

    function closeMegaNav(megaItem) {
        if (!megaItem) return;
        megaItem.classList.remove("open");
        var trigger = megaItem.querySelector("[data-mega-trigger]");
        if (trigger) trigger.setAttribute("aria-expanded", "false");
    }
    function openMegaNav(megaItem) {
        if (!megaItem) return;
        megaItem.classList.add("open");
        var trigger = megaItem.querySelector("[data-mega-trigger]");
        if (trigger) trigger.setAttribute("aria-expanded", "true");
    }
    function closeAllMegaNavs(except) {
        megaNavItems.forEach(function (item) { if (item !== except) closeMegaNav(item); });
    }

    var megaHoverTimers = new Map();
    function clearMegaHoverTimer(item) {
        var t = megaHoverTimers.get(item);
        if (t) { window.clearTimeout(t); megaHoverTimers.delete(item); }
    }
    function scheduleMegaClose(item) {
        clearMegaHoverTimer(item);
        var t = window.setTimeout(function () { closeMegaNav(item); megaHoverTimers.delete(item); }, 320);
        megaHoverTimers.set(item, t);
    }

    /* ───────────────────────── nav-dropdown 子菜单 ───────────────────────── */
    function setNavDropdownState(item, open) {
        if (!item) return;
        var trigger = item.querySelector("[data-nav-dropdown-trigger]");
        item.classList.toggle("open", open);
        if (trigger) trigger.setAttribute("aria-expanded", String(open));
    }
    function clearNavDropdownCloseTimer(item) {
        var t = R.navHoverCloseTimers.get(item);
        if (t) { window.clearTimeout(t); R.navHoverCloseTimers.delete(item); }
    }
    function scheduleNavDropdownClose(item) {
        clearNavDropdownCloseTimer(item);
        var t = window.setTimeout(function () {
            setNavDropdownState(item, false);
            R.navHoverCloseTimers.delete(item);
        }, 180);
        R.navHoverCloseTimers.set(item, t);
    }
    function closeNavDropdowns(exceptItem) {
        if (!navDropdownItems.length) return;
        navDropdownItems.forEach(function (item) {
            if (item === exceptItem) return;
            setNavDropdownState(item, false);
        });
    }

    function closeDropdown() {
        if (!dropdown || !dropdownTrigger) return;
        dropdown.classList.remove("open");
        dropdownTrigger.setAttribute("aria-expanded", "false");
    }
    R.closeDropdown = closeDropdown;

    /* ───────────────────────── setMenuState ───────────────────────── */
    function setMenuState(open) {
        if (!menu || !menuToggle) return;
        menu.classList.toggle("open", open);
        menuToggle.setAttribute("aria-expanded", String(open));
        body.classList.toggle("menu-open", open);
        if (!open) {
            closeProductMenu();
            closeNavDropdowns();
            closeDropdown();
        }
    }
    R.setMenuState = setMenuState;

    /* ───────────────────────── 导航菜单数据水合 ───────────────────────── */
    function buildNavBranchMarkup(item) {
        var children = Array.isArray(item && item.children) ? item.children : [];
        return '<article class="nav-tree-branch">' +
            '<a class="nav-tree-branch-title" href="' + R.escapeHtml(resolvePublicHref(item, "/products")) + '">' + R.escapeHtml(resolveContentTitle(item)) + '</a>' +
            '<div class="nav-tree-leaf-list">' +
            children.map(function (child) {
                return '<a href="' + R.escapeHtml(resolvePublicHref(child, "/products")) + '">' + R.escapeHtml(resolveContentTitle(child)) + '</a>';
            }).join("") +
            '</div></article>';
    }

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
    function resolveContentImage(item, type, index) {
        var candidates = [(item && item.cover_asset_url), (item && item.cover_asset && item.cover_asset.public_url), (item && item.cover_image_url)];
        for (var i = 0; i < candidates.length; i++) {
            var value = String(candidates[i] || "").trim();
            if (value) return R.assetPath(value);
        }
        return resolveFallbackImage(type, index);
    }
    function resolveFallbackImage(type, index) {
        var pool = R.homepageFallbackImages[type] || R.homepageFallbackImages.news;
        return R.assetPath(pool[index % pool.length]);
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

    function hydrateNavigationMenus(menus) {
        var headerMenu = Array.isArray(menus) ? menus.find(function (m) { return String((m && m.menu_position) || "") === "header"; }) : null;
        if (!headerMenu || !Array.isArray(headerMenu.items)) return;

        var productMenuItem = headerMenu.items.find(function (item) { return String((item && (item.code || item.route_key)) || "").indexOf("product") >= 0; });
        var solutionMenuItem = headerMenu.items.find(function (item) { return String((item && (item.code || item.route_key)) || "").indexOf("solution") >= 0; });
        var aboutMenuItem = headerMenu.items.find(function (item) { return String((item && (item.code || item.route_key)) || "").indexOf("about") >= 0; });

        if (productMenuItem) {
            var productDirectLink = document.querySelector("[data-product-nav] .nav-link-direct");
            if (productDirectLink) {
                productDirectLink.textContent = productMenuItem.name || productMenuItem.name_zh || productDirectLink.textContent;
                productDirectLink.href = resolvePublicHref(productMenuItem, "/products");
            }
            if (Array.isArray(productMenuItem.children) && productMenuItem.children.length && productViews.length) {
                productTabs.forEach(function (tab, index) {
                    tab.hidden = index > 0;
                    tab.classList.toggle("is-active", index === 0);
                    tab.setAttribute("aria-selected", String(index === 0));
                    if (index === 0) {
                        var titleNode = tab.querySelector("strong");
                        var noteNode = tab.querySelector("small");
                        if (titleNode) titleNode.textContent = productMenuItem.name || productMenuItem.name_zh || titleNode.textContent;
                        if (noteNode) noteNode.textContent = R.getLocalizedRuntimeCopy("按分类快速浏览", "Browse by CMS categories");
                    }
                });
                productViews.forEach(function (view, index) {
                    view.hidden = index > 0;
                    view.classList.toggle("is-active", index === 0);
                    if (index === 0) {
                        view.innerHTML = '<div class="nav-tree-branch-grid">' + productMenuItem.children.map(buildNavBranchMarkup).join("") + '</div>';
                    }
                });
                renderMobileProductAccordion();
                setProductTab((productTabs[0] && productTabs[0].dataset.productTab) || "factory");
            }
        }

        if (solutionMenuItem) {
            var solutionDirectLink = document.querySelectorAll(".nav-item-submenu .nav-link-direct")[0];
            var solutionPanel = document.querySelectorAll("[data-nav-dropdown-panel]")[0];
            if (solutionDirectLink) {
                solutionDirectLink.textContent = solutionMenuItem.name || solutionMenuItem.name_zh || solutionDirectLink.textContent;
                solutionDirectLink.href = resolvePublicHref(solutionMenuItem, "/solutions");
            }
            if (solutionPanel && Array.isArray(solutionMenuItem.children) && solutionMenuItem.children.length) {
                solutionPanel.innerHTML = '<div class="nav-submenu-list">' +
                    solutionMenuItem.children.map(function (item) {
                        return '<a href="' + R.escapeHtml(resolvePublicHref(item, "/solutions")) + '">' + R.escapeHtml(resolveContentTitle(item)) + '</a>';
                    }).join("") +
                    '</div>';
            }
        }

        if (aboutMenuItem) {
            var aboutLink = document.querySelector('.site-nav > a[href$="/about.html"], .site-nav > a[href="about.html"]');
            if (aboutLink) {
                aboutLink.textContent = aboutMenuItem.name || aboutMenuItem.name_zh || aboutLink.textContent;
                aboutLink.href = resolvePublicHref(aboutMenuItem, "/about");
            }
        }
    }

    function renderNavigationFromApi(items) {
        var lang = (body && body.dataset.lang) || "zh";
        var isEn = R.getContentLanguage(R.normalizedLang(lang)) === "en";

        function resolveNavTitle(item) {
            return String(item.display_title || item.name || item.title || item.name_zh || item.title_zh || "").trim();
        }
        function resolveNavHref(item, fallback) {
            if (item.type === "custom_url") return String(item.url || item.route_path || "").trim() || fallback;
            return resolvePublicHref(item, fallback);
        }
        function renderLeaf(item, isChild) {
            var title = resolveNavTitle(item);
            var href = resolveNavHref(item, isChild ? "#" : "index.html");
            return '<a href="' + R.escapeHtml(href) + '" data-zh="' + R.escapeHtml(item.name_zh || title) + '" data-en="' + R.escapeHtml(item.name || title) + '">' + R.escapeHtml(title) + '</a>';
        }
        function renderDropdown(item) {
            var title = resolveNavTitle(item);
            var children = Array.isArray(item.children) ? item.children : [];
            var href = resolveNavHref(item, "#");
            return '<div class="nav-item nav-item-submenu" data-nav-dropdown>' +
                '<div class="nav-link-split">' +
                '<a class="nav-link-direct" href="' + R.escapeHtml(href) + '" data-zh="' + R.escapeHtml(item.name_zh || title) + '" data-en="' + R.escapeHtml(item.name || title) + '">' + R.escapeHtml(title) + '</a>' +
                '<button class="nav-link-button nav-link-toggle" type="button" aria-expanded="false" aria-label="' + (isEn ? 'Toggle submenu' : '切换子菜单') + '">' +
                '<span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span>' +
                '</button></div>' +
                '<div class="nav-dropdown-panel" data-nav-dropdown-panel><div class="nav-submenu-list">' +
                children.map(function (child) { return renderLeaf(child, true); }).join("") +
                '</div></div></div>';
        }
        function renderMega(item) {
            var title = resolveNavTitle(item);
            var children = Array.isArray(item.children) ? item.children : [];
            var href = resolveNavHref(item, "#");
            return '<div class="nav-item nav-item-mega" data-product-nav>' +
                '<div class="nav-link-split">' +
                '<a class="nav-link-direct" href="' + R.escapeHtml(href) + '" data-zh="' + R.escapeHtml(item.name_zh || title) + '" data-en="' + R.escapeHtml(item.name || title) + '">' + R.escapeHtml(title) + '</a>' +
                '<button class="nav-link-button nav-link-toggle" type="button" data-product-trigger aria-expanded="false" aria-label="' + (isEn ? 'Toggle product catalog' : '切换产品分类') + '">' +
                '<span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span>' +
                '</button></div>' +
                '<div class="nav-mega-panel" data-product-panel><div class="nav-tree"><div class="nav-tree-views"><section class="nav-tree-view is-active"><div class="nav-tree-branch-grid">' +
                children.map(buildNavBranchMarkup).join("") +
                '</div></section></div></div></div></div>';
        }

        return items.map(function (item) {
            var type = String(item.type || "plain").trim();
            if (type === "dropdown" || type === "flyout") {
                var children = Array.isArray(item.children) ? item.children : [];
                if (children.length) return renderDropdown(item);
            }
            if (type === "mega" || type === "auto_category_tree") {
                var kids = Array.isArray(item.children) ? item.children : [];
                if (kids.length) return renderMega(item);
            }
            return renderLeaf(item, false);
        }).join("");
    }

    async function loadNavigation(requestedCode) {
        try {
            var siteNav = document.querySelector(".site-nav");
            if (siteNav && siteNav.dataset.staticNav === "1") return;
            var result = await R.getPublicApi("/api/site/navigation?lang=" + encodeURIComponent(R.toApiLanguageCode(requestedCode || "zh")));
            var headerMenu = Array.isArray(result && result.data)
                ? result.data.find(function (m) { return String((m && m.menu_position) || "") === "header"; })
                : null;
            if (!headerMenu || !Array.isArray(headerMenu.items) || !headerMenu.items.length) return;
            if (!siteNav) return;
            siteNav.innerHTML = renderNavigationFromApi(headerMenu.items);
        } catch (e) {
            // API 不可用，保留原静态菜单兜底
        }
    }

    /* ───────────────────────── 事件绑定：语言下拉 ───────────────────────── */
    if (dropdown && dropdownTrigger) {
        dropdownTrigger.addEventListener("click", function () {
            var open = dropdown.classList.toggle("open");
            dropdownTrigger.setAttribute("aria-expanded", String(open));
            if (open) {
                closeProductMenu();
                ensureLanguageMenuRendered();
                requestAnimationFrame(function () {
                    if (menu && mobileFabMedia && mobileFabMedia.matches) {
                        menu.scrollTo({ top: Math.max(dropdown.offsetTop - 8, 0), behavior: "smooth" });
                        return;
                    }
                    if (langMenu) langMenu.scrollIntoView({ block: "nearest", inline: "nearest", behavior: "smooth" });
                });
            }
        });
        document.addEventListener("click", function (event) {
            if (!dropdown.contains(event.target)) closeDropdown();
        });
    }

    /* ───────────────────────── 事件绑定：产品菜单 ───────────────────────── */
    if (productNav && productTrigger && productPanel) {
        var productSplit = productNav.querySelector(".nav-link-split");

        productTrigger.addEventListener("click", function (event) {
            event.preventDefault(); event.stopPropagation();
            var open = !productNav.classList.contains("open");
            if (open) { openProductMenu(); closeDropdown(); closeNavDropdowns(); return; }
            closeProductMenu();
        });

        if (productSplit) {
            productSplit.addEventListener("click", function (event) {
                if (!mobileFabMedia || !mobileFabMedia.matches) return;
                if (event.target.closest("[data-product-trigger]")) return;
                event.preventDefault(); event.stopPropagation();
                var open = !productNav.classList.contains("open");
                if (open) { openProductMenu(); closeDropdown(); closeNavDropdowns(); return; }
                closeProductMenu();
            });
        }

        productNav.addEventListener("mouseenter", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            clearProductHoverCloseTimer();
            openProductMenu(); closeDropdown(); closeNavDropdowns();
        });
        productNav.addEventListener("mouseleave", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            scheduleProductMenuClose();
        });
        productNav.addEventListener("focusin", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            clearProductHoverCloseTimer();
            openProductMenu(); closeDropdown(); closeNavDropdowns();
        });
        productNav.addEventListener("focusout", function (event) {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            if (productNav.contains(event.relatedTarget)) return;
            scheduleProductMenuClose();
        });

        productTabs.forEach(function (tab) {
            tab.addEventListener("click", function () { setProductTab(tab.dataset.productTab); });
        });

        productPanel.querySelectorAll(".nav-tree-root, .nav-tree-leaf-list a").forEach(function (link) {
            link.addEventListener("click", function () { closeProductMenu(); });
        });

        if (mobileProductAccordion) {
            mobileProductAccordion.addEventListener("click", function (event) {
                var sectionTrigger = event.target.closest(".mobile-product-section-trigger");
                var branchTrigger = event.target.closest(".mobile-product-branch-trigger");
                var productLink = event.target.closest(".mobile-product-links a");

                if (productLink) { closeProductMenu(); return; }

                if (branchTrigger) {
                    event.preventDefault();
                    var branch = branchTrigger.closest(".mobile-product-branch");
                    var section = branch && branch.closest(".mobile-product-section");
                    var links = branch && branch.querySelector(".mobile-product-links");
                    var nextOpen = !(branch && branch.classList.contains("is-open"));

                    if (section) {
                        section.querySelectorAll(".mobile-product-branch").forEach(function (item) {
                            item.classList.remove("is-open");
                            var t = item.querySelector(".mobile-product-branch-trigger");
                            if (t) t.setAttribute("aria-expanded", "false");
                            var il = item.querySelector(".mobile-product-links");
                            if (il) il.hidden = true;
                        });
                    }
                    if (branch && links && nextOpen) {
                        branch.classList.add("is-open");
                        branchTrigger.setAttribute("aria-expanded", "true");
                        links.hidden = false;
                    }
                    return;
                }

                if (sectionTrigger) {
                    event.preventDefault();
                    var section = sectionTrigger.closest(".mobile-product-section");
                    var sbody = section && section.querySelector(".mobile-product-section-body");
                    var sOpen = !(section && section.classList.contains("is-open"));
                    if (mobileProductAccordion) {
                        mobileProductAccordion.querySelectorAll(".mobile-product-section").forEach(function (item) {
                            item.classList.remove("is-open");
                            var t = item.querySelector(".mobile-product-section-trigger");
                            if (t) t.setAttribute("aria-expanded", "false");
                            var ib = item.querySelector(".mobile-product-section-body");
                            if (ib) { ib.hidden = true; ib.style.display = "none"; }
                        });
                    }
                    if (section && sbody && sOpen) {
                        section.classList.add("is-open");
                        sectionTrigger.setAttribute("aria-expanded", "true");
                        sbody.hidden = false;
                        sbody.style.display = "grid";
                    }
                }
            });
        }

        productBranches.forEach(function (branch) {
            var trigger = branch.querySelector(".nav-tree-branch-title");
            if (!trigger) return;
            trigger.setAttribute("aria-expanded", "false");
            trigger.addEventListener("click", function (event) {
                if (!isMobileProductAccordion()) return;
                event.preventDefault(); event.stopPropagation();
                var view = branch.closest("[data-product-view]");
                var siblings = view ? Array.from(view.querySelectorAll(".nav-tree-branch")) : [];
                var nextState = !branch.classList.contains("open");
                siblings.forEach(function (item) { setProductBranch(item, false); });
                setProductBranch(branch, nextState);
            });
        });

        document.addEventListener("click", function (event) {
            if (!productNav.contains(event.target)) closeProductMenu();
        });
    }

    /* ───────────────────────── 事件绑定：通用 mega nav ───────────────────────── */
    megaNavItems.forEach(function (megaItem) {
        var trigger = megaItem.querySelector("[data-mega-trigger]");
        var panel = megaItem.querySelector("[data-mega-panel]");
        var split = megaItem.querySelector(".nav-link-split");
        if (!trigger || !panel) return;

        trigger.addEventListener("click", function (event) {
            event.preventDefault(); event.stopPropagation();
            var open = !megaItem.classList.contains("open");
            if (open) { openMegaNav(megaItem); closeProductMenu(); closeNavDropdowns(); closeDropdown(); }
            else closeMegaNav(megaItem);
        });

        if (split) {
            split.addEventListener("click", function (event) {
                if (!mobileFabMedia || !mobileFabMedia.matches) return;
                if (event.target.closest("[data-mega-trigger]")) return;
                event.preventDefault(); event.stopPropagation();
                var open = !megaItem.classList.contains("open");
                if (open) { openMegaNav(megaItem); closeProductMenu(); closeNavDropdowns(); closeDropdown(); }
                else closeMegaNav(megaItem);
            });
        }

        megaItem.addEventListener("mouseenter", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            clearMegaHoverTimer(megaItem);
            openMegaNav(megaItem); closeProductMenu(); closeNavDropdowns(); closeDropdown();
        });
        megaItem.addEventListener("mouseleave", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            scheduleMegaClose(megaItem);
        });
        megaItem.addEventListener("focusin", function () {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            clearMegaHoverTimer(megaItem);
            openMegaNav(megaItem); closeProductMenu(); closeNavDropdowns(); closeDropdown();
        });
        megaItem.addEventListener("focusout", function (event) {
            if (mobileFabMedia && mobileFabMedia.matches) return;
            if (megaItem.contains(event.relatedTarget)) return;
            scheduleMegaClose(megaItem);
        });

        panel.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () { closeMegaNav(megaItem); });
        });
    });

    document.addEventListener("click", function (event) {
        megaNavItems.forEach(function (megaItem) {
            if (!megaItem.contains(event.target)) closeMegaNav(megaItem);
        });
    });

    /* ───────────────────────── 事件绑定：nav-dropdown ───────────────────────── */
    if (navDropdownItems.length) {
        navDropdownItems.forEach(function (item) {
            var trigger = item.querySelector("[data-nav-dropdown-trigger]");
            var panel = item.querySelector("[data-nav-dropdown-panel]");
            var split = item.querySelector(".nav-link-split");
            if (!trigger || !panel) return;

            trigger.addEventListener("click", function (event) {
                event.preventDefault(); event.stopPropagation();
                var nextOpen = !item.classList.contains("open");
                closeNavDropdowns(item);
                if (nextOpen) { closeDropdown(); closeProductMenu(); }
                setNavDropdownState(item, nextOpen);
            });

            if (split) {
                split.addEventListener("click", function (event) {
                    if (!mobileFabMedia || !mobileFabMedia.matches) return;
                    if (event.target.closest("[data-nav-dropdown-trigger]")) return;
                    event.preventDefault(); event.stopPropagation();
                    var nextOpen = !item.classList.contains("open");
                    closeNavDropdowns(item);
                    if (nextOpen) { closeDropdown(); closeProductMenu(); }
                    setNavDropdownState(item, nextOpen);
                });
            }

            item.addEventListener("mouseenter", function () {
                if (mobileFabMedia && mobileFabMedia.matches) return;
                clearNavDropdownCloseTimer(item);
                closeDropdown(); closeProductMenu(); closeNavDropdowns(item);
                setNavDropdownState(item, true);
            });
            item.addEventListener("mouseleave", function () {
                if (mobileFabMedia && mobileFabMedia.matches) return;
                scheduleNavDropdownClose(item);
            });
            item.addEventListener("focusin", function () {
                if (mobileFabMedia && mobileFabMedia.matches) return;
                clearNavDropdownCloseTimer(item);
                closeDropdown(); closeProductMenu(); closeNavDropdowns(item);
                setNavDropdownState(item, true);
            });
            item.addEventListener("focusout", function (event) {
                if (mobileFabMedia && mobileFabMedia.matches) return;
                if (item.contains(event.relatedTarget)) return;
                scheduleNavDropdownClose(item);
            });

            panel.querySelectorAll("a").forEach(function (link) {
                link.addEventListener("click", function () { closeNavDropdowns(); });
            });
        });

        document.addEventListener("click", function (event) {
            if (Array.from(navDropdownItems).some(function (item) { return item.contains(event.target); })) return;
            closeNavDropdowns();
        });
    }

    /* ───────────────────────── 事件绑定：语言菜单切换 ───────────────────────── */
    if (langMenu) {
        langMenu.addEventListener("click", function (event) {
            var button = event.target.closest("[data-lang-option]");
            if (!button) return;
            if (isStaticGeneratedPublicPage) {
                R.safeStorageSet("hanzun-lang", String(button.dataset.langOption || "").trim().toLowerCase());
                R.safeStorageSet("hanzun-lang-source", "manual");
                window.location.href = R.currentLocalizedStaticUrlForLanguage(button.dataset.langOption);
                return;
            }
            applyLanguage(button.dataset.langOption, { persistMode: "manual" });
            if (typeof R.hydratePublicSite === "function") R.hydratePublicSite(button.dataset.langOption);
            closeDropdown();
        });
    }

    /* ───────────────────────── 事件绑定：汉堡菜单 ───────────────────────── */
    if (menuToggle && menu) {
        menuToggle.addEventListener("click", function () {
            setMenuState(!menu.classList.contains("open"));
        });
        menu.addEventListener("click", function (event) {
            var link = event.target.closest('a[href^="#"]');
            var hash = (link && link.getAttribute("href")) || "";
            if (!link || !hash || hash === "#") return;
            if (!R.scrollToAnchorTarget(hash, true)) return;
            event.preventDefault();
        });
        menu.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function (event) {
                if (mobileFabMedia && mobileFabMedia.matches && link.closest(".nav-link-split")) return;
                setMenuState(false);
            });
        });
        document.addEventListener("click", function (event) {
            if (!menu.classList.contains("open")) return;
            if (menu.contains(event.target) || menuToggle.contains(event.target)) return;
            setMenuState(false);
        });
    }

    /* ───────────────────────── ESC 键 ───────────────────────── */
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeDropdown();
            closeProductMenu();
            closeNavDropdowns();
            if (typeof R.closeContactFab === "function") R.closeContactFab();
            setMenuState(false);
        }
    });

    /* ───────────────────────── resize ───────────────────────── */
    window.addEventListener("resize", function () {
        syncProductBranches(getActiveProductTabName());
        if (mobileProductAccordion) mobileProductAccordion.hidden = !isMobileProductAccordion();
    }, { passive: true });

    /* ───────────────────────── mega-panel 内联脚本逻辑 ─────────────────────────
       原先写在 index.template.html L823-940，现统一到这里。处理：
       - 移动端 news/cases 卡片降级为列表
       - 移动端 nav-link-direct / nav-link-toggle 捕获阶段点击
       - 桌面端 cat hover 切换
       - 桌面端 nav-tree-branch-title 初始 aria 状态
       - 桌面端 nav-mega-layout 注入 content-head
    ────────────────────────────────────────────────────────────────────────── */
    function applyMegaNavInlineEnhancements() {
        var items = megaNavItems;
        function open(m) {
            var p = m.querySelector("[data-mega-panel]");
            if (!p) return;
            items.forEach(function (x) {
                if (x !== m) {
                    x.classList.remove("open");
                    if (window.innerWidth > 960) {
                        var xp = x.querySelector("[data-mega-panel]");
                        if (xp) {
                            xp.style.setProperty("opacity", "0", "important");
                            xp.style.setProperty("visibility", "hidden", "important");
                            xp.style.setProperty("pointer-events", "none", "important");
                        }
                    }
                }
            });
            m.classList.add("open");
            if (window.innerWidth > 960) {
                p.style.setProperty("opacity", "1", "important");
                p.style.setProperty("visibility", "visible", "important");
                p.style.setProperty("pointer-events", "auto", "important");
                var k = m.getAttribute("data-mega-nav");
                if (k === "products" || k === "solutions") autoFirst(m);
            }
        }
        function close(m) {
            var p = m.querySelector("[data-mega-panel]");
            if (!p) return;
            m.classList.remove("open");
            if (window.innerWidth > 960) {
                p.style.setProperty("opacity", "0", "important");
                p.style.setProperty("visibility", "hidden", "important");
                p.style.setProperty("pointer-events", "none", "important");
            }
        }

        items.forEach(function (m) {
            var p = m.querySelector("[data-mega-panel]");
            if (!p) return;
            if (window.innerWidth > 960) {
                p.style.setProperty("opacity", "0", "important");
                p.style.setProperty("visibility", "hidden", "important");
                p.style.setProperty("pointer-events", "none", "important");
            }
            m.addEventListener("mouseenter", function () { if (window.innerWidth <= 960) return; open(m); });
            m.addEventListener("mouseleave", function () {
                if (window.innerWidth <= 960) return;
                var self = this;
                setTimeout(function () { if (!self.matches(":hover")) close(self); }, 200);
            });
        });

        if (window.innerWidth <= 960) {
            document.querySelectorAll('[data-mega-nav="news"], [data-mega-nav="cases"]').forEach(function (nav) {
                var panel = nav.querySelector("[data-mega-panel]");
                if (!panel) return;
                var layout = panel.querySelector(".nav-mega-layout--cards");
                if (!layout) return;
                var cardItems = layout.querySelectorAll(".nav-card-grid-item");
                var h = '<div class="nav-tree-branch-grid" style="gap:4px!important;display:flex!important;flex-direction:column!important">';
                cardItems.forEach(function (item) {
                    var href = item.getAttribute("href") || "#";
                    var label = item.querySelector(".nav-card-grid-label");
                    var text = label ? label.textContent.trim() : item.textContent.trim();
                    h += '<a href="' + href + '" style="display:flex!important;align-items:center!important;justify-content:flex-start!important;padding:12px 16px 12px 14px!important;font-size:14px!important;font-weight:500!important;color:rgba(245,248,252,.96)!important;background:rgba(255,255,255,.04)!important;border-radius:10px!important;text-decoration:none!important;width:100%!important;box-sizing:border-box!important">' + text + '</a>';
                });
                h += '</div>';
                panel.innerHTML = h;
            });
        }

        document.querySelectorAll("[data-mega-nav] .nav-link-direct").forEach(function (link) {
            link.addEventListener("click", function (e) {
                if (window.innerWidth > 960) return;
                e.preventDefault(); e.stopPropagation();
                var m = this.closest("[data-mega-nav]");
                if (!m) return;
                if (m.classList.contains("open")) close(m); else open(m);
            }, true);
        });

        document.querySelectorAll("[data-mega-nav] .nav-link-toggle").forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                if (window.innerWidth > 960) return;
                e.preventDefault(); e.stopPropagation();
                var m = this.closest("[data-mega-nav]");
                if (!m) return;
                if (m.classList.contains("open")) close(m); else open(m);
            });
        });

        items.forEach(function (m) {
            var p = m.querySelector("[data-mega-panel]");
            if (!p) return;
            p.querySelectorAll("a").forEach(function (a) {
                a.addEventListener("click", function (e) {
                    if (!a.classList.contains("nav-tree-branch-title") && !a.classList.contains("nav-mega-card")) {
                        if (window.innerWidth <= 960) { e.preventDefault(); e.stopPropagation(); }
                        close(m);
                    }
                });
            });
        });

        function autoFirst(nav) {
            var t = nav.querySelectorAll(".nav-tree-branch-title");
            if (!t.length) return;
            t.forEach(function (x) { x.classList.remove("nav-active"); });
            t[0].classList.add("nav-active");
            updateCardsHeading(nav, t[0]);
        }
        function updateCardsHeading(nav, el) {
            var head = nav.querySelector(".nav-mega-content-head strong");
            if (!head) return;
            head.textContent = el.textContent.replace(/>/g, "").trim();
            var catId = el.getAttribute("data-cat") || "";
            var cards = nav.querySelectorAll(".nav-mega-card");
            cards.forEach(function (c) {
                if (catId === "" || c.getAttribute("data-cat") === catId) c.style.setProperty("display", "flex", "important");
                else c.style.setProperty("display", "none", "important");
            });
        }

        items.forEach(function (nav) {
            var t = nav.querySelectorAll(".nav-tree-branch-title");
            t.forEach(function (el) {
                el.addEventListener("mouseenter", function () {
                    if (window.innerWidth <= 960) return;
                    t.forEach(function (x) { x.classList.remove("nav-active"); });
                    el.classList.add("nav-active");
                    updateCardsHeading(nav, el);
                });
            });
        });

        // 移动端手风琴初始隐藏
        document.querySelectorAll(".nav-tree-leaf-list").forEach(function (l) { l.style.display = "none"; });

        items.forEach(function (nav) {
            nav.querySelectorAll(".nav-tree-branch-title").forEach(function (el) {
                el.addEventListener("click", function (e) {
                    if (window.innerWidth > 960) return;
                    var branch = this.parentNode;
                    if (!branch || !branch.classList.contains("nav-tree-branch")) return;
                    var leaf = branch.querySelector(".nav-tree-leaf-list");
                    if (!leaf || !leaf.children.length) return;
                    e.preventDefault(); e.stopPropagation();
                    if (branch.classList.contains("open")) {
                        branch.classList.remove("open");
                        leaf.style.display = "none";
                    } else {
                        branch.classList.add("open");
                        leaf.style.display = "";
                        Array.from(leaf.children).forEach(function (a) {
                            a.style.display = "block";
                            a.style.padding = "8px 14px 8px 22px";
                            a.style.margin = "2px 0";
                            a.style.color = "rgba(200,216,236,.78)";
                            a.style.fontSize = "13px";
                            a.style.fontWeight = "400";
                            a.style.textDecoration = "none";
                            a.style.borderLeft = "2px solid rgba(255,255,255,.12)";
                            a.style.borderRadius = "0 6px 6px 0";
                            a.style.background = "rgba(255,255,255,.015)";
                        });
                    }
                });
            });
        });

        // products/solutions mega-layout 注入 content-head
        document.querySelectorAll('[data-mega-nav="products"], [data-mega-nav="solutions"]').forEach(function (nav) {
            var c = nav.querySelector(".nav-mega-cards");
            var l = nav.querySelector(".nav-mega-layout");
            if (!c || !l) return;
            var nm = nav.getAttribute("data-mega-nav") === "solutions" ? "方案" : "产品";
            var h = document.createElement("div");
            h.className = "nav-mega-content-head";
            h.innerHTML = '<strong>' + nm + '</strong><a href="/zh/' + (nav.getAttribute("data-mega-nav") === "solutions" ? "solutions" : "products") + '.html">查看全部 →</a>';
            var w = document.createElement("div");
            w.className = "nav-mega-content";
            w.appendChild(h);
            w.appendChild(c);
            l.appendChild(w);
        });
    }

    R.resolveContentTitle = resolveContentTitle;
    R.resolveContentSummary = resolveContentSummary;
    R.resolveContentImage = resolveContentImage;
    R.resolveFallbackImage = resolveFallbackImage;
    R.buildStaticPublicHref = buildStaticPublicHref;
    R.mapStaticPublicHref = mapStaticPublicHref;
    R.resolvePublicHref = resolvePublicHref;

    applyMegaNavInlineEnhancements();

    /* ───────────────────────── 启动 ───────────────────────── */
    var initialLanguage = R.resolveInitialPublicLanguage();
    applyLanguage(initialLanguage, { persistMode: "none" });
    ensureLanguageMenuRendered();
    setProductTab("factory");
    if (!isStaticGeneratedPublicPage && typeof R.hydratePublicSite === "function") {
        R.hydratePublicSite(initialLanguage);
    } else if (!isStaticGeneratedPublicPage) {
        loadNavigation(initialLanguage);
    }
})();
