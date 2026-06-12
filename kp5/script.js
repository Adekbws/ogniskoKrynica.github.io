(()=> {
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
