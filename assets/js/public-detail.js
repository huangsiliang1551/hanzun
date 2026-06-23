(function () {
    const body = document.body;
    const pageType = String(body?.dataset?.publicDetailType || "").trim();
    const slug = new URLSearchParams(window.location.search).get("slug") || String(body?.dataset?.defaultSlug || "").trim();

    if (!pageType || !slug) {
        return;
    }

    const detailConfig = {
        product: {
            endpoint: "/api/site/products/",
            zhType: "产品详情",
            enType: "Product Detail",
            listingUrl: "products.html",
            fallbackImage: "assets/images/home/equipment-forming-module.jpg",
        },
        solution: {
            endpoint: "/api/site/solutions/",
            zhType: "生产线方案",
            enType: "Solution Detail",
            listingUrl: "solutions.html",
            fallbackImage: "assets/images/home/equipment-integrated-line.jpg",
        },
        article: {
            endpoint: "/api/site/articles/",
            zhType: "新闻与案例",
            enType: "Article Detail",
            listingUrl: "news.html",
            fallbackImage: "assets/images/home/news-real-booth.jpg",
        },
    };

    const currentConfig = detailConfig[pageType];

    if (!currentConfig) {
        return;
    }

    const nodes = {
        type: document.querySelector("[data-public-detail-type-label]"),
        title: document.querySelector("[data-public-detail-title]"),
        summary: document.querySelector("[data-public-detail-summary]"),
        content: document.querySelector("[data-public-detail-content]"),
        facts: document.querySelector("[data-public-detail-facts]"),
        image: document.querySelector("[data-public-detail-image]"),
        breadcrumbs: document.querySelector("[data-public-detail-breadcrumbs]"),
        related: document.querySelector("[data-public-detail-related]"),
    };

    function currentPageLanguage() {
        if (typeof currentSupportLanguage === "function") {
            return currentSupportLanguage();
        }

        return String(body?.dataset?.lang || "zh").startsWith("en") ? "en" : "zh";
    }

    function resolveApiLanguage(code) {
        if (typeof toApiLanguageCode === "function") {
            return toApiLanguageCode(code);
        }

        return String(code || "zh").toLowerCase().startsWith("en") ? "en" : "zh";
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

    function sanitizeRichContent(html) {
        const allowedTags = new Set([
            "p", "br", "b", "strong", "i", "em", "u", "s", "span",
            "ul", "ol", "li",
            "h1", "h2", "h3", "h4", "h5", "h6",
            "blockquote", "pre", "code",
            "a", "img",
            "table", "thead", "tbody", "tr", "th", "td",
            "div", "section", "figure", "figcaption",
            "hr", "sub", "sup", "small", "mark",
        ]);
        const allowedAttributes = new Set([
            "href", "target", "rel",
            "src", "alt", "width", "height", "loading",
            "class", "id",
            "title", "align",
        ]);

        const doc = document.createElement("div");
        doc.innerHTML = String(html || "");

        function clean(node) {
            if (node.nodeType === 1) {
                const tag = node.tagName.toLowerCase();
                if (!allowedTags.has(tag)) {
                    const parent = node.parentNode;
                    while (node.firstChild) {
                        parent.insertBefore(node.firstChild, node);
                    }
                    parent.removeChild(node);
                    return;
                }

                Array.from(node.attributes).forEach((attr) => {
                    if (!allowedAttributes.has(attr.name)) {
                        node.removeAttribute(attr.name);
                    }
                });

                if (tag === "a" && node.getAttribute("href")?.startsWith("javascript:")) {
                    node.removeAttribute("href");
                }

                if (tag === "img" && !node.getAttribute("src")) {
                    node.removeAttribute("src");
                }
            }

            Array.from(node.childNodes).forEach(clean);
        }

        clean(doc);
        return doc.innerHTML;
    }

    function formatDateTime(value) {
        const date = new Date(String(value || ""));

        if (Number.isNaN(date.getTime())) {
            return String(value || "");
        }

        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    }

    function statusLabel(field, value, lang) {
        const labels = {
            business_status: {
                on_sale: { zh: "上架", en: "On sale" },
                off_sale: { zh: "下架", en: "Off sale" },
                discontinued: { zh: "停产", en: "Discontinued" },
            },
            publish_status: {
                draft: { zh: "草稿", en: "Draft" },
                published: { zh: "已发布", en: "Published" },
                offline: { zh: "已下线", en: "Offline" },
            },
            content_type: {
                news: { zh: "新闻", en: "News" },
                case: { zh: "客户案例", en: "Case" },
            },
        };

        const mapped = labels[field]?.[String(value || "")];
        return mapped ? mapped[lang] : String(value || "");
    }

    function renderFacts(items) {
        if (!nodes.facts) {
            return;
        }

        nodes.facts.innerHTML = items.map((item) => `
            <article class="contact-card">
                <div class="contact-card-head">
                    <small>${escapeMarkup(item.label)}</small>
                </div>
                <strong><span>${escapeMarkup(item.value)}</span></strong>
            </article>
        `).join("");
    }

    function renderRelated(record, lang) {
        if (!nodes.related) {
            return;
        }

        const relatedItems = [];
        if (Array.isArray(record.related_solution_ids) && record.related_solution_ids.length) {
            relatedItems.push({
                label: lang === "en" ? "Related Solutions" : "关联方案",
                value: record.related_solution_ids.join(", "),
            });
        }
        if (Array.isArray(record.related_product_ids) && record.related_product_ids.length) {
            relatedItems.push({
                label: lang === "en" ? "Related Products" : "关联产品",
                value: record.related_product_ids.join(", "),
            });
        }
        if (String(record.case_tags || "").trim()) {
            relatedItems.push({
                label: lang === "en" ? "Tags" : "标签",
                value: String(record.case_tags || ""),
            });
        }

        if (!relatedItems.length) {
            nodes.related.hidden = true;
            return;
        }

        nodes.related.hidden = false;
        nodes.related.innerHTML = relatedItems.map((item) => `
            <article class="contact-card">
                <div class="contact-card-head">
                    <small>${escapeMarkup(item.label)}</small>
                </div>
                <strong><span>${escapeMarkup(item.value)}</span></strong>
            </article>
        `).join("");
    }

    function setMetaContent(name, value) {
        if (!value) return;
        const attr = name.startsWith("og:") ? "property" : "name";
        let el = document.querySelector(`meta[${attr}="${CSS.escape(name)}"]`);
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

    function applySeoMeta(record) {
        const lang = currentPageLanguage();
        const siteConfig = typeof readPublicSiteConfig === "function" ? readPublicSiteConfig() : null;
        const siteName = String(siteConfig?.site_name || "HANZUN").trim() || "HANZUN";
        const seo = record?.seo || {};
        const title = String(record.name || record.title || record.name_zh || record.title_zh || "").trim();
        const summary = String(record.summary || record.summary_zh || "").trim();

        const seoTitle = seo.title || title;
        document.title = `${seoTitle} | ${siteName}`;

        const seoDescription = seo.description || summary;
        const metaDescription = document.querySelector("#meta-description");
        if (metaDescription && seoDescription) {
            metaDescription.setAttribute("content", seoDescription);
        }

        setMetaContent("og:title", seoTitle);
        if (seoDescription) {
            setMetaContent("og:description", seoDescription);
        }
        setMetaContent("og:type", pageType === "article" ? "article" : "website");
        setMetaContent("og:site_name", siteName);

        const image = resolveImage(record);
        if (image && !image.startsWith("assets/images/")) {
            setMetaContent("og:image", image);
        }

        if (seo.canonical_url) {
            setCanonicalUrl(seo.canonical_url);
        }

        if (seo.index_status) {
            setMetaContent("robots", seo.index_status === "noindex" ? "noindex, nofollow" : "index, follow");
        }
    }

    function resolveImage(record) {
        const candidates = [
            record?.cover_asset_url,
            record?.cover_asset?.public_url,
            record?.cover_image_url,
        ];

        for (const candidate of candidates) {
            const value = String(candidate || "").trim();
            if (value) {
                return value;
            }
        }

        return currentConfig.fallbackImage;
    }

    function renderDetail(record) {
        const lang = currentPageLanguage();
        const title = String(record.name || record.title || record.name_zh || record.title_zh || "").trim();
        const summary = String(record.summary || record.summary_zh || "").trim();
        const content = String(record.content || record.content_zh || "").trim();

        if (nodes.type) {
            nodes.type.textContent = lang === "en" ? currentConfig.enType : currentConfig.zhType;
        }

        if (nodes.title && title) {
            nodes.title.textContent = title;
        }

        if (nodes.summary && summary) {
            nodes.summary.textContent = summary;
        }

        if (nodes.content && content) {
            if (pageType === "article" || pageType === "product" || pageType === "solution") {
                nodes.content.innerHTML = sanitizeRichContent(content);
            } else {
                nodes.content.textContent = content;
            }
        }

        if (nodes.image) {
            nodes.image.src = resolveImage(record);
            nodes.image.alt = title || "Hanzun detail";
            nodes.image.setAttribute("data-progressive-media", "");
            nodes.image.onerror = function () {
                this.src = currentConfig.fallbackImage;
                this.onerror = null;
            };
        }

        if (nodes.breadcrumbs) {
            const listLabel = lang === "en" ? "Back to List" : "返回列表";
            nodes.breadcrumbs.innerHTML = `<a href="${escapeMarkup(currentConfig.listingUrl)}">${escapeMarkup(listLabel)}</a>`;
        }

        const facts = [];
        if (pageType === "product") {
            if (record.sku) {
                facts.push({ label: "SKU", value: String(record.sku) });
            }
            if (record.business_status) {
                facts.push({
                    label: lang === "en" ? "Business Status" : "业务状态",
                    value: statusLabel("business_status", record.business_status, lang),
                });
            }
            if (record.publish_status) {
                facts.push({
                    label: lang === "en" ? "Publish Status" : "发布状态",
                    value: statusLabel("publish_status", record.publish_status, lang),
                });
            }
        } else if (pageType === "solution") {
            if (record.publish_status) {
                facts.push({
                    label: lang === "en" ? "Publish Status" : "发布状态",
                    value: statusLabel("publish_status", record.publish_status, lang),
                });
            }
        } else if (pageType === "article") {
            if (record.content_type) {
                facts.push({
                    label: lang === "en" ? "Type" : "内容类型",
                    value: statusLabel("content_type", record.content_type, lang),
                });
            }
            if (record.country_code) {
                facts.push({ label: lang === "en" ? "Country" : "国家", value: String(record.country_code) });
            }
            if (record.publish_time) {
                facts.push({
                    label: lang === "en" ? "Publish Time" : "发布时间",
                    value: formatDateTime(record.publish_time),
                });
            }
        }

        renderFacts(facts);
        renderRelated(record, lang);

        if (pageType === "product") {
            renderProductExtraSections(record, lang);
        } else if (pageType === "solution") {
            }

        applySeoMeta(record);

        if (typeof initProgressiveMedia === "function") {
            initProgressiveMedia();
        }
    }

    function renderProductExtraSections(record, lang) {
        // Specifications table
        const specsContainer = document.querySelector("[data-public-detail-specs]");
        if (specsContainer && record.specifications) {
            let specs = record.specifications;
            if (typeof specs === "string") {
                try { specs = JSON.parse(specs); } catch (e) { specs = null; }
            }
            if (specs && typeof specs === "object" && Object.keys(specs).length > 0) {
                const rows = Object.entries(specs).map(([key, val]) =>
                    "<tr><th>" + escapeMarkup(key) + "</th><td>" + escapeMarkup(String(val)) + "</td></tr>"
                ).join("");
                specsContainer.hidden = false;
                specsContainer.querySelector("table")?.remove();
                const table = document.createElement("table");
                table.className = "specs-table";
                table.innerHTML = "<tbody>" + rows + "</tbody>";
                specsContainer.appendChild(table);
            }
        }

        // Gallery
        const galleryContainer = document.querySelector("[data-public-detail-gallery]");
        if (galleryContainer && record.gallery_assets) {
            let gallery = record.gallery_assets;
            if (typeof gallery === "string") {
                try { gallery = JSON.parse(gallery); } catch (e) { gallery = null; }
            }
            if (Array.isArray(gallery) && gallery.length > 0) {
                galleryContainer.hidden = false;
                const images = gallery.map((asset, idx) =>
                    "<div class=\"gallery-slide\" data-gallery-index=\"" + idx + "\">" +
                    "<img src=\"" + escapeMarkup(String(asset.url || asset || "")) + "\" alt=\"\" loading=\"lazy\" onerror=\"this.src='" + escapeMarkup(currentConfig.fallbackImage) + "';this.onerror=null\">" +
                    "</div>"
                ).join("");
                galleryContainer.innerHTML =
                    "<div class=\"gallery-track\">" + images + "</div>" +
                    "<div class=\"gallery-nav\">" +
                    gallery.map((_, idx) => "<button class=\"gallery-dot" + (idx === 0 ? " is-active" : "") + "\" data-gallery-dot=\"" + idx + "\"></button>").join("") +
                    "</div>";

                galleryContainer.querySelectorAll("[data-gallery-dot]").forEach((dot) => {
                    dot.addEventListener("click", () => {
                        const index = parseInt(dot.dataset.galleryDot, 10);
                        galleryContainer.querySelectorAll(".gallery-slide").forEach((slide, i) => {
                            slide.style.display = i === index ? "block" : "none";
                        });
                        galleryContainer.querySelectorAll("[data-gallery-dot]").forEach((d) => {
                            d.classList.toggle("is-active", parseInt(d.dataset.galleryDot, 10) === index);
                        });
                    });
                });

                // Show first slide
                galleryContainer.querySelectorAll(".gallery-slide").forEach((slide, i) => {
                    slide.style.display = i === 0 ? "block" : "none";
                });
            }
        }

        // Related solutions
        const relSolutions = document.querySelector("[data-public-detail-rel-solutions]");
        if (relSolutions && record.related_solution_ids && Array.isArray(record.related_solution_ids) && record.related_solution_ids.length > 0) {
            relSolutions.hidden = false;
            const label = lang === "en" ? "Related Solutions" : "相关方案";
            const heading = relSolutions.querySelector("h3");
            if (heading) heading.textContent = label;
            const list = relSolutions.querySelector("[data-rel-solutions-list]");
            if (list) {
                const ids = record.related_solution_ids.join(",");
                fetch("/api/site/solutions?ids=" + encodeURIComponent(ids) + "&lang=" + resolveApiLanguage(lang))
                    .then((r) => r.json())
                    .then((res) => {
                        const related = Array.isArray(res.data) ? res.data : (Array.isArray(res.items) ? res.items : []);
                        list.innerHTML = related.map((item) => {
                            const name = String(item.name || item.name_zh || item.title || "").trim();
                            return "<a class=\"related-chip\" href=\"solutions.html?slug=" + encodeURIComponent(item.slug || "") + "\">" + escapeMarkup(name) + "</a>";
                        }).join("") || "<span>-";
                    })
                    .catch(function(err) { console.error("[Detail] 关联数据加载失败:", err); });
            }
        }

        // JSON-LD 结构化数据
        injectJsonLd(record, lang, currentConfig);
    }

    function injectJsonLd(record, lang, config) {
        var schema = {};
        if (pageType === "product") {
            schema = { "@context": "https://schema.org", "@type": "Product", "name": record.name || record.name_zh || "", "description": record.summary || record.summary_zh || "", "image": resolveImage(record) || undefined, "sku": record.sku || undefined };
        } else if (pageType === "article") {
            schema = { "@context": "https://schema.org", "@type": "Article", "headline": record.title || record.title_zh || "", "description": record.summary || record.summary_zh || "", "image": resolveImage(record) || undefined, "datePublished": record.publish_time || record.created_at || undefined };
        } else if (pageType === "solution") {
            schema = { "@context": "https://schema.org", "@type": "Service", "name": record.name || record.name_zh || "", "description": record.summary || record.summary_zh || "", "image": resolveImage(record) || undefined };
        } else { return; }
        var script = document.createElement('script');
        script.type = 'application/ld+json';
        script.textContent = JSON.stringify(schema, function(k,v){return v===undefined?undefined:v;});
        document.head.appendChild(script);
    }

    function renderEquipmentSections(record, lang) {
        // Included equipment
        const equipContainer = document.querySelector("[data-public-detail-equipment]");
        if (equipContainer && record.related_product_ids && Array.isArray(record.related_product_ids) && record.related_product_ids.length > 0) {
            equipContainer.hidden = false;
            const label = lang === "en" ? "Equipment Included" : "包含设备";
            const heading = equipContainer.querySelector("h3");
            if (heading) heading.textContent = label;
            const list = equipContainer.querySelector("[data-equipment-list]");
            if (list) {
                const ids = record.related_product_ids.join(",");
                fetch("/api/site/products?ids=" + encodeURIComponent(ids) + "&lang=" + resolveApiLanguage(lang))
                    .then((r) => r.json())
                    .then((res) => {
                        const products = Array.isArray(res.data) ? res.data : (Array.isArray(res.items) ? res.items : []);
                        list.innerHTML = products.map((item) => {
                            const name = String(item.name || item.name_zh || item.title || "").trim();
                            const href = typeof buildStaticPublicHref === "function"
                                ? buildStaticPublicHref("product", item.slug || "")
                                : "/zh/products/" + encodeURIComponent(item.slug || "") + ".html";
                            return "<a class=\"related-chip\" href=\"" + escapeMarkup(href) + "\">" + escapeMarkup(name) + "</a>";
                        }).join("") || "<span>-";
                    })
                    .catch(function(err) { console.error("[Detail] 关联数据加载失败:", err); });
            }
        }
    }

    function renderDetailNotFoundState() {
        const lang = currentPageLanguage();
        const siteConfig = typeof readPublicSiteConfig === "function" ? readPublicSiteConfig() : null;
        const siteName = String(siteConfig?.site_name || "HANZUN").trim() || "HANZUN";
        const title = lang === "en" ? "Content not found" : "内容未找到";
        const summary = lang === "en"
            ? "The page you are looking for does not exist or has been removed."
            : "您查找的页面不存在或已被移除。";
        const bodyText = lang === "en"
            ? "Please check the URL or go back to the listing page to browse available content."
            : "请检查链接地址是否正确，或返回列表页浏览现有内容。";
        const listLabel = lang === "en" ? "Back to List" : "返回列表";

        if (nodes.type) {
            nodes.type.textContent = lang === "en" ? currentConfig.enType : currentConfig.zhType;
        }
        if (nodes.title) {
            nodes.title.textContent = title;
        }
        if (nodes.summary) {
            nodes.summary.textContent = summary;
        }
        if (nodes.content) {
            nodes.content.textContent = bodyText;
        }
        if (nodes.image) {
            nodes.image.src = currentConfig.fallbackImage;
            nodes.image.alt = title;
            nodes.image.setAttribute("data-progressive-media", "");
        }
        if (nodes.breadcrumbs) {
            nodes.breadcrumbs.innerHTML = `<a href="${escapeMarkup(currentConfig.listingUrl)}">${escapeMarkup(listLabel)}</a>`;
        }
        if (nodes.facts) {
            nodes.facts.innerHTML = "";
        }
        if (nodes.related) {
            nodes.related.hidden = true;
            nodes.related.innerHTML = "";
        }

        applySeoMeta({
            name: title,
            summary: summary,
        });

        if (typeof initProgressiveMedia === "function") {
            initProgressiveMedia();
        }
    }

    function renderDetailErrorState() {
        const lang = currentPageLanguage();
        const siteConfig = typeof readPublicSiteConfig === "function" ? readPublicSiteConfig() : null;
        const siteName = String(siteConfig?.site_name || "HANZUN").trim() || "HANZUN";
        const title = lang === "en" ? "Unable to load this page right now." : "当前页面暂时无法加载";
        const summary = lang === "en"
            ? "Please go back to the listing page or try refreshing later."
            : "请先返回列表页，或稍后刷新重试。";
        const bodyText = lang === "en"
            ? "The content service is temporarily unavailable, but you can continue browsing other pages or contact the factory team."
            : "内容服务暂时不可用，但您仍可继续浏览其他页面，或直接联系工厂团队。";
        const listLabel = lang === "en" ? "Back to List" : "返回列表";

        if (nodes.type) {
            nodes.type.textContent = lang === "en" ? currentConfig.enType : currentConfig.zhType;
        }
        if (nodes.title) {
            nodes.title.textContent = title;
        }
        if (nodes.summary) {
            nodes.summary.textContent = summary;
        }
        if (nodes.content) {
            nodes.content.textContent = bodyText;
        }
        if (nodes.image) {
            nodes.image.src = currentConfig.fallbackImage;
            nodes.image.alt = title;
            nodes.image.setAttribute("data-progressive-media", "");
        }
        if (nodes.breadcrumbs) {
            nodes.breadcrumbs.innerHTML = `<a href="${escapeMarkup(currentConfig.listingUrl)}">${escapeMarkup(listLabel)}</a>`;
        }
        if (nodes.facts) {
            nodes.facts.innerHTML = "";
        }
        if (nodes.related) {
            nodes.related.hidden = true;
            nodes.related.innerHTML = "";
        }

        applySeoMeta({
            name: title,
            summary: summary,
        });

        if (typeof initProgressiveMedia === "function") {
            initProgressiveMedia();
        }
    }

    async function loadDetail(requestedCode) {
        try {
            const result = await getPublicApi(`${currentConfig.endpoint}${encodeURIComponent(slug)}?lang=${encodeURIComponent(resolveApiLanguage(requestedCode))}`);
            const resolvedCode = result?.meta?.language?.resolved_code || resolveApiLanguage(requestedCode);

            if (typeof applyLanguage === "function") {
                applyLanguage(resolvedCode, { persistMode: "none" });
            }

            renderDetail(result?.data || {});

            // T7: 浏览量计数（不阻塞渲染）
            if (result?.data?.id) {
                var entityType = pageType === 'article' ? 'article' : pageType;
                fetch('/api/site/pageview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entity_type: entityType, entity_id: result.data.id }),
                    keepalive: true
                }).catch(function() {});
            }
        } catch (error) {
            if (error.code === 40401) {
                renderDetailNotFoundState();
            } else {
                renderDetailErrorState();
            }
        }
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

    function initShareButton() {
        var shareBtn = document.querySelector("[data-share-button]");
        if (!shareBtn) return;
        shareBtn.addEventListener("click", function () {
            var url = window.location.href;
            var title = document.title || "HANZUN";
            if (typeof navigator.share === "function") {
                navigator.share({ title: title, url: url }).catch(function () {});
            } else if (typeof navigator.clipboard?.writeText === "function") {
                navigator.clipboard.writeText(url).then(function () {
                    var origText = shareBtn.textContent;
                    shareBtn.textContent = currentPageLanguage() === "en" ? "Copied!" : "已复制";
                    setTimeout(function () { shareBtn.textContent = origText; }, 2000);
                }).catch(function () {});
            }
        });
    }

    initShareButton();
    loadDetail(initialLanguage);
})();
