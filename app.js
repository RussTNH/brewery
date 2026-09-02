let deferredInstallPrompt = null;

document.addEventListener("DOMContentLoaded", () => {
    registerServiceWorker();
    setupPwaInstall();
    loadBreweryContent();
});

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
    const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    const isStandalone = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

    if (isIos && !isStandalone && iosInstall) {
        iosInstall.hidden = false;
    }

    window.addEventListener("beforeinstallprompt", event => {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (installButton && !isStandalone) installButton.hidden = false;
    });

    if (installButton) {
        installButton.addEventListener("click", async () => {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            installButton.hidden = true;
        });
    }

    window.addEventListener("appinstalled", () => {
        deferredInstallPrompt = null;
        if (installButton) installButton.hidden = true;
        if (iosInstall) iosInstall.hidden = true;
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
