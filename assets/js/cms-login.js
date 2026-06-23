(function () {
    const AUTH = window.__CMS_AUTH__;
    if (!AUTH) {
        return;
    }

    const API_BASE_URL = AUTH.API_BASE_URL;
    const LOGIN_ENDPOINT = API_BASE_URL + "/admin/auth/login";
    const STORAGE_KEYS = AUTH.STORAGE_KEYS;
    const SUBMIT_TEXT = "登录后台";
    const SUBMITTING_TEXT = "登录中...";

    const form = document.querySelector("[data-login-form]");
    const usernameInput = document.querySelector("[data-login-username]");
    const passwordInput = document.querySelector("[data-login-password]");
    const submitButton = document.querySelector("[data-login-submit]");
    const errorNode = document.querySelector("[data-login-error]");

    if (!form || !usernameInput || !passwordInput || !submitButton || !errorNode) {
        return;
    }

    if (AUTH.hasSession()) {
        AUTH.redirectToAdmin();
        return;
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearError();

        const username = usernameInput.value.trim();
        const password = passwordInput.value;
        if (!username || !password) {
            showError("请填写账号和密码");
            return;
        }

        setSubmitting(true);

        try {
            const response = await fetch(LOGIN_ENDPOINT, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });

            const result = await parseJson(response);
            const data = result && typeof result === "object" ? result.data : null;

            if (!response.ok || !data || !data.access_token || !data.refresh_token || !data.user) {
                throw new Error(readMessage(result) || "登录失败，请稍后重试。");
            }

            AUTH.storageSet(STORAGE_KEYS.accessToken, data.access_token);
            AUTH.storageSet(STORAGE_KEYS.refreshToken, data.refresh_token);
            AUTH.storageSet(STORAGE_KEYS.user, JSON.stringify(data.user));
            AUTH.storageSet(
                STORAGE_KEYS.authMeta,
                JSON.stringify({
                    expires_in: data.expires_in || null,
                    session_code: data.session_code || null
                })
            );

            AUTH.redirectToAdmin();
        } catch (error) {
            AUTH.clearSession();
            showError(error instanceof Error ? error.message : "登录失败，请稍后重试。");
            setSubmitting(false);
        }
    });

    function setSubmitting(isSubmitting) {
        submitButton.disabled = isSubmitting;
        submitButton.textContent = isSubmitting ? SUBMITTING_TEXT : SUBMIT_TEXT;
    }

    function showError(message) {
        errorNode.hidden = false;
        errorNode.textContent = message;
    }

    function clearError() {
        errorNode.hidden = true;
        errorNode.textContent = "";
    }

    async function parseJson(response) {
        try {
            return await response.json();
        } catch (error) {
            return null;
        }
    }

    function readMessage(result) {
        if (!result || typeof result !== "object") {
            return "";
        }

        return typeof result.message === "string" ? result.message : "";
    }
})();
