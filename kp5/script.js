(()=> {
  const CONTACT_PHONE = "+48664663940";
  const FORM_TS_SELECTOR = "[data-form-ts]";
  const FORM_STATUS_SELECTOR = "[data-form-status]";

  document.querySelectorAll(".nav-cta--menu").forEach((link) => {
    link.href = `tel:${CONTACT_PHONE}`;
  });

  document.querySelectorAll(".apt-link").forEach((link) => {
    const label = link.getAttribute("aria-label") || link.dataset.apt || "";
    if (!label) return;
    link.setAttribute("aria-label", `Wybierz lokal ${label}`);
    if (link.querySelector("title")) return;
    const title = document.createElementNS("http://www.w3.org/2000/svg", "title");
    title.textContent = `Lokal ${label}`;
    link.insertBefore(title, link.firstChild);
  });

  const formTimestamp = String(Math.floor(Date.now() / 1000));
  document.querySelectorAll(FORM_TS_SELECTOR).forEach((input) => {
    input.value = formTimestamp;
  });

  function setContactFormStatus(message, isError = false) {
    const statusNode = document.querySelector(FORM_STATUS_SELECTOR);
    if (!statusNode) return;
    statusNode.hidden = false;
    statusNode.textContent = message;
    statusNode.classList.toggle("is-success", !isError);
    statusNode.classList.toggle("is-error", isError);
  }

  function scrollToFormFeedback() {
    const statusNode = document.querySelector(FORM_STATUS_SELECTOR);
    const target =
      statusNode && !statusNode.hidden
        ? statusNode
        : document.getElementById("kontakt");
    target?.scrollIntoView({ block: "end", inline: "nearest" });
  }

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
  function showFloor(id) {
    tabs.forEach((tab) => {
      const selected = tab.dataset.floor === id;
      tab.classList.toggle("active", selected);
      tab.setAttribute("aria-selected", String(selected));
    });
    panels.forEach((panel) => {
      const selected = panel.dataset.floorPanel === id;
      panel.classList.toggle("active", selected);
      panel.hidden = !selected;
    });
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

  function parsePolishArea(text) {
    const match = String(text).match(/([\d,.]+)/);
    if (!match) return NaN;
    return parseFloat(match[1].replace(",", "."));
  }

  function formatPrice(value) {
    return `${Math.round(value).toLocaleString("pl-PL")} zł`;
  }

  function formatPricePerM2(value) {
    return `${Math.round(value).toLocaleString("pl-PL")} zł/m²`;
  }

  const STATUS_LABELS = {
    available: "Dostępny",
    reserved: "Rezerwacja",
    sold: "Sprzedany",
  };

  function applyApartmentStatus(row, status) {
    const statusCell = row.children[5];
    if (!statusCell) return;
    const safeStatus = STATUS_LABELS[status] ? status : "available";
    const label = STATUS_LABELS[safeStatus];
    statusCell.innerHTML = `<span class="status-pill ${safeStatus}" aria-label="${label}"><span class="status-pill-text" aria-hidden="true">${label}</span></span>`;
  }

  function updateRowInteractivity(row) {
    const isSold = row.dataset.status === "sold";
    row.classList.toggle("is-sold", isSold);

    if (isSold) {
      row.tabIndex = -1;
      row.removeAttribute("role");
      row.removeAttribute("aria-label");
      return;
    }

    const apartmentNo = row.children[0]?.textContent?.trim() || "";
    row.tabIndex = 0;
    row.setAttribute("role", "button");
    row.setAttribute(
      "aria-label",
      apartmentNo
        ? `Otwórz kartę lokalu ${apartmentNo}`
        : "Otwórz kartę lokalu",
    );
  }

  function applyApartmentRowState(row, status) {
    const safeStatus = STATUS_LABELS[status] ? status : "available";
    row.dataset.status = safeStatus;
    applyApartmentStatus(row, safeStatus);
    updateRowInteractivity(row);
  }

  function renderApartmentPricesForRow(row) {
    const areaCell = row.children[2];
    const pricePerM2Cell = row.children[3];
    const totalPriceCell = row.children[4];
    if (!areaCell || !pricePerM2Cell || !totalPriceCell) return;

    if (row.dataset.status === "sold") {
      pricePerM2Cell.textContent = "";
      totalPriceCell.textContent = "";
      pricePerM2Cell.classList.add("is-price-hidden");
      totalPriceCell.classList.add("is-price-hidden");
      return;
    }

    pricePerM2Cell.classList.remove("is-price-hidden");
    totalPriceCell.classList.remove("is-price-hidden");

    const area = parsePolishArea(areaCell.textContent);
    const pricePerM2 = Number(row.dataset.pricePerM2);

    if (area > 0 && Number.isFinite(pricePerM2)) {
      pricePerM2Cell.textContent = formatPricePerM2(pricePerM2);
      totalPriceCell.textContent = formatPrice(pricePerM2 * area);
    } else {
      pricePerM2Cell.textContent = "—";
      totalPriceCell.textContent = "—";
    }
  }

  function renderApartmentPrices() {
    document.querySelectorAll(".apartments-table tbody tr").forEach((row) => {
      renderApartmentPricesForRow(row);
    });
  }

  async function loadApartmentData() {
    renderApartmentPrices();

    try {
      const response = await fetch("api/lokale.php", {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      if (!response.ok) return;

      const data = await response.json();
      if (!data || typeof data !== "object") return;

      document.querySelectorAll(".apartments-table tbody tr").forEach((row) => {
        const apartmentNo = row.children[0]?.textContent?.trim();
        const apartment = data[apartmentNo];
        if (!apartment) return;

        if (Number.isFinite(Number(apartment.pricePerM2))) {
          row.dataset.pricePerM2 = String(apartment.pricePerM2);
        }
        if (apartment.status) {
          applyApartmentRowState(row, apartment.status);
        }
      });

      renderApartmentPrices();
    } catch {
      // Wartości z data-price-per-m2 w HTML pozostają aktywne.
    }
  }

  loadApartmentData();

  if (window.location.hash === "#kontakt") {
    scrollToFormFeedback();
  }

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
  const modalImageWrap = document.querySelector(".card-modal-image-wrap");
  const requestEmailInput = requestForm?.querySelector('input[name="email"]');
  const apartmentInput = requestForm?.querySelector("[data-apartment-input]");

  function normalizeApartmentId(rawId) {
    const digits = String(rawId || "").match(/\d+/)?.[0];
    return digits ? `LU.${Number(digits)}` : "";
  }

  function buildCardImagePath(apartmentId) {
    const localNo = apartmentId.replace("LU.", "");
    return `assets/karty/png/ognisko_km_popup_240626-LU${localNo}.png`;
  }

  function setModalImage(apartmentId) {
    if (!modalImage || !modalImageWrap) return;
    const localNo = apartmentId.replace("LU.", "");
    modalImageWrap.classList.add("is-loading");
    modalImage.src = buildCardImagePath(apartmentId);
    modalImage.alt = localNo
      ? `Karta lokalu ${localNo}`
      : "Karta lokalu";
    if (modalImage.complete) {
      modalImageWrap.classList.remove("is-loading");
    }
  }

  modalImage?.addEventListener("load", () => {
    modalImageWrap?.classList.remove("is-loading");
  });
  modalImage?.addEventListener("error", () => {
    modalImageWrap?.classList.remove("is-loading");
  });

  function setRequestPanel(open) {
    previewPanel.hidden = open;
    requestPanel.hidden = !open;
    if (open) requestEmailInput?.focus();
  }

  function openCardModal(row) {
    if (!cardModal || row.dataset.status === "sold") return;
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
    if (apartmentInput) {
      apartmentInput.value = apartmentNo || apartmentId || "";
    }
    requestForm?.querySelectorAll(FORM_TS_SELECTOR).forEach((input) => {
      input.value = formTimestamp;
    });
    setRequestPanel(false);
  }

  function closeCardModal() {
    if (!cardModal) return;
    cardModal.hidden = true;
    if (!promoModal || promoModal.hidden) {
      document.body.classList.remove("modal-open");
    }
    setRequestPanel(false);
  }

  rows.forEach((row) => {
    const apartmentNo = row.children[0]?.textContent?.trim() || "";
    const apartmentId = normalizeApartmentId(apartmentNo);
    row.dataset.apartmentId = apartmentId;
    row.dataset.status = row.dataset.status || "available";
    updateRowInteractivity(row);
    row.addEventListener("click", () => openCardModal(row));
    row.addEventListener("keydown", (event) => {
      if (row.dataset.status === "sold") return;
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
      if (!row || row.dataset.status === "sold") return;
      event.preventDefault();
      openCardModal(row);
    });
  });

  closeModalTriggers.forEach((trigger) =>
    trigger.addEventListener("click", closeCardModal),
  );
  openRequestBtn?.addEventListener("click", () => setRequestPanel(true));
  backToPreviewBtn?.addEventListener("click", () => setRequestPanel(false));

  document.querySelectorAll(".contact-form").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const submitButton = form.querySelector('button[type="submit"]');
      const statusNode = form.querySelector(FORM_STATUS_SELECTOR);
      submitButton?.setAttribute("disabled", "true");
      if (statusNode) {
        statusNode.hidden = true;
      }

      try {
        const response = await fetch(form.action, {
          method: "POST",
          headers: {
            Accept: "application/json",
          },
          body: new FormData(form),
        });
        const result = await response.json();
        if (!response.ok || !result?.ok) {
          setContactFormStatus(
            result?.message ||
              "Nie udało się wysłać formularza. Spróbuj ponownie.",
            true,
          );
          scrollToFormFeedback();
          return;
        }

        setContactFormStatus(
          result.message ||
            "Dziękujemy za wiadomość. Odezwiemy się wkrótce.",
        );
        form.reset();
        form.querySelectorAll(FORM_TS_SELECTOR).forEach((input) => {
          input.value = formTimestamp;
        });
        scrollToFormFeedback();
      } catch {
        setContactFormStatus(
          "Nie udało się wysłać formularza. Spróbuj ponownie.",
          true,
        );
        scrollToFormFeedback();
      } finally {
        submitButton?.removeAttribute("disabled");
      }
    });
  });

  requestForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const email = requestEmailInput?.value?.trim();
    if (!email || !requestForm) return;

    const submitButton = requestForm.querySelector('button[type="submit"]');
    submitButton?.setAttribute("disabled", "true");

    try {
      const response = await fetch("contact.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(requestForm),
      });
      const result = await response.json();
      if (!response.ok || !result?.ok) {
        requestNote.textContent =
          result?.message ||
          "Nie udało się wysłać prośby. Napisz do nas na kontakt@apartamentyognisko.pl.";
        return;
      }
      requestNote.textContent =
        result.message ||
        `Dziękujemy. Karta lokalu została wysłana na ${email}.`;
      requestForm.reset();
      document.querySelectorAll(FORM_TS_SELECTOR).forEach((input) => {
        input.value = formTimestamp;
      });
      if (apartmentInput) {
        apartmentInput.value =
          modalTitle.textContent.match(/Lokal\s+(\d+)/)?.[1] || "";
      }
    } catch {
      requestNote.textContent =
        "Nie udało się wysłać prośby. Napisz do nas na kontakt@apartamentyognisko.pl.";
    } finally {
      submitButton?.removeAttribute("disabled");
    }
  });

  const promoModal = document.querySelector("#promo-modal");
  const promoBubble = document.querySelector("#promo-bubble");
  const promoCloseTriggers = document.querySelectorAll("[data-close-promo]");
  const promoCta = document.querySelector("[data-promo-cta]");

  function openPromoModal() {
    if (!promoModal) return;
    promoModal.hidden = false;
    document.body.classList.add("modal-open");
    promoModal.querySelector(".promo-modal-close")?.focus();
  }

  function closePromoModal() {
    if (!promoModal) return;
    promoModal.hidden = true;
    if (!cardModal || cardModal.hidden) {
      document.body.classList.remove("modal-open");
    }
  }

  promoCloseTriggers.forEach((trigger) => {
    trigger.addEventListener("click", closePromoModal);
  });

  promoBubble?.addEventListener("click", () => openPromoModal());

  const heroSection = document.querySelector(".hero");
  function updatePromoBubbleTheme() {
    if (!heroSection || !promoBubble) return;
    const heroRect = heroSection.getBoundingClientRect();
    const bubbleRect = promoBubble.getBoundingClientRect();
    const overlapsHero =
      bubbleRect.bottom > heroRect.top && bubbleRect.top < heroRect.bottom;
    promoBubble.classList.toggle("is-contrast", !overlapsHero);
  }

  updatePromoBubbleTheme();
  window.addEventListener("scroll", updatePromoBubbleTheme, { passive: true });
  window.addEventListener("resize", updatePromoBubbleTheme);

  promoCta?.addEventListener("click", (event) => {
    event.preventDefault();
    closePromoModal();
    setNavOpen(false);
    document.getElementById("kontakt")?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  });

  if (promoModal) {
    window.setTimeout(() => openPromoModal(), 400);
  }

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    if (promoModal && !promoModal.hidden) {
      closePromoModal();
      return;
    }
    if (cardModal && !cardModal.hidden) {
      closeCardModal();
    }
  });
})();
