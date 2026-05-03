// Profile dropdown + live search + back-nav reset
document.addEventListener("DOMContentLoaded", () => {
  initMobileMenu();
  initProfileDropdown();
  initQuickCategoryButtons();
  initLiveSearch();
});

function initMobileMenu() {
  const toggle = document.getElementById("menu-bar");
  const navbar = document.querySelector(".header .navbar");
  if (!toggle || !navbar) return;
  toggle.setAttribute("role", "button");
  toggle.setAttribute("tabindex", "0");
  toggle.setAttribute("aria-controls", "primary-nav");
  toggle.setAttribute("aria-expanded", "false");
  navbar.id = navbar.id || "primary-nav";

  const setOpen = (open) => {
    navbar.classList.toggle("active", open);
    toggle.setAttribute("aria-expanded", String(open));
  };
  toggle.addEventListener("click", () =>
    setOpen(!navbar.classList.contains("active")),
  );
  toggle.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      setOpen(!navbar.classList.contains("active"));
    }
  });
  // Collapse when a link is tapped (otherwise it stays open behind the next page during transition)
  navbar
    .querySelectorAll("a")
    .forEach((a) => a.addEventListener("click", () => setOpen(false)));
}

// When the page is restored from the back-forward cache, the previously
// applied filters can leave the listing list looking suspiciously empty
// ("only one product after I click back"). Force a fresh, unfiltered view.
window.addEventListener("pageshow", (event) => {
  if (!event.persisted) return;
  const form = document.querySelector("[data-live-search]");
  if (!form) return;
  form.reset();
  form.dispatchEvent(new Event("input", { bubbles: true }));
});

function initProfileDropdown() {
  const btn = document.getElementById("user-btn");
  const menu = document.getElementById("profile-menu");
  if (!btn || !menu) return;

  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    const isOpen = !menu.hidden;
    menu.hidden = isOpen;
    btn.setAttribute("aria-expanded", String(!isOpen));
  });
  document.addEventListener("click", (e) => {
    if (!menu.hidden && !menu.contains(e.target) && e.target !== btn) {
      menu.hidden = true;
      btn.setAttribute("aria-expanded", "false");
    }
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !menu.hidden) {
      menu.hidden = true;
      btn.setAttribute("aria-expanded", "false");
      btn.focus();
    }
  });
}

// Quick category chips: set the search form's category select and re-run.
function initQuickCategoryButtons() {
  const form = document.querySelector("[data-live-search]");
  const select = form?.querySelector("[data-filter-category]");
  if (!form || !select) return;

  document.querySelectorAll("[data-quick-category]").forEach((btn) => {
    btn.addEventListener("click", () => {
      //Remove active from all, then mark the clicked one as active
      document
        .querySelectorAll("[data-quick-category]")
        .forEach((b) => b.classList.remove("is-active"));
      btn.classList.add("is-active");

      select.value = btn.dataset.quickCategory;
      select.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
}

// Live search: any form with [data-live-search] becomes AJAX-driven.
// Required: a results container with [data-live-results] and a status node
// with [data-live-status]. The form's GET endpoint must return JSON.
function initLiveSearch() {
  const form = document.querySelector("[data-live-search]");
  if (!form) return;
  const endpoint = form.dataset.liveSearch;
  const resultsEl = document.querySelector("[data-live-results]");
  const statusEl = document.querySelector("[data-live-status]");
  if (!endpoint || !resultsEl) return;

  let timer = null;
  let lastQuery = null;
  let activeController = null;

  const run = () => {
    const params = new URLSearchParams(new FormData(form)).toString();
    if (params === lastQuery) return;
    lastQuery = params;

    if (activeController) activeController.abort();
    activeController = new AbortController();

    if (statusEl) statusEl.textContent = "Searching…";
    fetch(`${endpoint}?${params}`, {
      headers: { Accept: "application/json" },
      signal: activeController.signal,
    })
      .then((r) => r.json())
      .then((data) => {
        renderLiveResults(resultsEl, statusEl, data);
      })
      .catch((err) => {
        if (err.name === "AbortError") return;
        if (statusEl) statusEl.textContent = "Search failed. Try again.";
      });
  };

  const debounced = () => {
    clearTimeout(timer);
    timer = setTimeout(run, 200);
  };

  form.addEventListener("input", debounced);
  form.addEventListener("change", debounced);
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    clearTimeout(timer);
    run();
  });
}

function renderLiveResults(container, statusEl, data) {
  const results = Array.isArray(data.results) ? data.results : [];
  if (statusEl) {
    statusEl.innerHTML = `<strong>${results.length}</strong> result${results.length === 1 ? "" : "s"}.`;
  }
  if (results.length === 0) {
    container.innerHTML = `<p>No listings match your filters. Try widening your search.</p>`;
    return;
  }
  container.innerHTML = results.map(renderListingCard).join("");
}

function renderListingCard(r) {
  const media = r.image
    ? `<img src="../${escapeHtml(r.image)}" alt="">`
    : `<span class="listing-card-glyph">${escapeHtml(listingGlyph(r.title))}</span>`;
  const price = formatRand(r.price);
  const seller = r.seller_name || "Deleted user";
  return `
    <a class="listing-card" href="view.php?id=${Number(r.id)}">
      <div class="listing-card-media">
        ${media}
        <span class="pill pill-verified listing-card-pill">✓ Verified</span>
      </div>
      <div class="listing-card-body">
        <h3 class="listing-card-title">${escapeHtml(r.title || "")}</h3>
        <div class="listing-card-row">
          <span class="listing-card-meta">${escapeHtml(seller)}</span>
          <span class="price">R${price}</span>
        </div>
      </div>
    </a>
  `;
}

function listingGlyph(title) {
  const clean = String(title || "").replace(/[^A-Za-z0-9 ]/g, "");
  const first = clean.trim().split(/\s+/)[0] || "";
  return first.slice(0, 3).toUpperCase() || "·";
}

function formatRand(n) {
  const num = Math.round(Number(n) || 0);
  return num.toLocaleString("en-ZA").replace(/,/g, " ");
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}
