(()=> {
  const root = document.querySelector(".apartments-map");
  if (!root) return;
  function setActive(id, state) {
    root.querySelectorAll(`[data-apt="${id}"],[data-apt-list="${id}"]`)
      .forEach(el => el.classList.toggle("is-active", state));
  }
  root.querySelectorAll(".apt-link,[data-apt-list]").forEach(el => {
    const id = el.dataset.apt || el.dataset.aptList;
    el.addEventListener("mouseenter", () => setActive(id, true));
    el.addEventListener("mouseleave", () => setActive(id, false));
    el.addEventListener("focus", () => setActive(id, true));
    el.addEventListener("blur", () => setActive(id, false));
  });
})();
