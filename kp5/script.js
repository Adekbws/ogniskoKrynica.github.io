(()=> {
  const CONTACT_PHONE = "+48664663940";

  document.querySelectorAll(".nav-cta--menu").forEach((link) => {
    link.href = `tel:${CONTACT_PHONE}`;
  });

  const nav = document.querySelector(".nav");
  const toggle = document.querySelector(".nav-toggle");
  const backdrop = document.querySelector(".nav-backdrop");
  const navLinks = document.querySelectorAll("#site-nav a");

  function setNavOpen(open) {
    if (!nav || !toggle) return;
    nav.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Zamknij menu" : "Otwórz menu");
    document.body.classList.toggle("nav-open", open);
    if (backdrop) backdrop.hidden = !open;
  }

  toggle?.addEventListener("click", () => {
    setNavOpen(!nav.classList.contains("is-open"));
  });
  backdrop?.addEventListener("click", () => setNavOpen(false));
  navLinks.forEach((link) => {
    link.addEventListener("click", () => setNavOpen(false));
  });
  window.addEventListener("resize", () => {
    if (window.innerWidth > 900) setNavOpen(false);
  });

  const tabs = document.querySelectorAll(".map-tabs button");
  const panels = document.querySelectorAll(".map-panel");
  function showFloor(id){
    tabs.forEach(t => t.classList.toggle("active", t.dataset.floor === id));
    panels.forEach(p => p.classList.toggle("active", p.dataset.floorPanel === id));
  }
  tabs.forEach(tab => tab.addEventListener("click", () => showFloor(tab.dataset.floor)));

  document.querySelectorAll(".map-panel").forEach(panel => {
    function setActive(id, state){
      panel.querySelectorAll(`[data-apt="${id}"]`).forEach(el => el.classList.toggle("is-active", state));
    }
    panel.querySelectorAll(".apt-link").forEach(el => {
      const id = el.dataset.apt;
      el.addEventListener("mouseenter", () => setActive(id, true));
      el.addEventListener("mouseleave", () => setActive(id, false));
      el.addEventListener("focus", () => setActive(id, true));
      el.addEventListener("blur", () => setActive(id, false));
    });
  });

  const tableHeadRow = document.querySelector(".apartments-table thead tr");
  tableHeadRow?.children[5]?.remove();
  document
    .querySelectorAll(".apartments-table tbody tr")
    .forEach((row) => row.children[5]?.remove());

  const rows = document.querySelectorAll(".apartments-table tbody tr");
  const cardModal = document.querySelector("#card-modal");
  const closeModalTriggers = document.querySelectorAll("[data-close-modal]");
  const openRequestBtn = document.querySelector("[data-open-request]");
  const backToPreviewBtn = document.querySelector("[data-back-preview]");
  const requestForm = document.querySelector("[data-request-form]");
  const requestNote = document.querySelector("[data-request-note]");
  const previewPanel = document.querySelector('[data-panel="preview"]');
  const requestPanel = document.querySelector('[data-panel="request"]');
  const modalTitle = document.querySelector("#card-modal-title");
  const modalImage = document.querySelector(".card-modal-image");
  const requestEmailInput = requestForm?.querySelector('input[name="email"]');

  function normalizeApartmentId(rawId) {
    const digits = String(rawId || "").match(/\d+/)?.[0];
    return digits ? `LU.${Number(digits)}` : "";
  }

  function buildCardImagePath(apartmentId) {
    const localNo = apartmentId.replace("LU.", "");
    return `assets/karty/png/ognisko_km_popup_240626-LU${localNo}.png`;
  }

  function setModalImage(apartmentId) {
    if (!modalImage) return;
    const localNo = apartmentId.replace("LU.", "");
    modalImage.src = buildCardImagePath(apartmentId);
    modalImage.alt = localNo
      ? `Karta lokalu ${localNo}`
      : "Karta lokalu";
  }

  function setRequestPanel(open) {
    previewPanel.hidden = open;
    requestPanel.hidden = !open;
    if (open) requestEmailInput?.focus();
  }

  function openCardModal(row) {
    if (!cardModal) return;
    const apartmentNo = row.children[0]?.textContent?.trim() || "";
    const apartmentId = normalizeApartmentId(row.dataset.apartmentId || apartmentNo);
    modalTitle.textContent = apartmentNo
      ? `Lokal ${apartmentNo} - karta`
      : "Karta lokalu";
    setModalImage(apartmentId);
    cardModal.hidden = false;
    document.body.classList.add("modal-open");
    requestForm?.reset();
    requestNote.textContent = "";
    setRequestPanel(false);
  }

  function closeCardModal() {
    if (!cardModal) return;
    cardModal.hidden = true;
    document.body.classList.remove("modal-open");
    setRequestPanel(false);
  }

  rows.forEach((row) => {
    const apartmentNo = row.children[0]?.textContent?.trim() || "";
    const apartmentId = normalizeApartmentId(apartmentNo);
    row.dataset.apartmentId = apartmentId;
    row.tabIndex = 0;
    row.setAttribute("role", "button");
    row.setAttribute("aria-label", "Otwórz kartę lokalu");
    row.addEventListener("click", () => openCardModal(row));
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openCardModal(row);
      }
    });
  });

  document.querySelectorAll(".apt-link").forEach((link) => {
    const apartmentId = normalizeApartmentId(link.dataset.apt);
    link.addEventListener("click", (event) => {
      const row = Array.from(rows).find(
        (tableRow) => tableRow.dataset.apartmentId === apartmentId,
      );
      if (!row) return;
      event.preventDefault();
      openCardModal(row);
    });
  });

  closeModalTriggers.forEach((trigger) =>
    trigger.addEventListener("click", closeCardModal),
  );
  openRequestBtn?.addEventListener("click", () => setRequestPanel(true));
  backToPreviewBtn?.addEventListener("click", () => setRequestPanel(false));
  requestForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    const email = requestEmailInput?.value?.trim();
    if (!email) return;
    requestNote.textContent = `Dziękujemy. Kartę lokalu wyślemy na ${email}.`;
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && cardModal && !cardModal.hidden) {
      closeCardModal();
    }
  });
})();
