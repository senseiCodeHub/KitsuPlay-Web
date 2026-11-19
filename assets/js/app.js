/* KitsuPlay Web — page interactions. No inline handlers (strict CSP). */
(function () {
    "use strict";

    const meta = document.querySelector('meta[name="csrf"]');
    const csrf = meta ? meta.getAttribute("content") || "" : "";
    // '' when the host rewrites clean URLs, '/index.php' when it does not.
    const apiRoot = document.body.dataset.base || "";

    // ── helpers ─────────────────────────────────────────────────────────────

    function toast(message, kind) {
        let host = document.querySelector(".toast-host");
        if (!host) {
            host = document.createElement("div");
            host.className = "toast-host";
            host.setAttribute("role", "status");
            host.setAttribute("aria-live", "polite");
            document.body.appendChild(host);
        }
        const node = document.createElement("div");
        node.className = "toast toast--" + (kind || "info");
        node.textContent = message;
        host.appendChild(node);
        setTimeout(function () {
            node.classList.add("is-out");
            setTimeout(() => node.remove(), 260);
        }, 3200);
    }

    async function postJson(url, body) {
        const target = url.indexOf("/api/") === 0 ? apiRoot + url : url;
        const response = await fetch(target, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF": csrf,
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(body || {}),
        });
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = { ok: false, error: "The server answered unexpectedly." };
        }
        if (!response.ok || data.ok === false) {
            throw new Error(data.error || "Request failed.");
        }
        return data;
    }

    function icon(name) {
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("class", "icon");
        svg.setAttribute("aria-hidden", "true");
        const use = document.createElementNS("http://www.w3.org/2000/svg", "use");
        use.setAttribute("href", "#i-" + name);
        svg.appendChild(use);
        return svg;
    }

    // ── preferences (per device) ────────────────────────────────────────────

    const PREF_KEY = "kitsuplay.prefs.v1";
    const DEFAULT_PREFS = {
        autoplayNext: true,
        preferredQuality: "auto",
        subtitlesOn: false,
        subtitleLanguage: "en",
        reducedData: false,
        familyMode: false,
    };

    function readPrefs() {
        try {
            return Object.assign({}, DEFAULT_PREFS, JSON.parse(localStorage.getItem(PREF_KEY) || "{}"));
        } catch (error) {
            return Object.assign({}, DEFAULT_PREFS);
        }
    }

    function writePrefs(patch) {
        const next = Object.assign(readPrefs(), patch);
        try {
            localStorage.setItem(PREF_KEY, JSON.stringify(next));
        } catch (error) {
            /* private mode */
        }
        return next;
    }
    window.KitsuPrefs = { read: readPrefs, write: writePrefs };

    // ── hero ────────────────────────────────────────────────────────────────

    document.querySelectorAll("[data-hero]").forEach(function (hero) {
        const slides = Array.from(hero.querySelectorAll("[data-hero-slide]"));
        const dots = Array.from(hero.querySelectorAll("[data-hero-dot]"));
        if (slides.length < 2) return;
        let index = 0;
        let timer = null;

        function show(next) {
            index = (next + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle("is-active", i === index));
            dots.forEach((dot, i) => dot.classList.toggle("is-active", i === index));
        }

        function start() {
            stop();
            timer = setInterval(() => show(index + 1), 9000);
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        dots.forEach((dot, i) =>
            dot.addEventListener("click", () => {
                show(i);
                start();
            }),
        );
        hero.addEventListener("mouseenter", stop);
        hero.addEventListener("mouseleave", start);
        start();
    });

    // ── rails ───────────────────────────────────────────────────────────────

    document.querySelectorAll("[data-rail]").forEach(function (block) {
        const track = block.querySelector("[data-rail-track]");
        if (!track) return;
        const step = function () {
            return Math.max(240, track.clientWidth * 0.85);
        };
        const prev = block.querySelector("[data-rail-prev]");
        const next = block.querySelector("[data-rail-next]");
        if (prev) prev.addEventListener("click", () => track.scrollBy({ left: -step(), behavior: "smooth" }));
        if (next) next.addEventListener("click", () => track.scrollBy({ left: step(), behavior: "smooth" }));
    });

    // ── watchlist / library ─────────────────────────────────────────────────

    function setButtonState(button, saved) {
        const use = button.querySelector("use");
        if (use) use.setAttribute("href", saved ? "#i-check" : "#i-plus");
        const label = button.querySelector("span");
        if (label) {
            label.textContent = saved
                ? button.dataset.labelSaved || "In watchlist"
                : button.dataset.labelEmpty || "Add to watchlist";
        }
    }

    document.querySelectorAll("[data-watchlist]").forEach(function (button) {
        const label = button.querySelector("span");
        if (label) {
            button.dataset.labelEmpty = label.textContent;
            button.dataset.labelSaved = "In watchlist";
        }
        button.addEventListener("click", async function () {
            let item;
            try {
                item = JSON.parse(button.getAttribute("data-item") || "{}");
            } catch (error) {
                return;
            }
            button.disabled = true;
            try {
                const result = await postJson("/api/library", { action: "toggle", item: item });
                setButtonState(button, result.saved);
                toast(result.saved ? "Added to watchlist" : "Removed from watchlist", "success");
            } catch (error) {
                toast(error.message, "error");
            } finally {
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll("[data-status-select]").forEach(function (select) {
        select.addEventListener("change", async function () {
            try {
                await postJson("/api/library", {
                    action: "status",
                    ref: select.getAttribute("data-ref"),
                    status: select.value,
                });
                toast("Status updated", "success");
            } catch (error) {
                toast(error.message, "error");
            }
        });
    });

    document.querySelectorAll("[data-remove]").forEach(function (button) {
        button.addEventListener("click", async function () {
            const ref = button.getAttribute("data-ref");
            if (!ref) return;
            try {
                await postJson("/api/library", { action: "remove", ref: ref });
                const card = button.closest(".library-card");
                if (card) card.remove();
                toast("Removed from watchlist", "success");
            } catch (error) {
                toast(error.message, "error");
            }
        });
    });

    const clearButton = document.querySelector("[data-clear-history]");
    if (clearButton) {
        clearButton.addEventListener("click", async function () {
            if (!window.confirm("Clear your whole watch history? This cannot be undone.")) return;
            try {
                await postJson("/api/library", { action: "clear-history" });
                const list = document.querySelector(".history");
                if (list) list.remove();
                toast("History cleared", "success");
            } catch (error) {
                toast(error.message, "error");
            }
        });
    }

    // ── share ───────────────────────────────────────────────────────────────

    document.querySelectorAll("[data-share]").forEach(function (button) {
        button.addEventListener("click", async function () {
            const title = button.getAttribute("data-title") || document.title;
            const url = window.location.href;
            try {
                if (navigator.share) {
                    await navigator.share({ title: title, url: url });
                    return;
                }
                await navigator.clipboard.writeText(url);
                toast("Link copied", "success");
            } catch (error) {
                toast("Could not share this page", "error");
            }
        });
    });

    // ── search (debounced) ──────────────────────────────────────────────────

    const searchInput = document.querySelector("[data-search-input]");
    if (searchInput) {
        const host = document.querySelector("[data-search-results]");
        let timer = null;
        let controller = null;

        function cardHtml(item) {
            const metaLine = [item.year, item.rating ? "★ " + Number(item.rating).toFixed(1) : ""]
                .filter(Boolean)
                .join(" · ");
            const wrapper = document.createElement("a");
            wrapper.className = "card";
            wrapper.href = "/title/" + encodeURIComponent(item.ref);

            const art = document.createElement("span");
            art.className = "card__art";
            if (item.posterUrl) {
                const img = document.createElement("img");
                img.src = item.posterUrl;
                img.alt = "";
                img.loading = "lazy";
                img.referrerPolicy = "no-referrer";
                art.appendChild(img);
            }
            const shade = document.createElement("span");
            shade.className = "card__shade";
            art.appendChild(shade);
            if (item.badge) {
                const badge = document.createElement("span");
                badge.className = "card__badge";
                badge.textContent = item.badge;
                art.appendChild(badge);
            }
            const body = document.createElement("span");
            body.className = "card__body";
            const title = document.createElement("span");
            title.className = "card__title";
            title.textContent = item.title;
            body.appendChild(title);
            if (metaLine) {
                const metaNode = document.createElement("span");
                metaNode.className = "card__meta";
                metaNode.textContent = metaLine;
                body.appendChild(metaNode);
            }
            art.appendChild(body);
            wrapper.appendChild(art);
            return wrapper;
        }

        async function run(term) {
            if (controller) controller.abort();
            controller = new AbortController();
            try {
                const response = await fetch(apiRoot + "/api/search?q=" + encodeURIComponent(term), {
                    signal: controller.signal,
                    headers: { Accept: "application/json" },
                });
                const data = await response.json();
                if (!host) return;
                host.textContent = "";
                if (!data.results || data.results.length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "empty";
                    empty.innerHTML = '<h3>No matches for “' + term.replace(/[<>&]/g, "") + '”</h3><p>Try a shorter title or the original spelling.</p>';
                    host.appendChild(empty);
                    return;
                }
                const grid = document.createElement("div");
                grid.className = "grid";
                data.results.forEach((item) => grid.appendChild(cardHtml(item)));
                host.appendChild(grid);
            } catch (error) {
                if (error.name !== "AbortError") toast("Search failed — try again.", "error");
            }
        }

        searchInput.addEventListener("input", function () {
            clearTimeout(timer);
            const term = searchInput.value.trim();
            if (term.length < 2) return;
            timer = setTimeout(() => run(term), 320);
        });
    }

    // ── browse infinite scroll ──────────────────────────────────────────────

    const browseGrid = document.querySelector("[data-browse]");
    const sentinel = document.querySelector("[data-browse-sentinel]");
    if (browseGrid && sentinel) {
        let page = 1;
        let loading = false;
        let done = false;

        function url(pageNumber) {
            const params = new URLSearchParams({
                type: browseGrid.dataset.type || "movie",
                page: String(pageNumber),
            });
            ["collection", "genre", "year", "country", "language", "sort"].forEach(function (key) {
                const value = browseGrid.dataset[key];
                if (value) params.set(key, value);
            });
            return apiRoot + "/api/browse?" + params.toString();
        }

        const observer = new IntersectionObserver(
            async function (entries) {
                if (!entries[0].isIntersecting || loading || done) return;
                loading = true;
                const spinner = sentinel.querySelector(".spinner");
                if (spinner) spinner.hidden = false;
                try {
                    page += 1;
                    const response = await fetch(url(page), { headers: { Accept: "application/json" } });
                    const data = await response.json();
                    if (!data.items || data.items.length === 0) {
                        done = true;
                        return;
                    }
                    let grid = browseGrid.querySelector(".grid");
                    if (!grid) {
                        grid = document.createElement("div");
                        grid.className = "grid";
                        browseGrid.appendChild(grid);
                    }
                    data.items.forEach((item) => grid.appendChild(cardFromData(item)));
                    if (data.hasMore === false) done = true;
                } catch (error) {
                    done = true;
                } finally {
                    loading = false;
                    if (spinner) spinner.hidden = true;
                }
            },
            { rootMargin: "600px" },
        );
        observer.observe(sentinel);
    }

    function cardFromData(item) {
        const link = document.createElement("a");
        link.className = "card";
        link.href = "/title/" + encodeURIComponent(item.ref);
        const art = document.createElement("span");
        art.className = "card__art";
        if (item.posterUrl) {
            const img = document.createElement("img");
            img.src = item.posterUrl;
            img.alt = "";
            img.loading = "lazy";
            img.referrerPolicy = "no-referrer";
            art.appendChild(img);
        }
        const shade = document.createElement("span");
        shade.className = "card__shade";
        art.appendChild(shade);
        if (item.badge) {
            const badge = document.createElement("span");
            badge.className = "card__badge";
            badge.textContent = item.badge;
            art.appendChild(badge);
        }
        const body = document.createElement("span");
        body.className = "card__body";
        const title = document.createElement("span");
        title.className = "card__title";
        title.textContent = item.title;
        body.appendChild(title);
        const metaLine = [item.year, item.rating ? "★ " + Number(item.rating).toFixed(1) : ""]
            .filter(Boolean)
            .join(" · ");
        if (metaLine) {
            const metaNode = document.createElement("span");
            metaNode.className = "card__meta";
            metaNode.textContent = metaLine;
            body.appendChild(metaNode);
        }
        art.appendChild(body);
        link.appendChild(art);
        return link;
    }

    // ── announcements ───────────────────────────────────────────────────────

    document.querySelectorAll("[data-read-all]").forEach(function (button) {
        button.addEventListener("click", async function () {
            try {
                await postJson("/api/notices", { action: "read-all" });
                document.querySelectorAll(".notice-card").forEach((card) => {
                    card.classList.add("is-read");
                    const pill = card.querySelector(".pill");
                    if (pill) pill.remove();
                });
                document.querySelectorAll(".nav__badge, .dot").forEach((node) => node.remove());
                toast("All announcements marked read", "success");
            } catch (error) {
                toast(error.message, "error");
            }
        });
    });

    const noticeCards = document.querySelectorAll("[data-notice]");
    noticeCards.forEach(function (card) {
        const seen = new IntersectionObserver(function (entries) {
            entries.forEach(async function (entry) {
                if (!entry.isIntersecting) return;
                seen.disconnect();
                if (!card.classList.contains("is-read")) {
                    try {
                        await postJson("/api/notices", { action: "read", id: card.getAttribute("data-notice") });
                        card.classList.add("is-read");
                        const pill = card.querySelector(".pill");
                        if (pill) pill.remove();
                    } catch (error) {
                        /* read state is best-effort */
                    }
                }
            });
        });
        seen.observe(card);
    });

    // ── settings ────────────────────────────────────────────────────────────

    document.querySelectorAll("[data-pref]").forEach(function (input) {
        const key = input.getAttribute("data-pref");
        const prefs = readPrefs();
        if (input.type === "checkbox") {
            input.checked = Boolean(prefs[key]);
        } else if (prefs[key] !== undefined) {
            input.value = prefs[key];
        }
        const handler = function () {
            const value = input.type === "checkbox" ? input.checked : input.value;
            writePrefs({ [key]: value });
            toast("Preference saved", "success");
        };
        input.addEventListener("change", handler);
    });

    document.querySelectorAll("[data-autosubmit]").forEach(function (select) {
        select.addEventListener("change", function () {
            const form = select.form;
            if (form) form.submit();
        });
    });

    // ── PWA ─────────────────────────────────────────────────────────────────

    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("/sw.js").catch(function () {
                /* offline support is optional */
            });
        });
    }

    let installEvent = null;
    window.addEventListener("beforeinstallprompt", function (event) {
        event.preventDefault();
        installEvent = event;
    });

    document.querySelectorAll("[data-install]").forEach(function (button) {
        button.addEventListener("click", async function () {
            if (!installEvent) {
                toast("Use your browser menu → Install app.", "info");
                return;
            }
            installEvent.prompt();
            installEvent = null;
        });
    });

    // ── push notifications ──────────────────────────────────────────────────

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[src="' + src + '"]');
            if (existing) return resolve();
            const script = document.createElement("script");
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function enablePush(configJson) {
        let config;
        try {
            config = JSON.parse(configJson || "{}");
        } catch (error) {
            return;
        }
        if (!config.apiKey) {
            toast("Push is not configured on this install.", "error");
            return;
        }
        if (!("Notification" in window) || !("serviceWorker" in navigator)) {
            toast("This browser does not support notifications.", "error");
            return;
        }
        const permission = await Notification.requestPermission();
        if (permission !== "granted") {
            toast("Notifications were not allowed.", "error");
            return;
        }
        try {
            await loadScript("/assets/vendor/firebase-app-compat.js");
            await loadScript("/assets/vendor/firebase-messaging-compat.js");
            firebase.initializeApp({
                apiKey: config.apiKey,
                projectId: config.projectId,
                messagingSenderId: config.messagingSenderId,
                appId: config.appId,
            });
            const registration = await navigator.serviceWorker.ready;
            const token = await firebase.messaging().getToken({
                vapidKey: config.vapidKey,
                serviceWorkerRegistration: registration,
            });
            if (!token) throw new Error("No token returned");
            await postJson("/api/push-token", { token: token, device: navigator.userAgent.slice(0, 120) });
            try {
                localStorage.setItem("kitsuplay.push", token);
            } catch (error) {
                /* ignore */
            }
            toast("Notifications enabled", "success");
        } catch (error) {
            toast(error.message || "Notifications could not be enabled.", "error");
        }
    }

    document.querySelectorAll("[data-enable-push]").forEach(function (button) {
        button.addEventListener("click", function () {
            enablePush(button.getAttribute("data-config") || "");
        });
    });

    document.querySelectorAll("[data-enable-push-settings]").forEach(function (button) {
        button.addEventListener("click", async function () {
            try {
                const response = await fetch(apiRoot + "/notifications", { credentials: "same-origin" });
                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, "text/html");
                const source = doc.querySelector("[data-enable-push]");
                await enablePush(source ? source.getAttribute("data-config") || "" : "");
            } catch (error) {
                toast("Could not reach the notification settings.", "error");
            }
        });
    });

    // Images that fail to load get a quiet placeholder instead of a broken icon.
    document.addEventListener(
        "error",
        function (event) {
            const target = event.target;
            if (target && target.tagName === "IMG" && !target.dataset.failed) {
                target.dataset.failed = "1";
                target.style.opacity = "0";
            }
        },
        true,
    );

    window.KitsuApiRoot = apiRoot;
})();
