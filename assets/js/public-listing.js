(function () {
    const body = document.body;
    const pageType = String(body?.dataset?.publicListing || "").trim();

    if (!pageType) {
        return;
    }

    const configMap = {
        products: {
            endpoint: "/api/site/products",
            zhTitle: "产品中心",
            enTitle: "Products",
            zhLead: "集中查看设备分类、产品摘要与首页推荐内容。",
            enLead: "Browse categories, equipment summaries, and homepage featured content.",
            detailType: "product",
            fallbackImages: [
                "assets/images/home/equipment-forming-module.jpg",
                "assets/images/home/equipment-transfer-line.jpg",
                "assets/images/home/equipment-integrated-line.jpg",
                "assets/images/home/equipment-depositing-station.jpg",
            ],
        },
        solutions: {
            endpoint: "/api/site/solutions",
            zhTitle: "生产线方案",
            enTitle: "Solutions",
            zhLead: "查看整线方案、流程说明与产能参数。",
            enLead: "Review turnkey line solutions, process flow, and capacity parameters.",
            detailType: "solution",
            fallbackImages: [
                "assets/images/home/equipment-integrated-line.jpg",
                "assets/images/home/company-strength-process-generated.jpg",
                "assets/images/home/equipment-transfer-line.jpg",
                "assets/images/home/equipment-forming-module.jpg",
            ],
        },
        articles: {
            endpoint: "/api/site/articles",
            zhTitle: "新闻与案例",
            enTitle: "News and Cases",
            zhLead: "查看企业新闻、客户案例与海外项目动态。",
            enLead: "View company news, customer cases, and overseas project updates.",
            detailType: "article",
            fallbackImages: [
                "assets/images/home/news-real-expo-hall.jpg",
                "assets/images/home/news-real-booth.jpg",
                "assets/images/home/news-real-handshake-team.jpg",
                "assets/images/home/news-real-business-pose.jpg",
            ],
        },
    };

    const currentConfig = configMap[pageType];

    if (!currentConfig) {
        return;
    }

    const state = {
        activeCategoryId: 0,
        items: [],
        categories: [],
        currentPage: 1,
        totalPages: 1,
        perPage: 12,
    };

    const nodes = {
        title: document.querySelector("[data-public-title]"),
        lead: document.querySelector("[data-public-lead]"),
        categories: document.querySelector("[data-public-categories]"),
        grid: document.querySelector("[data-public-grid]"),
    };

    const paginationNode = document.querySelector("[data-public-pagination]");
    const gridShell = nodes.grid?.closest("[data-public-grid-shell]") || nodes.grid?.parentElement;

    function currentLanguage() {
        const code = String(body?.dataset?.lang || "zh").toLowerCase();
        return code.startsWith("en") ? "en" : "zh";
    }

    function resolveApiLanguage(code) {
        if (typeof toApiLanguageCode === "function") {
            return toApiLanguageCode(code);
        }

        return String(code || "zh").toLowerCase().startsWith("en") ? "en" : "zh";
    }

    function resolveStaticDetailHref(slug) {
        const normalizedSlug = String(slug || "").trim();
        if (!normalizedSlug) {
            return "#";
        }

        if (typeof buildStaticPublicHref === "function") {
            return buildStaticPublicHref(currentConfig.detailType, normalizedSlug);
        }

        const lang = currentLanguage();
        const langPrefix = lang === "en" ? "/en" : "/zh";
        const routeSegment = currentConfig.detailType === "product"
            ? "products"
            : (currentConfig.detailType === "solution" ? "solutions" : "articles");

        return `${langPrefix}/${routeSegment}/${encodeURIComponent(normalizedSlug)}.html`;
    }

    function escapeMarkup(value) {
        if (typeof escapeHtml === "function") {
            return escapeHtml(value);
        }

        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function fetchJson(url) {
        return fetch(url, {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        }).then((response) => response.json().catch(() => null).then((result) => {
            if (!response.ok || !result || Number(result.code) !== 0) {
                throw new Error("Request failed");
            }

            return result;
        }));
    }

    function flattenCategories(items, depth, bucket) {
        (items || []).forEach((item) => {
            bucket.push({
                id: Number(item.id || 0),
                name: String(item.name || item.name_zh || "").trim(),
                depth,
            });

            if (Array.isArray(item.children) && item.children.length) {
                flattenCategories(item.children, depth + 1, bucket);
            }
        });
    }

    function resolveTitle(item) {
        return String(item.name || item.title || item.name_zh || item.title_zh || "").trim();
    }

    function resolveSummary(item) {
        return String(item.summary || item.summary_zh || item.subtitle || "").trim();
    }

    function resolveMeta(item, lang) {
        if (pageType === "products") {
            return [
                item.sku ? `SKU ${item.sku}` : "",
                item.business_status ? (lang === "en" ? String(item.business_status) : String(item.business_status)) : "",
            ].filter(Boolean);
        }

        if (pageType === "solutions") {
            return [
                item.capacity_text || "",
                item.flow_text || "",
            ].filter(Boolean);
        }

        return [
            item.content_type ? (lang === "en" ? String(item.content_type) : String(item.content_type)) : "",
            item.publish_time ? String(item.publish_time).slice(0, 10) : "",
        ].filter(Boolean);
    }

    function resolveImage(item, index) {
        const candidates = [
            item?.cover_asset_url,
            item?.cover_asset?.public_url,
            item?.cover_image_url,
        ];

        for (const candidate of candidates) {
            const value = String(candidate || "").trim();
            if (value) {
                return value;
            }
        }

        return currentConfig.fallbackImages[index % currentConfig.fallbackImages.length];
    }

    function renderHeaderCopy() {
        const lang = currentLanguage();
        const title = lang === "en" ? currentConfig.enTitle : currentConfig.zhTitle;
        const lead = lang === "en" ? currentConfig.enLead : currentConfig.zhLead;
        const siteConfig = typeof readPublicSiteConfig === "function" ? readPublicSiteConfig() : null;
        const siteName = String(siteConfig?.site_name || "HANZUN").trim() || "HANZUN";

        if (nodes.title) {
            nodes.title.textContent = title;
        }

        if (nodes.lead) {
            nodes.lead.textContent = lead;
        }

        document.title = `${title} | ${siteName}`;

        const metaDescription = document.querySelector("#meta-description");
        if (metaDescription) {
            metaDescription.setAttribute("content", lead);
        }

        if (typeof setMetaContent === "function") {
            setMetaContent("og:title", title);
            setMetaContent("og:description", lead);
            setMetaContent("og:type", "website");
            setMetaContent("og:site_name", siteName);
        }
    }

    function renderCategories() {
        if (!nodes.categories) {
            return;
        }

        const lang = currentLanguage();
        const flat = [];
        flattenCategories(state.categories, 0, flat);
        const allLabel = lang === "en" ? "All" : "全部";

        nodes.categories.innerHTML = [
            `<button class="public-filter-button${state.activeCategoryId === 0 ? " is-active" : ""}" type="button" data-category-id="0">${escapeMarkup(allLabel)}</button>`,
            ...flat.map((item) => `
                <button class="public-filter-button${state.activeCategoryId === item.id ? " is-active" : ""}" type="button" data-category-id="${item.id}">
                    ${escapeMarkup(item.depth > 0 ? `${"· ".repeat(item.depth)}${item.name}` : item.name)}
                </button>
            `),
        ].join("");
    }

    function renderLoadErrorState() {
        if (!nodes.grid) {
            return;
        }

        const lang = currentLanguage();
        const title = lang === "en"
            ? "Unable to load the latest content right now."
            : "暂时无法加载最新内容。";
        const description = lang === "en"
            ? "Please refresh later or go back to the homepage to continue browsing."
            : "请稍后刷新重试，或先返回首页继续浏览。";

        nodes.grid.innerHTML = `
            <div class="public-empty-state">
                <strong>${escapeMarkup(title)}</strong>
                <span>${escapeMarkup(description)}</span>
            </div>
        `;
    }

    function filteredItems() {
        if (!state.activeCategoryId) {
            return state.items;
        }

        const categoryIds = new Set();
        collectActiveCategoryIds(state.categories, state.activeCategoryId, categoryIds);
        if (!categoryIds.size) {
            categoryIds.add(state.activeCategoryId);
        }

        return state.items.filter((item) => categoryIds.has(Number(item.category_id || 0)));
    }

    function collectActiveCategoryIds(items, activeId, bucket) {
        (items || []).forEach((item) => {
            const currentId = Number(item.id || 0);
            const children = Array.isArray(item.children) ? item.children : [];

            if (currentId === activeId) {
                collectCategoryTreeIds(item, bucket);
                return;
            }

            if (children.length) {
                collectActiveCategoryIds(children, activeId, bucket);
            }
        });
    }

    function collectCategoryTreeIds(item, bucket) {
        const currentId = Number(item?.id || 0);
        if (currentId > 0) {
            bucket.add(currentId);
        }

        (Array.isArray(item?.children) ? item.children : []).forEach((child) => {
            collectCategoryTreeIds(child, bucket);
        });
    }

    function renderGrid() {
        if (!nodes.grid) {
            return;
        }

        const lang = currentLanguage();
        const items = filteredItems();

        if (!items.length) {
            nodes.grid.innerHTML = `<div class="public-empty-state">${escapeMarkup(lang === "en" ? "No content in this category yet." : "当前分类暂无内容。")}</div>`;
            return;
        }

        nodes.grid.innerHTML = items.map((item, index) => {
            const title = resolveTitle(item);
            const summary = resolveSummary(item);
            const meta = resolveMeta(item, lang);
            const href = resolveStaticDetailHref(item.slug);

            return `
                <a class="public-card" href="${escapeMarkup(href)}">
                    <figure class="public-card-media">
                        <img src="${escapeMarkup(resolveImage(item, index))}" alt="${escapeMarkup(title)}" loading="lazy" decoding="async" data-progressive-media>
                    </figure>
                    <div class="public-card-copy">
                        <small>${escapeMarkup(pageType === "articles" ? (item.content_type || (lang === "en" ? "News" : "新闻")) : (lang === "en" ? "Content" : "内容"))}</small>
                        <h3>${escapeMarkup(title)}</h3>
                        <p>${escapeMarkup(summary)}</p>
                        <div class="public-card-meta">
                            ${meta.map((entry) => `<span>${escapeMarkup(entry)}</span>`).join("")}
                        </div>
                    </div>
                </a>
            `;
        }).join("");

        if (typeof initProgressiveMedia === "function") {
            initProgressiveMedia();
        }
    }

    function renderPagination() {
        if (!paginationNode) return;

        if (state.totalPages <= 1) {
            paginationNode.innerHTML = "";
            return;
        }

        const pages = [];
        const current = state.currentPage;
        const total = state.totalPages;

        const addPage = (num, label) => {
            pages.push({ type: "page", num, label: label ?? String(num), active: num === current });
        };

        const addEllipsis = () => {
            if (pages.length && pages[pages.length - 1].type !== "ellipsis") {
                pages.push({ type: "ellipsis" });
            }
        };

        // Prev
        pages.push({ type: "prev", disabled: current <= 1 });

        if (total <= 7) {
            for (let i = 1; i <= total; i++) addPage(i);
        } else {
            addPage(1);
            if (current > 3) addEllipsis();

            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);
            for (let i = start; i <= end; i++) addPage(i);

            if (current < total - 2) addEllipsis();
            addPage(total);
        }

        // Next
        pages.push({ type: "next", disabled: current >= total });

        paginationNode.innerHTML = pages.map((p) => {
            if (p.type === "ellipsis") {
                return `<span class="public-page-ellipsis">...</span>`;
            }
            if (p.type === "prev") {
                return `<button class="public-page-btn" data-page="prev"${p.disabled ? " disabled" : ""} type="button">&laquo;</button>`;
            }
            if (p.type === "next") {
                return `<button class="public-page-btn" data-page="next"${p.disabled ? " disabled" : ""} type="button">&raquo;</button>`;
            }
            return `<button class="public-page-btn${p.active ? " is-active" : ""}" data-page="${p.num}" type="button">${escapeMarkup(p.label)}</button>`;
        }).join("");
    }

    function scrollToGridTop() {
        if (gridShell) {
            const top = gridShell.getBoundingClientRect().top + window.scrollY - 140;
            window.scrollTo({ top, behavior: "smooth" });
        }
    }

    function bindCategoryEvents() {
        nodes.categories?.addEventListener("click", (event) => {
            const button = event.target.closest("[data-category-id]");

            if (!button) {
                return;
            }

            state.activeCategoryId = Number(button.getAttribute("data-category-id") || 0);
            state.currentPage = 1;
            renderCategories();
            renderGrid();
            renderPagination();
            scrollToGridTop();
        });
    }

    function bindPaginationEvents() {
        paginationNode?.addEventListener("click", (event) => {
            const button = event.target.closest("[data-page]");
            if (!button || button.disabled || button.classList.contains("is-active")) return;

            const pageAttr = button.getAttribute("data-page");
            let nextPage;

            if (pageAttr === "prev") {
                nextPage = state.currentPage - 1;
            } else if (pageAttr === "next") {
                nextPage = state.currentPage + 1;
            } else {
                nextPage = Number(pageAttr);
            }

            if (nextPage < 1 || nextPage > state.totalPages || nextPage === state.currentPage) return;

            state.currentPage = nextPage;
            loadListing(currentLanguage());
            scrollToGridTop();
        });
    }

    async function loadListing(requestedCode) {
        try {
            const separator = currentConfig.endpoint.includes("?") ? "&" : "?";
            const page = Math.max(1, state.currentPage || 1);
            const perPage = Math.max(1, state.perPage || 12);
            const result = await fetchJson(`${currentConfig.endpoint}${separator}lang=${encodeURIComponent(resolveApiLanguage(requestedCode))}&page=${page}&per_page=${perPage}`);
            const resolvedCode = result?.meta?.language?.resolved_code || resolveApiLanguage(requestedCode);

            if (typeof applyLanguage === "function") {
                applyLanguage(resolvedCode, { persistMode: "none" });
            }

            const paginationMeta = result?.data?.meta || {};
            state.items = Array.isArray(result?.data?.items) ? result.data.items : [];
            state.categories = Array.isArray(result?.data?.categories) ? result.data.categories : [];
            state.currentPage = paginationMeta.current_page || page;
            state.totalPages = paginationMeta.total_pages || 1;
            state.perPage = paginationMeta.per_page || perPage;
            renderHeaderCopy();
            renderCategories();
            renderGrid();
            renderPagination();
        } catch (error) {
            renderHeaderCopy();
            renderCategories();
            renderLoadErrorState();
        }
    }

    function watchLanguageChanges() {
        const observer = new MutationObserver(() => {
            renderHeaderCopy();
            renderCategories();
            renderGrid();
        });

        observer.observe(body, {
            attributes: true,
            attributeFilter: ["data-lang"],
        });
    }

    const initialLanguage = typeof resolveInitialPublicLanguage === "function"
        ? resolveInitialPublicLanguage()
        : (
            body.dataset.forceLang
            || (typeof safeStorageGet === "function" ? safeStorageGet("hanzun-lang") : "")
            || (typeof detectBrowserLanguage === "function" ? detectBrowserLanguage() : "")
            || body.dataset.lang
            || "zh"
        );

    bindCategoryEvents();
    bindPaginationEvents();
    watchLanguageChanges();
    loadListing(initialLanguage);
})();
