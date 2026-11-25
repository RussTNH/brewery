let deferredInstallPrompt = null;
const trackedOnceEvents = new Set();

document.addEventListener("DOMContentLoaded", () => {
    registerServiceWorker();
    setupPwaInstall();
    trackPageView();
    loadBreweryContent();
});

function trackEvent(eventName, { once = false } = {}) {
    if (once && trackedOnceEvents.has(eventName)) return;
    if (once) trackedOnceEvents.add(eventName);

    fetch("track.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ event: eventName }),
        cache: "no-store",
        credentials: "same-origin",
        keepalive: true
    }).then(response => {
        if (!response.ok) {
            console.debug(`Brewery statistics request returned ${response.status}.`);
        }
    }).catch(error => {
        // Tracking must never interrupt the public PWA. Do not retry because a
        // lost response may still have been counted by the server.
        console.debug("Brewery statistics request was not completed:", error);
    });
}

function trackPageView() {
    const pageEvents = {
        welcome: "welcome_view",
        guide: "guide_view"
    };
    const eventName = pageEvents[document.body.dataset.page];
    if (!eventName) return;

    const recordWhenVisible = () => {
        if (document.visibilityState === "hidden") return;
        document.removeEventListener("visibilitychange", recordWhenVisible);
        trackEvent(eventName, { once: true });
    };

    if (document.visibilityState === "hidden") {
        document.addEventListener("visibilitychange", recordWhenVisible);
    } else {
        recordWhenVisible();
    }
}

function trackElementView(element, eventName) {
    let isHalfVisible = false;
    let observer = null;

    const cleanup = () => {
        document.removeEventListener("visibilitychange", recordIfVisible);
        window.removeEventListener("scroll", checkViewportFallback);
        window.removeEventListener("resize", checkViewportFallback);
        if (observer) observer.disconnect();
    };

    const recordIfVisible = () => {
        if (!isHalfVisible || document.visibilityState === "hidden") return;
        cleanup();
        trackEvent(eventName, { once: true });
    };

    const checkViewportFallback = () => {
        const rect = element.getBoundingClientRect();
        const visibleHeight = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
        isHalfVisible = rect.height > 0 && visibleHeight / rect.height >= 0.5;
        recordIfVisible();
    };

    document.addEventListener("visibilitychange", recordIfVisible);

    if (!("IntersectionObserver" in window)) {
        window.addEventListener("scroll", checkViewportFallback, { passive: true });
        window.addEventListener("resize", checkViewportFallback);
        checkViewportFallback();
        return;
    }

    observer = new IntersectionObserver(entries => {
        const entry = entries.find(candidate => candidate.target === element);
        if (!entry) return;
        isHalfVisible = entry.isIntersecting && entry.intersectionRatio >= 0.5;
        recordIfVisible();
    }, { threshold: [0.5] });

    observer.observe(element);
}

function isIosDevice() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent)
        || (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
}

function registerServiceWorker() {
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", () => {
            navigator.serviceWorker.register("sw.js").catch(error => {
                console.error("Brewery PWA service worker registration failed:", error);
            });
        });
    }
}

function setupPwaInstall() {
    const installButton = document.getElementById("install-app-button");
    const iosInstall = document.getElementById("ios-install");
    const isIos = isIosDevice();
    const isAndroid = /android/i.test(window.navigator.userAgent);
    const isStandalone = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

    if (isIos && !isStandalone && iosInstall) {
        iosInstall.hidden = false;
        trackElementView(iosInstall, "ios_instructions");
    }

    window.addEventListener("beforeinstallprompt", event => {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (installButton && !isStandalone) installButton.hidden = false;
    });

    if (installButton) {
        installButton.addEventListener("click", async () => {
            if (!deferredInstallPrompt) return;

            const installPrompt = deferredInstallPrompt;
            deferredInstallPrompt = null;
            installButton.hidden = true;

            try {
                const promptResult = installPrompt.prompt();
                trackEvent("install_click");
                await promptResult;
            } catch (error) {
                console.error("Brewery PWA install prompt failed:", error);
            }
        });
    }

    window.addEventListener("appinstalled", () => {
        deferredInstallPrompt = null;
        if (installButton) installButton.hidden = true;
        if (iosInstall) iosInstall.hidden = true;
        if (isAndroid) trackEvent("android_install", { once: true });
    });
}

async function loadBreweryContent() {
    await Promise.all([
        loadAles(),
        loadEvents()
    ]);
}

async function loadAles() {
    const container = document.getElementById("ale-list");
    if (!container) return;

    try {
        const response = await fetch("live-data/data/beers.json", { cache: "no-store" });
        if (!response.ok) throw new Error("Unable to load ales");

        const ales = await response.json();
        const visibleAles = ales.filter(ale => String(ale.status || "On Tap").toLowerCase() !== "off tap");

        container.innerHTML = visibleAles.map(ale => `
            <article class="item ale">
                <div class="ale-copy">
                    <span class="tag">${escapeHtml(ale.status || "On Tap")}</span>
                    <h3>${escapeHtml(ale.name || "")}</h3>
                    <p>${escapeHtml(ale.description || "")}</p>
                </div>
                ${ale.image ? `<img class="beer-art" src="${escapeAttribute(ale.image)}" alt="${escapeAttribute(ale.alt || `${ale.name || "Ale"} artwork`)}">` : ""}
            </article>
        `).join("");
    } catch (error) {
        console.error("Unable to load brewery ales:", error);
    }
}

async function loadEvents() {
    const container = document.getElementById("event-list");
    if (!container) return;

    try {
        const response = await fetch("live-data/data/events.json", { cache: "no-store" });
        if (!response.ok) throw new Error("Unable to load events");

        const events = await response.json();
        const visibleEvents = events.filter(event => String(event.status || "Coming Up").toLowerCase() !== "hidden");

        container.innerHTML = visibleEvents.map(event => {
            const when = formatEventWhen(event.date, event.time);
            return `
                <article class="item event${event.image ? " ale" : ""}">
                    <div class="ale-copy">
                        <span class="tag">${escapeHtml(event.status || "Coming Up")}</span>
                        <h3>${escapeHtml(event.title || "")}</h3>
                        ${when ? `<p><strong>${escapeHtml(when)}</strong></p>` : ""}
                        ${event.description ? `<p>${escapeHtml(event.description)}</p>` : ""}
                    </div>
                    ${event.image ? `<img class="beer-art event-art" src="${escapeAttribute(event.image)}" alt="${escapeAttribute(`${event.title || "Event"} artwork`)}">` : ""}
                </article>
            `;
        }).join("");
    } catch (error) {
        console.error("Unable to load brewery events:", error);
    }
}

function formatEventWhen(date, time) {
    if (!date && !time) return "";

    let dateText = "";
    if (date) {
        const parts = String(date).split("-");
        if (parts.length === 3) dateText = `${parts[2]}/${parts[1]}/${parts[0]}`;
        else dateText = String(date);
    }

    let timeText = "";
    if (time) {
        const parts = String(time).split(":");
        if (parts.length >= 2) timeText = `${parts[0]}:${parts[1]}`;
        else timeText = String(time);
    }

    return [dateText, timeText].filter(Boolean).join(" · ");
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function escapeAttribute(value) {
    return escapeHtml(value);
}
