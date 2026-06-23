(function () {
    const body = document.body;
    const heroTitle = document.getElementById("about-hero-title");
    const heroLead = document.getElementById("about-hero-lead");
    const companyKicker = document.getElementById("about-company-kicker");
    const companyIntro = document.getElementById("about-company-intro");
    const teamBlockTitle = document.getElementById("about-team-block-title");
    const certBlockTitle = document.getElementById("about-cert-block-title");
    const summaryGrid = document.getElementById("about-summary-grid");
    const richCopyGrid = document.getElementById("about-rich-copy-grid");
    const teamGrid = document.getElementById("about-team-grid");
    const certificateGrid = document.getElementById("about-certificate-grid");
    let requestVersion = 0;
    let observedLang = "";

    const fallbackTeamImages = {
        "amy zhang": "assets/images/team/sales-amy-zhang.png",
        "david lin": "assets/images/team/sales-david-lin.png",
        "lisa chen": "assets/images/team/sales-lisa-chen.png",
        "kevin xu": "assets/images/team/sales-kevin-xu.png",
        "daniel chen": "assets/images/home/company-team-factory.jpg",
    };

    function currentLanguage() {
        const code = String(body?.dataset.lang || "zh").toLowerCase();
        return code.startsWith("en") ? "en" : "zh";
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function paragraphize(value) {
        return String(value || "")
            .split(/\n+/)
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => `<p>${escapeHtml(item)}</p>`)
            .join("");
    }

    function resolveTeamImage(item, index) {
        const assetUrl = String(item?.avatar_asset_url || item?.avatar_asset?.public_url || "").trim();
        if (assetUrl) {
            return assetUrl;
        }

        const key = String(item?.name || item?.name_zh || "").trim().toLowerCase();
        if (fallbackTeamImages[key]) {
            return fallbackTeamImages[key];
        }

        const images = [
            "assets/images/team/sales-amy-zhang.png",
            "assets/images/team/sales-david-lin.png",
            "assets/images/team/sales-lisa-chen.png",
            "assets/images/team/sales-kevin-xu.png",
        ];

        return images[index % images.length];
    }

    function resolveCertificateImage(item, index) {
        const assetUrl = String(item?.image_asset_url || item?.image_asset?.public_url || "").trim();
        if (assetUrl) {
            return assetUrl;
        }

        const id = Number(item?.id || 0);
        const imageIndex = id > 0 ? ((id - 1) % 4) + 1 : (index % 4) + 1;
        return `assets/images/certificates/cert-${imageIndex}.png`;
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

    function renderSummaryCards(blocks) {
        if (!summaryGrid || !Array.isArray(blocks) || !blocks.length) {
            return;
        }

        summaryGrid.innerHTML = blocks.slice(0, 4).map((block) => `
            <article class="about-summary-card reveal is-visible">
                <strong>${escapeHtml(block.title || block.title_zh || "")}</strong>
                <p>${escapeHtml(block.subtitle || block.subtitle_zh || block.content || block.content_zh || "")}</p>
            </article>
        `).join("");
    }

    function renderRichBlocks(blocks) {
        if (!richCopyGrid || !Array.isArray(blocks) || !blocks.length) {
            return;
        }

        richCopyGrid.innerHTML = blocks.map((block) => `
            <article class="metrics-dashboard-intro about-copy-card reveal is-visible">
                <h3>${escapeHtml(block.title || block.title_zh || "")}</h3>
                ${paragraphize(block.content || block.content_zh || block.subtitle || block.subtitle_zh || "")}
            </article>
        `).join("");
    }

    function renderTeam(items) {
        if (!teamGrid || !Array.isArray(items) || !items.length) {
            return;
        }

        teamGrid.innerHTML = items.map((item, index) => {
            const name = item.name || item.name_zh || "";
            const title = item.title || item.title_zh || "";
            const department = item.department || item.department_zh || "";
            const bio = item.bio || item.bio_zh || "";
            const email = String(item.email || "").trim();
            const phone = String(item.phone || "").trim();
            const whatsapp = String(item.whatsapp || "").trim();
            const image = resolveTeamImage(item, index);

            return `
                <article class="sales-card reveal is-visible">
                    <figure class="sales-avatar">
                        <img src="${escapeHtml(image)}" alt="${escapeHtml(name)} portrait" loading="lazy" decoding="async" data-progressive-media>
                        <figcaption class="sales-name-bar">
                            <strong>${escapeHtml(name)}</strong>
                        </figcaption>
                    </figure>
                    <div class="sales-copy">
                        <div class="sales-meta">
                            <small>${escapeHtml([title, department].filter(Boolean).join(" / "))}</small>
                            <p>${escapeHtml(bio)}</p>
                        </div>
                        ${email ? `<a class="sales-contact-link sales-contact-email" href="mailto:${escapeHtml(email)}">${currentLanguage() === "en" ? "Email" : "邮箱"}</a>` : ""}
                        ${phone ? `<a class="sales-contact-link sales-contact-phone" href="tel:${escapeHtml(phone)}">${currentLanguage() === "en" ? "Phone" : "电话"}</a>` : ""}
                        ${whatsapp ? `<a class="sales-contact-link sales-contact-whatsapp" href="https://wa.me/${escapeHtml(whatsapp.replace(/\D/g, ""))}" target="_blank" rel="noopener">WhatsApp</a>` : ""}
                    </div>
                </article>
            `;
        }).join("");
    }

    function renderCertificates(items) {
        if (!certificateGrid || !Array.isArray(items) || !items.length) {
            return;
        }

        certificateGrid.innerHTML = items.map((item, index) => {
            const name = item.name || item.name_zh || "";
            const issuer = item.issuer || item.issuer_zh || "";
            const description = item.description || item.description_zh || "";

            return `
                <article class="metrics-cert-card reveal is-visible">
                    <figure class="metrics-cert-media">
                        <img src="${escapeHtml(resolveCertificateImage(item, index))}" alt="${escapeHtml(name)}" loading="lazy" decoding="async" data-progressive-media>
                    </figure>
                    <span>
                        ${escapeHtml(name)}
                        <small>${escapeHtml(issuer || description)}</small>
                    </span>
                </article>
            `;
        }).join("");
    }

    function applyAboutPayload(payload) {
        const data = payload?.data || {};
        const page = data.page || {};
        const blocks = Array.isArray(data.blocks) ? data.blocks : [];
        const textBlocks = blocks.filter((item) => !["team", "team_list", "certificate", "certificate_list"].includes(String(item.block_type || "")));
        const teamBlock = blocks.find((item) => ["team", "team_list"].includes(String(item.block_type || "")));
        const certificateBlock = blocks.find((item) => ["certificate", "certificate_list"].includes(String(item.block_type || "")));
        const leadBlock = textBlocks[0] || {};

        if (heroTitle) {
            heroTitle.textContent = page.name || page.name_zh || heroTitle.textContent;
        }

        if (heroLead) {
            heroLead.textContent = leadBlock.subtitle || leadBlock.subtitle_zh || leadBlock.content || leadBlock.content_zh || heroLead.textContent;
        }

        if (companyKicker && leadBlock.title) {
            companyKicker.textContent = leadBlock.title;
        }

        if (companyIntro) {
            companyIntro.textContent = leadBlock.content || leadBlock.content_zh || heroLead?.textContent || companyIntro.textContent;
        }

        if (teamBlockTitle && teamBlock?.title) {
            teamBlockTitle.textContent = teamBlock.title;
        }

        if (certBlockTitle && certificateBlock?.title) {
            certBlockTitle.textContent = certificateBlock.title;
        }

        if (textBlocks.length) {
            renderSummaryCards(textBlocks);
            renderRichBlocks(textBlocks);
        }

        if (Array.isArray(teamBlock?.items) && teamBlock.items.length) {
            renderTeam(teamBlock.items);
        }

        if (Array.isArray(certificateBlock?.items) && certificateBlock.items.length) {
            renderCertificates(certificateBlock.items);
        }

        if (document.title) {
            const pageName = page.name || page.name_zh || "";
            const siteConfig = typeof readPublicSiteConfig === "function" ? readPublicSiteConfig() : null;
            const siteName = String(siteConfig?.site_name || "Hanzun").trim() || "Hanzun";
            const companyName = String(siteConfig?.company_name || "上海涵尊实业有限公司").trim() || "上海涵尊实业有限公司";
            if (pageName) {
                document.title = currentLanguage() === "en"
                    ? `${pageName} | ${siteName}`
                    : `${pageName} | ${companyName}`;
            }
        }
    }

    function hydrateAbout() {
        const lang = currentLanguage();
        const version = ++requestVersion;

        fetchJson(`/api/site/about?lang=${encodeURIComponent(lang)}`)
            .then((payload) => {
                if (version !== requestVersion) {
                    return;
                }

                applyAboutPayload(payload);
                if (typeof window.initProgressiveMedia === "function") {
                    window.initProgressiveMedia();
                }
            })
            .catch(function (err) { console.error("[About] 加载企业介绍失败:", err); });
    }

    function watchLanguage() {
        if (!body) {
            return;
        }

        observedLang = currentLanguage();

        const observer = new MutationObserver(function () {
            const nextLang = currentLanguage();
            if (nextLang === observedLang) {
                return;
            }

            observedLang = nextLang;
            hydrateAbout();
        });

        observer.observe(body, {
            attributes: true,
            attributeFilter: ["data-lang"],
        });
    }

    watchLanguage();
    hydrateAbout();
})();
