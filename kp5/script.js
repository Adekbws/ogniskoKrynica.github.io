(()=> {
  const CONTACT_PHONE = "+48664663940";
  const FORM_TS_SELECTOR = "[data-form-ts]";
  const FORM_STATUS_SELECTOR = "[data-form-status]";
  const FORM_ERROR_MESSAGES = {
    spam: "Nie udało się wysłać formularza. Spróbuj ponownie lub napisz na kontakt@apartamentyognisko.pl.",
    timing: "Formularz wysłano zbyt szybko. Odśwież stronę i spróbuj ponownie.",
    rate: "Zbyt wiele prób w krótkim czasie. Spróbuj ponownie za chwilę.",
    email: "Podaj poprawny adres e-mail.",
    send: "Wystąpił błąd wysyłki. Napisz do nas na kontakt@apartamentyognisko.pl.",
    apartment: "Nie udało się rozpoznać numeru lokalu. Spróbuj ponownie.",
    card_file:
      "Nie udało się przygotować karty lokalu. Napisz do nas na kontakt@apartamentyognisko.pl.",
  };

  document.querySelectorAll(".nav-cta--menu").forEach((link) => {
    link.href = `tel:${CONTACT_PHONE}`;
  });

  const formTimestamp = String(Math.floor(Date.now() / 1000));
  document.querySelectorAll(FORM_TS_SELECTOR).forEach((input) => {
    input.value = formTimestamp;
  });

  function showContactFormStatus() {
    const params = new URLSearchParams(window.location.search);
    const statusNode = document.querySelector(FORM_STATUS_SELECTOR);
    if (!statusNode) return;

    if (params.get("sent") === "1") {
      statusNode.hidden = false;
      statusNode.textContent =
        "Dziękujemy za wiadomość. Odezwiemy się wkrótce.";
      statusNode.classList.add("is-success");
      statusNode.classList.remove("is-error");
      return;
    }

    const errorCode = params.get("error");
    if (!errorCode) return;

    statusNode.hidden = false;
    statusNode.textContent =
      FORM_ERROR_MESSAGES[errorCode] ||
      "Nie udało się wysłać formularza. Spróbuj ponownie.";
    statusNode.classList.add("is-error");
    statusNode.classList.remove("is-success");
  }

  showContactFormStatus();

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

  function parsePolishPrice(text) {
    const match = String(text).match(/([\d\s]+)/);
    if (!match) return NaN;
    return parseInt(match[1].replace(/\s/g, ""), 10);
  }

  function formatPricePerM2(value) {
    return `${Math.round(value).toLocaleString("pl-PL")} zł/m²`;
  }

  document.querySelectorAll(".apartments-table tbody tr").forEach((row) => {
    const areaCell = row.children[2];
    const priceCell = row.children[3];
    if (!areaCell || !priceCell) return;

    const area = parsePolishArea(areaCell.textContent);
    const price = parsePolishPrice(priceCell.textContent);
    const cell = document.createElement("td");

    if (area > 0 && Number.isFinite(price)) {
      cell.textContent = formatPricePerM2(price / area);
    } else {
      cell.textContent = "—";
    }

    areaCell.insertAdjacentElement("afterend", cell);
  });

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
    document.body.classList.remove("modal-open");
    setRequestPanel(false);
  }

  rows.forEach((row) => {
    const apartmentNo = row.children[0]?.textContent?.trim() || "";
    const apartmentId = normalizeApartmentId(apartmentNo);
    row.dataset.apartmentId = apartmentId;
    row.tabIndex = 0;
    row.setAttribute("role", "button");
    row.setAttribute(
      "aria-label",
      apartmentNo
        ? `Otwórz kartę lokalu ${apartmentNo}`
        : "Otwórz kartę lokalu",
    );
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

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && cardModal && !cardModal.hidden) {
      closeCardModal();
    }
  });
})();
