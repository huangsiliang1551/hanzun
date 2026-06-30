/**
 * site-aside.js — 侧边咨询/客服/微信/回顶/lead 表单模块
 *
 * 所有页面加载。负责：
 *   - contactFab 开关、contactChoosers
 *   - backToTop 滚动显隐
 *   - supportPanel 客服对话（open/close/submit/hydrate 历史）
 *   - wechatPanel 微信弹窗
 *   - leadForms 询盘表单
 *   - salesContactMenus 销售联系方式下拉
 *
 * 依赖 site-runtime.js，通过 window.HanzunRuntime 调用公共 API。
 */
(function () {
    "use strict";
    var R = window.HanzunRuntime;
    if (!R) return;

    var body = R.body;
    var contactFab = R.contactFab;
    var contactTrigger = R.contactTrigger;
    var contactChoosers = R.contactChoosers;
    var backToTopButton = R.backToTopButton;
    var backToTopButtons = R.backToTopButtons;
    var supportPanel = R.supportPanel;
    var supportTriggers = R.supportTriggers;
    var supportCloseButtons = R.supportCloseButtons;
    var supportForm = R.supportForm;
    var supportInput = R.supportInput;
    var supportMessages = R.supportMessages;
    var supportStatus = R.supportStatus;
    var supportSubmitButton = R.supportSubmitButton;
    var supportSubmitLabel = R.supportSubmitLabel;
    var supportPromptButtons = R.supportPromptButtons;
    var leadForms = R.leadForms;
    var wechatPanel = R.wechatPanel;
    var wechatTrigger = R.wechatTrigger;
    var wechatCloseButtons = R.wechatCloseButtons;
    var wechatCopyButton = R.wechatCopyButton;
    var mobileFabMedia = R.mobileFabMedia;

    var supportDefaultMessagesMarkup = supportMessages ? supportMessages.innerHTML : "";
    var supportState = {
        sending: false,
        hydratePromise: null,
        hydratedSessionCode: "",
        lastFailedMessage: "",
        lastInquiryId: 0,
        noticeNodes: new Map(),
    };

    function syncSupportDefaultMessagesMarkup() {
        if (!supportMessages) { supportDefaultMessagesMarkup = ""; return; }
        supportDefaultMessagesMarkup = supportMessages.innerHTML;
    }
    R.syncSupportDefaultMessagesMarkup = syncSupportDefaultMessagesMarkup;

    /* ───────────────────────── contactFab ───────────────────────── */
    function closeContactFab() {
        if (!contactFab || !contactTrigger) return;
        closeContactChoosers();
        contactFab.classList.remove("open");
        contactFab.dataset.open = "false";
        var floatingMenu = contactFab.querySelector("[data-contact-menu]");
        if (floatingMenu) {
            floatingMenu.style.setProperty("opacity", "0", "important");
            floatingMenu.style.setProperty("visibility", "hidden", "important");
            floatingMenu.style.setProperty("pointer-events", "none", "important");
            floatingMenu.style.setProperty("transform", "translateY(8px)", "important");
        }
        contactTrigger.setAttribute("aria-expanded", "false");
    }
    R.closeContactFab = closeContactFab;

    function openContactFab() {
        if (!contactFab || !contactTrigger) return;
        contactFab.classList.add("open");
        contactFab.dataset.open = "true";
        var floatingMenu = contactFab.querySelector("[data-contact-menu]");
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
    R.openContactFab = openContactFab;

    function setContactChooserState(chooser, open) {
        if (!chooser) return;
        var trigger = chooser.querySelector("[data-contact-chooser-trigger]");
        var cmenu = chooser.querySelector("[data-contact-chooser-menu]");
        chooser.classList.toggle("open", open);
        if (trigger) trigger.setAttribute("aria-expanded", String(open));
        if (cmenu) cmenu.hidden = !open;
    }
    R.setContactChooserState = setContactChooserState;

    function isFabContactChooser(chooser) {
        return Boolean(chooser && chooser.closest("[data-contact-fab]"));
    }

    function closeContactChoosers(exceptChooser) {
        if (!contactChoosers.length) return;
        contactChoosers.forEach(function (chooser) {
            if (chooser === exceptChooser) return;
            setContactChooserState(chooser, false);
        });
    }
    R.closeContactChoosers = closeContactChoosers;

    function syncContactFabVisibility() {
        if (!contactFab) return;
        if (!mobileFabMedia || !mobileFabMedia.matches) {
            contactFab.classList.add("is-active");
            return;
        }
        var isDetailPage = Boolean(document.querySelector("[data-public-detail-root]"));
        var shouldShow = isDetailPage || window.scrollY > 560 || contactFab.classList.contains("open");
        contactFab.classList.toggle("is-active", shouldShow);
    }
    R.syncContactFabVisibility = syncContactFabVisibility;

    function syncBackToTopVisibility() {
        if (!backToTopButton) return;
        var shouldShow = window.scrollY > 420;
        backToTopButton.classList.toggle("visible", shouldShow);
    }
    R.syncBackToTopVisibility = syncBackToTopVisibility;

    /* ───────────────────────── sales contact 菜单 ───────────────────────── */
    function collectSalesContacts() {
        return Array.from(document.querySelectorAll(".sales-card")).map(function (card) {
            var name = (card.querySelector(".sales-name-bar strong") && card.querySelector(".sales-name-bar strong").textContent.trim()) || "";
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
    }

    function renderSalesContactMenu(type, contacts) {
        var key = type === "phone" ? "phone" : "email";
        var hrefKey = key === "phone" ? "phoneHref" : "emailHref";
        return contacts
            .filter(function (c) { return c[key] && c[hrefKey]; })
            .map(function (c) {
                return '<a class="float-option float-option-inline" href="' + R.escapeHtml(c[hrefKey]) + '"><strong>' + R.escapeHtml(c.name + ": " + c[key]) + '</strong></a>';
            })
            .join("");
    }

    function populateSalesContactMenus() {
        var contacts = collectSalesContacts();
        if (!contacts.length) return;
        document.querySelectorAll("[data-contact-list]").forEach(function (menu) {
            if (menu.dataset.staticContactMenu === "1" || menu.querySelector("a")) return;
            var nextMarkup = renderSalesContactMenu(menu.dataset.contactList, contacts);
            if (!nextMarkup) return;
            menu.innerHTML = nextMarkup;
        });
    }
    R.populateSalesContactMenus = populateSalesContactMenus;

    /* ───────────────────────── supportPanel ───────────────────────── */
    function openSupportPanel(prefill) {
        prefill = prefill || "";
        if (!supportPanel) return;
        closeWechatPanel();
        supportPanel.hidden = false;
        body.classList.add("support-open");
        void hydrateSupportConversation();
        if (supportInput) {
            if (prefill) supportInput.value = prefill;
            window.setTimeout(function () {
                supportInput.focus();
                if (supportInput.setSelectionRange) supportInput.setSelectionRange(supportInput.value.length, supportInput.value.length);
            }, 40);
        }
    }
    R.openSupportPanel = openSupportPanel;

    function closeSupportPanel() {
        if (!supportPanel) return;
        supportPanel.hidden = true;
        body.classList.remove("support-open");
    }
    R.closeSupportPanel = closeSupportPanel;

    function scrollSupportConversationToBottom() {
        if (!supportMessages) return;
        supportMessages.scrollTop = supportMessages.scrollHeight;
    }

    function restoreSupportComposerFocus() {
        if (!supportInput) return;
        window.setTimeout(function () {
            supportInput.focus();
            if (supportInput.setSelectionRange) supportInput.setSelectionRange(supportInput.value.length, supportInput.value.length);
        }, 40);
    }

    function setSupportComposerBusy(isBusy) {
        supportState.sending = isBusy;
        if (supportInput) supportInput.disabled = isBusy;
        if (supportSubmitButton) supportSubmitButton.disabled = isBusy;
        if (supportForm) supportForm.setAttribute("aria-busy", isBusy ? "true" : "false");
        if (supportSubmitLabel) {
            supportSubmitLabel.textContent = isBusy
                ? R.getLocalizedSupportText("发送中...", "Sending...")
                : R.getLocalizedSupportText("发送", "Send");
        }
    }

    function setSupportComposerStatus(message) {
        if (!supportStatus) return;
        var content = String(message || "").trim();
        supportStatus.textContent = content;
        supportStatus.hidden = content === "";
    }

    function resetSupportConversation() {
        if (!supportMessages) return;
        supportMessages.innerHTML = supportDefaultMessagesMarkup;
        supportState.noticeNodes.clear();
        scrollSupportConversationToBottom();
    }

    function normalizeSupportSources(sources) {
        if (!Array.isArray(sources)) return [];
        return sources
            .map(function (item) {
                return {
                    title: String((item && item.title) || "").trim(),
                    sourceType: String((item && item.source_type) || "").trim(),
                };
            })
            .filter(function (item) { return item.title || item.sourceType; })
            .slice(0, 3);
    }

    function localizeSupportSourceType(value) {
        var normalized = String(value || "").trim().toLowerCase();
        if (!normalized) return "";
        if (normalized === "product") return R.getLocalizedSupportText("产品资料", "Product");
        if (normalized === "solution") return R.getLocalizedSupportText("方案资料", "Solution");
        if (normalized === "article") return R.getLocalizedSupportText("相关文章", "Article");
        return "";
    }

    function renderMarkdownText(text) {
        var raw = String(text || "").trim();
        if (!raw) return "";
        // 如果有 marked.js 用它渲染，否则转义后保留换行
        if (window.marked && typeof window.marked.parse === "function") {
            try {
                return window.marked.parse(raw, { breaks: true, gfm: true });
            } catch (e) {}
        }
        // fallback: 转义 HTML + 换行转 <br>
        return raw
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\n/g, "<br>");
    }

    function formatSupportMessageTime(value) {
        var raw = String(value || "").trim();
        if (!raw) return "";
        var matched = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (!matched) return "";
        var parsed = new Date(Number(matched[1]), Number(matched[2]) - 1, Number(matched[3]), Number(matched[4]), Number(matched[5]), Number(matched[6] || 0));
        if (Number.isNaN(parsed.getTime())) return "";
        var pad = function (n) { return String(n).padStart(2, "0"); };
        return pad(parsed.getHours()) + ":" + pad(parsed.getMinutes());
    }

    function formatDateDivider(value) {
        var raw = String(value || "").trim();
        if (!raw) return "";
        var matched = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!matched) return "";
        var parsed = new Date(Number(matched[1]), Number(matched[2]) - 1, Number(matched[3]));
        if (Number.isNaN(parsed.getTime())) return "";
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var yesterday = new Date(today.getTime() - 86400000);
        var isEn = R.getContentLanguage(R.normalizedLang((body && body.dataset.lang) || "zh")) === "en";
        if (parsed.getTime() === today.getTime()) return isEn ? "Today" : "今天";
        if (parsed.getTime() === yesterday.getTime()) return isEn ? "Yesterday" : "昨天";
        var pad = function (n) { return String(n).padStart(2, "0"); };
        return matched[1] + "-" + matched[2] + "-" + matched[3];
    }

    function createSupportMessageNode(message) {
        if (!message || !supportMessages) return null;
        var type = String(message.type || "").trim();
        var article = document.createElement("article");
        article.className = type === "system-status" ? "support-message support-message-system-status" : "support-message support-message-" + type;

        if (type === "system-status") {
            var bubble = document.createElement("div");
            bubble.className = "support-bubble";
            var copy = document.createElement("p");
            copy.textContent = String(message.content || "").trim();
            bubble.appendChild(copy);
            if (typeof message.action === "function") {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "support-retry-button";
                button.textContent = String(message.actionLabel || R.getLocalizedSupportText("重试", "Retry")).trim();
                button.addEventListener("click", message.action);
                bubble.appendChild(button);
            }
            article.appendChild(bubble);
            return article;
        }

        var bubble2 = document.createElement("div");
        bubble2.className = "support-bubble";

        // Markdown 渲染内容
        var contentHtml = renderMarkdownText(message.content);
        var contentDiv = document.createElement("div");
        contentDiv.innerHTML = contentHtml;
        bubble2.appendChild(contentDiv);

        // 时间戳
        var formattedTime = formatSupportMessageTime(message.createdAt);
        if (formattedTime) {
            var time = document.createElement("span");
            time.className = "support-message-time";
            time.textContent = formattedTime;
            bubble2.appendChild(time);
        }

        // RAG 来源
        if (type === "assistant") {
            var sources = normalizeSupportSources(message.sources);
            if (sources.length) {
                var list = document.createElement("div");
                list.className = "support-source-list";
                var listTitle = document.createElement("strong");
                listTitle.textContent = R.getLocalizedSupportText("参考资料", "References");
                list.appendChild(listTitle);
                sources.forEach(function (item) {
                    var sourceItem = document.createElement("span");
                    sourceItem.className = "support-source-item";
                    sourceItem.textContent = item.title || "";
                    list.appendChild(sourceItem);
                });
                bubble2.appendChild(list);
            }
        }
        article.appendChild(bubble2);
        return article;
    }

    function createTypingIndicator() {
        var article = document.createElement("article");
        article.className = "support-message support-typing";
        article.setAttribute("data-typing-indicator", "1");
        var bubble = document.createElement("div");
        bubble.className = "support-bubble";
        for (var i = 0; i < 3; i++) {
            var dot = document.createElement("span");
            dot.className = "support-typing-dot";
            bubble.appendChild(dot);
        }
        article.appendChild(bubble);
        return article;
    }

    function removeTypingIndicator() {
        if (!supportMessages) return;
        var indicators = supportMessages.querySelectorAll('[data-typing-indicator]');
        indicators.forEach(function (el) { el.remove(); });
    }

    function ensureDateDivider(dateStr) {
        if (!supportMessages || !dateStr) return;
        var dateKey = String(dateStr).substring(0, 10);
        if (!dateKey) return;
        var existing = supportMessages.querySelector('[data-date-divider="' + dateKey + '"]');
        if (existing) return;
        var divider = document.createElement("div");
        divider.className = "support-date-divider";
        divider.setAttribute("data-date-divider", dateKey);
        var span = document.createElement("span");
        span.textContent = formatDateDivider(dateStr);
        divider.appendChild(span);
        supportMessages.appendChild(divider);
    }

    function scrollSupportToBottom() {
        if (!supportMessages) return;
        requestAnimationFrame(function () {
            supportMessages.scrollTop = supportMessages.scrollHeight;
        });
    }

    function appendSupportMessage(message) {
        if (!supportMessages) return null;
        // 日期分组
        if (message.createdAt) {
            ensureDateDivider(message.createdAt);
        }
        var node = createSupportMessageNode(message);
        if (!node) return null;
        supportMessages.appendChild(node);
        scrollSupportToBottom();
        return node;
    }

    function appendSupportConversationMessage(role, content, options) {
        options = options || {};
        var normalizedRole = role === "assistant" ? "assistant" : "user";
        var title = normalizedRole === "assistant"
            ? R.getLocalizedSupportText("客服助手", "Support Assistant")
            : R.getLocalizedSupportText("访客", "Visitor");
        return appendSupportMessage({
            type: normalizedRole,
            title: title,
            content: content,
            createdAt: options.createdAt || new Date().toISOString().slice(0, 19).replace("T", " "),
            sources: normalizedRole === "assistant" ? options.sources : [],
        });
    }

    function clearSupportNotice(key) {
        var existing = supportState.noticeNodes.get(key);
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
        supportState.noticeNodes.delete(key);
    }

    function upsertSupportStatusMessage(key, content, options) {
        options = options || {};
        clearSupportNotice(key);
        var node = appendSupportMessage({
            type: "system-status",
            content: content,
            action: options.action,
            actionLabel: options.actionLabel,
        });
        if (node) supportState.noticeNodes.set(key, node);
        return node;
    }

    function showSupportInquiryNotice(inquiryId) {
        var normalizedInquiryId = Number(inquiryId || 0);
        if (normalizedInquiryId <= 0) return;
        supportState.lastInquiryId = normalizedInquiryId;
        upsertSupportStatusMessage("inquiry-" + normalizedInquiryId, R.getLocalizedSupportText(
            "已生成询盘，销售团队将根据您留下的信息继续跟进。",
            "An inquiry has been created. Our sales team will follow up based on the details you shared."
        ));
    }

    function renderSupportHistory(response) {
        if (!supportMessages) return;
        var historyMessages = Array.isArray(response && response.messages) ? response.messages : [];
        resetSupportConversation();
        if (!historyMessages.length) {
            if (Number((response && response.inquiry_id) || 0) > 0) showSupportInquiryNotice(response.inquiry_id);
            scrollSupportConversationToBottom();
            return;
        }
        supportMessages.innerHTML = "";
        supportState.noticeNodes.clear();
        historyMessages.forEach(function (item) {
            var role = String((item && item.role) || "").trim();
            var content = String((item && item.content) || "").trim();
            if (!content || (role !== "user" && role !== "assistant")) return;
            appendSupportConversationMessage(role, content, {
                createdAt: item.created_at,
                sources: role === "assistant" ? item.sources : [],
            });
        });
        if (Number((response && response.inquiry_id) || 0) > 0) showSupportInquiryNotice(response.inquiry_id);
    }

    async function hydrateSupportConversation(force) {
        if (!supportMessages) return null;
        var sessionCode = R.currentSupportSessionCode();
        if (!sessionCode) {
            supportState.hydratedSessionCode = "";
            supportState.lastFailedMessage = "";
            if (force) resetSupportConversation();
            return null;
        }
        if (!force && supportState.hydratedSessionCode === sessionCode) return null;
        if (supportState.hydratePromise) return supportState.hydratePromise;

        supportState.hydratePromise = (async function () {
            setSupportComposerStatus(R.getLocalizedSupportText("正在恢复对话...", "Restoring conversation..."));
            try {
                var response = await R.postPublicApi("/api/ai/session", {
                    client_id: R.ensureSupportClientId(),
                    session_code: sessionCode,
                });
                renderSupportHistory(response);
                supportState.hydratedSessionCode = String(response.session_code || sessionCode).trim();
                setSupportComposerStatus(R.getLocalizedSupportText("已恢复上次对话。", "Conversation restored."));
                window.setTimeout(function () {
                    if (!supportState.sending) setSupportComposerStatus("");
                }, 1400);
                return response;
            } catch (error) {
                if (Number((error && error.code) || 0) === 40401) {
                    R.safeSessionStorageRemove(R.supportSessionStorageKey);
                    resetSupportConversation();
                    supportState.hydratedSessionCode = "";
                    setSupportComposerStatus(R.getLocalizedSupportText("未找到上次对话，已为您开启新会话。", "The previous conversation was unavailable, so a new session is ready."));
                    return null;
                }
                setSupportComposerStatus(R.getLocalizedSupportText("暂时无法恢复历史对话，您仍可继续发送消息。", "History is temporarily unavailable, but you can keep chatting."));
                return null;
            } finally {
                supportState.hydratePromise = null;
            }
        })();
        return supportState.hydratePromise;
    }
    R.hydrateSupportConversation = hydrateSupportConversation;

    async function retryLastSupportMessage() {
        if (!supportState.lastFailedMessage || supportState.sending) return;
        clearSupportNotice("retry");
        await submitSupportMessageLive(supportState.lastFailedMessage, { skipUserAppend: true });
    }

    async function submitSupportMessageLive(message, options) {
        options = options || {};
        var trimmed = String(message || "").trim();
        if (!trimmed) {
            setSupportComposerStatus(R.getLocalizedSupportText("请输入问题后再发送。", "Enter a question before sending."));
            restoreSupportComposerFocus();
            return;
        }
        if (supportState.sending) return;
        if (R.currentSupportSessionCode() && supportState.hydratedSessionCode !== R.currentSupportSessionCode()) {
            await hydrateSupportConversation();
        }
        clearSupportNotice("retry");
        if (!options.skipUserAppend) appendSupportConversationMessage("user", trimmed);

        setSupportComposerBusy(true);
        setSupportComposerStatus(R.getLocalizedSupportText("客服助手正在整理回复...", "Support assistant is preparing a reply..."));

        // 显示正在输入动画
        if (supportMessages) {
            supportMessages.appendChild(createTypingIndicator());
            scrollSupportToBottom();
        }

        try {
            var response = await R.postPublicApi("/api/ai/chat", {
                client_id: R.ensureSupportClientId(),
                session_code: R.currentSupportSessionCode(),
                message: trimmed,
                path: R.currentSupportPath(),
                title: document.title,
                referrer: document.referrer,
                language: R.currentSupportLanguage(),
                country_code: R.currentSupportCountry(),
                utm_source: R.currentUtmSource(),
            });

            // 移除正在输入动画
            removeTypingIndicator();

            supportState.hydratedSessionCode = String(response.session_code || R.currentSupportSessionCode()).trim();
            supportState.lastFailedMessage = "";

            appendSupportConversationMessage("assistant", response.assistant_reply || R.getLocalizedSupportText(
                "已收到您的消息，我们会继续整理需求并尽快回复您。",
                "Your message has been received. We will continue organizing the requirement and reply shortly."
            ), { sources: response.sources });

            if (Number(response.inquiry_id || 0) > 0) showSupportInquiryNotice(response.inquiry_id);

            setSupportComposerStatus(R.getLocalizedSupportText("消息已发送。", "Message sent."));
            window.setTimeout(function () {
                if (!supportState.sending) setSupportComposerStatus("");
            }, 1200);
        } catch (error) {
            removeTypingIndicator();
            supportState.lastFailedMessage = trimmed;
            setSupportComposerStatus(R.getLocalizedSupportText("发送失败，您可以重试或继续补充需求。", "Message failed to send. Retry or continue sharing details."));
            upsertSupportStatusMessage("retry", R.getLocalizedSupportText(
                "当前消息未成功送达，点击重试后会继续使用同一会话发送。",
                "The last message did not go through. Retry will send it again in the same session."
            ), {
                action: retryLastSupportMessage,
                actionLabel: R.getLocalizedSupportText("重试", "Retry"),
            });
        } finally {
            if (supportInput) supportInput.value = "";
            setSupportComposerBusy(false);
            restoreSupportComposerFocus();
        }
    }
    R.submitSupportMessageLive = submitSupportMessageLive;

    /* ───────────────────────── lead 表单 ───────────────────────── */
    function ensureLeadFormStatusNode(form) {
        var statusNode = form.querySelector("[data-lead-form-status]");
        if (statusNode) return statusNode;
        statusNode = document.createElement("p");
        statusNode.className = "lead-form-status";
        statusNode.setAttribute("data-lead-form-status", "");
        var actionsNode = form.querySelector(".lead-form-actions");
        if (actionsNode) actionsNode.appendChild(statusNode);
        else form.appendChild(statusNode);
        return statusNode;
    }

    function setLeadFormStatus(form, message, type) {
        var statusNode = ensureLeadFormStatusNode(form);
        statusNode.textContent = message || "";
        statusNode.dataset.state = type || "default";
    }

    function collectLeadFormPayload(form) {
        var formData = new FormData(form);
        return {
            name: String(formData.get("name") || "").trim(),
            phone: String(formData.get("phone") || "").trim(),
            email: String(formData.get("email") || "").trim(),
            message: String(formData.get("message") || "").trim(),
        };
    }

    function setLeadFormSubmitting(form, submitting) {
        var submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) return;
        submitButton.disabled = submitting;
        submitButton.dataset.submitting = submitting ? "1" : "0";
        submitButton.textContent = submitting
            ? R.getLocalizedSupportText("提交中...", "Submitting...")
            : R.getLocalizedSupportText("提交联系信息", "Submit Inquiry");
    }

    async function submitLeadFormLive(form) {
        var payload = collectLeadFormPayload(form);
        var errors = [];
        if (!payload.name) errors.push(R.getLocalizedSupportText("请填写姓名", "Please enter your name"));
        if (!payload.email) errors.push(R.getLocalizedSupportText("请填写邮箱", "Please enter your email"));
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) errors.push(R.getLocalizedSupportText("邮箱格式不正确", "Invalid email format"));
        if (errors.length > 0) { setLeadFormStatus(form, errors.join("; "), "error"); return; }

        setLeadFormSubmitting(form, true);
        setLeadFormStatus(form, R.getLocalizedSupportText("正在提交...", "Submitting..."), "pending");

        try {
            await R.postPublicApi("/api/site/lead", {
                name: payload.name, phone: payload.phone, email: payload.email, message: payload.message,
            });
            form.reset();
            setLeadFormStatus(form, R.getLocalizedSupportText("提交成功，我们会尽快与您联系。", "Submitted successfully. We will get back to you soon."), "success");
        } catch (error) {
            setLeadFormStatus(form, R.getLocalizedSupportText("提交失败，请稍后重试。", "Submission failed. Please try again later."), "error");
        } finally {
            setLeadFormSubmitting(form, false);
            var submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                window.setTimeout(function () { submitButton.disabled = false; }, 3000);
            }
        }
    }
    R.submitLeadFormLive = submitLeadFormLive;

    /* ───────────────────────── wechat 弹窗 ───────────────────────── */
    function openWechatPanel() {
        if (!wechatPanel) return;
        wechatPanel.hidden = false;
        body.classList.add("wechat-open");
    }
    R.openWechatPanel = openWechatPanel;

    function closeWechatPanel() {
        if (!wechatPanel) return;
        wechatPanel.hidden = true;
        body.classList.remove("wechat-open");
    }
    R.closeWechatPanel = closeWechatPanel;

    async function copyTextValue(value) {
        if (!value) return false;
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch (_) {
            var input = document.createElement("input");
            input.value = value;
            document.body.appendChild(input);
            input.select();
            input.setSelectionRange(0, input.value.length);
            try {
                document.execCommand("copy");
                document.body.removeChild(input);
                return true;
            } catch (e) {
                document.body.removeChild(input);
                return false;
            }
        }
    }

    /* ───────────────────────── 事件绑定 ───────────────────────── */
    if (contactFab && contactTrigger) {
        if (!mobileFabMedia || !mobileFabMedia.matches) contactFab.classList.add("attention");
        populateSalesContactMenus();

        contactChoosers.forEach(function (chooser) {
            var trigger = chooser.querySelector("[data-contact-chooser-trigger]");
            var options = chooser.querySelectorAll(".float-option");
            if (!trigger) return;

            var openChooser = function (event) {
                event.preventDefault(); event.stopPropagation();
                var nextOpen = !chooser.classList.contains("open");
                closeContactChoosers(chooser);
                setContactChooserState(chooser, nextOpen);
                if (nextOpen && isFabContactChooser(chooser)) openContactFab();
            };
            trigger.addEventListener("click", openChooser);
            trigger.addEventListener("contextmenu", openChooser);
            options.forEach(function (option) {
                option.addEventListener("click", function () {
                    closeContactChoosers();
                    closeContactFab();
                    syncContactFabVisibility();
                });
            });
        });

        contactTrigger.addEventListener("click", function () {
            closeContactChoosers();
            var open = contactFab.classList.contains("open");
            if (open) closeContactFab();
            else openContactFab();
        });

        document.addEventListener("click", function (event) {
            var clickedInsideChooser = Array.from(contactChoosers).some(function (c) { return c.contains(event.target); });
            if (clickedInsideChooser || contactFab.contains(event.target)) return;
            closeContactChoosers();
            closeContactFab();
            syncContactFabVisibility();
        });
    }

    if (backToTopButtons.length) {
        backToTopButtons.forEach(function (button) {
            button.addEventListener("click", function () { R.scrollToTop(); });
        });
    }
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
        supportTriggers.forEach(function (button) {
            button.addEventListener("click", function () {
                closeContactChoosers();
                closeContactFab();
                openSupportPanel();
            });
        });
    }
    if (supportCloseButtons.length) {
        supportCloseButtons.forEach(function (button) {
            button.addEventListener("click", function () { closeSupportPanel(); });
        });
    }
    if (supportPromptButtons.length) {
        supportPromptButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                // 优先用 data-support-prompt（已含完整提问文案），再按语言取 data-zh-prompt/data-en-prompt
                var lang = R.normalizedLang((body && body.dataset.lang) || "zh");
                var prompt = "";
                if (lang === "en" && button.dataset.enPrompt) {
                    prompt = button.dataset.enPrompt;
                } else if (button.dataset.zhPrompt) {
                    prompt = button.dataset.zhPrompt;
                }
                if (!prompt) {
                    prompt = button.dataset.supportPrompt || button.textContent.trim();
                }
                openSupportPanel(prompt);
            });
        });
    }
    if (supportForm && supportInput) {
        supportForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            await submitSupportMessageLive(supportInput.value);
        });
    }
    if (leadForms.length) {
        leadForms.forEach(function (form) {
            form.addEventListener("submit", async function (event) {
                event.preventDefault();
                await submitLeadFormLive(form);
            });
        });
    }
    if (wechatTrigger) {
        wechatTrigger.addEventListener("click", function () {
            closeSupportPanel();
            openWechatPanel();
        });
    }
    if (wechatCloseButtons.length) {
        wechatCloseButtons.forEach(function (button) {
            button.addEventListener("click", function () { closeWechatPanel(); });
        });
    }
    if (wechatCopyButton) {
        wechatCopyButton.addEventListener("click", async function () {
            var value = wechatCopyButton.dataset.copyValue || "";
            var label = wechatCopyButton.querySelector("span");
            var success = await copyTextValue(value);
            var isEnglish = R.getContentLanguage(R.normalizedLang((body && body.dataset.lang) || "zh")) === "en";
            if (!label) return;
            var resetText = isEnglish ? "Copy WeChat ID" : "复制微信号";
            label.textContent = success ? (isEnglish ? "Copied" : "已复制") : resetText;
            window.setTimeout(function () { label.textContent = resetText; }, 1800);
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeSupportPanel();
            closeWechatPanel();
        }
    });

    /* ───────────────────────── 启动 ───────────────────────── */
    void hydrateSupportConversation();
})();
