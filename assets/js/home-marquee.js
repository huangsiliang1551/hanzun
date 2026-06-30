/**
 * home-marquee.js — 首页团队滚动 + 证书舞台 + 数字动画
 *
 * 仅首页加载。负责：
 *   - initLoopStrip（销售团队卡片横向滚动循环）
 *   - hydrateTeamStrip（从 API 拉销售团队数据渲染卡片）
 *   - hydrateCertificatesGrid（首页证书网格）
 *   - initCertificateStage（证书卡片轮播）
 *   - animateCounter（数字滚动动画）
 *
 * 依赖 site-runtime.js。本模块通过 HanzunRuntime 暴露 animateCounter / hydrateTeamStrip /
 * hydrateCertificatesGrid，供 home-hydrate.js 或自身启动逻辑调用。
 *
 * 注意：loopStrips.forEach(initLoopStrip) 在本模块启动时立即执行；如果页面没有
 * [data-loop-strip] 元素，forEach 不会触发，天然满足"仅首页调用"。
 */
(function () {
    "use strict";
    var R = window.HanzunRuntime;
    if (!R) return;

    var loopStrips = R.loopStrips;
    var certificateStage = R.certificateStage;

    /* ───────────────────────── animateCounter ───────────────────────── */
    function animateCounter(node) {
        if (node.dataset.animated === "true") return;
        node.dataset.animated = "true";
        var target = Number(node.dataset.count || 0);
        var suffix = node.dataset.suffix || "";
        var duration = 1500;
        var start = performance.now();

        function frame(now) {
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = Math.round(target * eased);
            node.textContent = value + suffix;
            if (progress < 1) requestAnimationFrame(frame);
            else node.textContent = target + suffix;
        }
        requestAnimationFrame(frame);
    }
    R.animateCounter = animateCounter;

    /* ───────────────────────── initLoopStrip ───────────────────────── */
    function initLoopStrip(strip) {
        var track = strip.querySelector("[data-loop-track]");
        if (!track) return;
        var items = Array.from(track.children);
        if (items.length < 2) return;

        var stepDelay = Number(strip.dataset.loopStepDelay || 2800);
        var paused = false;
        var stepTimer = null;
        var isAnimating = false;

        function getGap() {
            return Number.parseFloat(window.getComputedStyle(track).gap || "0") || 0;
        }
        function getStepDistance() {
            var first = track.firstElementChild;
            if (!first) return 0;
            return first.getBoundingClientRect().width + getGap();
        }
        function resetTrackPosition() {
            track.style.transition = "none";
            track.style.transform = "translate3d(0, 0, 0)";
        }
        function stopLoop() {
            if (stepTimer) { window.clearInterval(stepTimer); stepTimer = null; }
        }
        function stepLoop() {
            if (paused || isAnimating) return;
            var distance = getStepDistance();
            if (!distance) return;
            isAnimating = true;
            track.style.transition = "transform 560ms cubic-bezier(0.22, 0.61, 0.36, 1)";
            track.style.transform = "translate3d(" + (-distance) + "px, 0, 0)";
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

        track.addEventListener("transitionend", function (event) {
            if (event.target !== track || event.propertyName !== "transform") return;
            var first = track.firstElementChild;
            if (first) track.appendChild(first);
            resetTrackPosition();
            void track.offsetWidth;
            track.style.transition = "";
            isAnimating = false;
        });

        window.addEventListener("resize", function () { rebuildLoop(); }, { passive: true });
        window.addEventListener("load", rebuildLoop, { once: true });

        strip.addEventListener("mouseenter", function () { paused = true; stopLoop(); });
        strip.addEventListener("mouseleave", function () { paused = false; startLoop(); });
        strip.addEventListener("focusin", function () { paused = true; stopLoop(); });
        strip.addEventListener("focusout", function () { paused = false; startLoop(); });

        document.addEventListener("visibilitychange", function () {
            if (document.hidden) { stopLoop(); return; }
            startLoop();
        });

        rebuildLoop();
    }
    R.initLoopStrip = initLoopStrip;

    /* ───────────────────────── hydrateTeamStrip ───────────────────────── */
    function hydrateTeamStrip(members) {
        var track = document.querySelector("[data-loop-track]");
        if (!track || !members || !members.length) return;
        track.innerHTML = members.map(function (m) {
            var name = m.name || m.name_zh || "";
            var avatar = m.avatar_asset_url || (m.avatar_asset && m.avatar_asset.public_url) || R.assetPath("assets/images/team/default.png");
            var email = m.email || "";
            var phone = m.phone || "";
            var whatsapp = m.whatsapp || "";
            return '<article class="sales-card">' +
                '<figure class="sales-avatar"><img src="' + R.escapeHtml(avatar) + '" alt="' + R.escapeHtml(name) + '" loading="lazy" decoding="async" data-progressive-media>' +
                '<figcaption class="sales-name-bar"><strong>' + R.escapeHtml(name) + '</strong></figcaption></figure>' +
                '<div class="sales-copy">' +
                (email ? '<a class="sales-contact-link sales-contact-email" href="mailto:' + R.escapeHtml(email) + '">' + R.escapeHtml(R.getLocalizedRuntimeCopy("邮箱", "Email")) + '</a>' : '') +
                (phone ? '<a class="sales-contact-link sales-contact-phone" href="tel:' + R.escapeHtml(phone) + '">' + R.escapeHtml(R.getLocalizedRuntimeCopy("电话", "Phone")) + '</a>' : '') +
                (whatsapp ? '<a class="sales-contact-link sales-contact-whatsapp" href="https://wa.me/' + R.escapeHtml(whatsapp.replace(/\D/g, "")) + '">WhatsApp</a>' : '') +
                '</div></article>';
        }).join("");
        if (typeof R.initProgressiveMedia === "function") R.initProgressiveMedia();
    }
    R.hydrateTeamStrip = hydrateTeamStrip;

    /* ───────────────────────── hydrateCertificatesGrid ───────────────────────── */
    function hydrateCertificatesGrid(certificates) {
        var grid = document.querySelector(".metrics-cert-grid");
        if (!grid || !certificates || !certificates.length) return;
        grid.innerHTML = certificates.slice(0, 5).map(function (cert) {
            var name = cert.name || cert.name_zh || "";
            var img = cert.image_asset_url || (cert.image_asset && cert.image_asset.public_url) || R.assetPath("assets/images/certificates/cert-1.png");
            return '<article class="metrics-cert-card">' +
                '<figure class="metrics-cert-media">' +
                '<img src="' + R.escapeHtml(img) + '" alt="' + R.escapeHtml(name) + '" loading="lazy" decoding="async" data-progressive-media>' +
                '</figure><span>' + R.escapeHtml(name) + '</span></article>';
        }).join("");
        if (typeof R.initProgressiveMedia === "function") R.initProgressiveMedia();
    }
    R.hydrateCertificatesGrid = hydrateCertificatesGrid;

    /* ───────────────────────── initCertificateStage ───────────────────────── */
    function initCertificateStage(stage) {
        var cards = Array.from(stage.querySelectorAll(".certificate-card"));
        if (cards.length < 2) return;

        var index = 0;
        var paused = false;

        function paint() {
            cards.forEach(function (card, cardIndex) {
                card.classList.remove("is-active", "is-prev", "is-next", "is-hidden");
                if (cardIndex === index) card.classList.add("is-active");
                else if (cardIndex === (index - 1 + cards.length) % cards.length) card.classList.add("is-prev");
                else if (cardIndex === (index + 1) % cards.length) card.classList.add("is-next");
                else card.classList.add("is-hidden");
            });
        }
        function tick() {
            if (!paused) {
                index = (index + 1) % cards.length;
                paint();
            }
        }

        stage.addEventListener("mouseenter", function () { paused = true; });
        stage.addEventListener("mouseleave", function () { paused = false; });
        stage.addEventListener("focusin", function () { paused = true; });
        stage.addEventListener("focusout", function () { paused = false; });

        paint();
        window.setInterval(tick, 2800);
    }
    R.initCertificateStage = initCertificateStage;

    /* ───────────────────────── 启动 ───────────────────────── */
    loopStrips.forEach(function (strip) { initLoopStrip(strip); });
    if (certificateStage) initCertificateStage(certificateStage);
})();
