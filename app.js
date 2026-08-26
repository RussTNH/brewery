document.addEventListener("DOMContentLoaded", () => {
    registerServiceWorker();
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
        const response = await fetch("data/beers.json", { cache: "no-store" });
        if (!response.ok) throw new Error("Unable to load ales");

        const ales = await response.json();
        container.innerHTML = ales.map(ale => `
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
        const response = await fetch("data/events.json", { cache: "no-store" });
        if (!response.ok) throw new Error("Unable to load events");

        const events = await response.json();
        container.innerHTML = events.map(event => `
            <article class="item event">
                <span class="tag">${escapeHtml(event.status || "Coming Up")}</span>
                <h3>${escapeHtml(event.title || "")}</h3>
                ${event.description ? `<p>${escapeHtml(event.description)}</p>` : ""}
            </article>
        `).join("");
    } catch (error) {
        console.error("Unable to load brewery events:", error);
    }
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
