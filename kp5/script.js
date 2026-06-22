(()=> {
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
})();
