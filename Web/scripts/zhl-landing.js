/* ZHL: gemeinsamer DE/EN-Umschalter für alle ZHL-Seiten.
 * Übersetzt Elemente mit data-en (innerHTML) und data-en-ph (placeholder).
 * Die deutsche Fassung ist der sichtbare Inline-Inhalt; EN steckt im Attribut.
 * Sprache bleibt per localStorage erhalten. */
(function () {
  var TITLES = {}; // optional: Seite kann window.ZHL_TITLE = {de,en} setzen
  var cur = "de";

  function applyLang(l) {
    document.documentElement.lang = l;
    var t = window.ZHL_TITLE;
    if (t && t[l]) document.title = t[l];

    document.querySelectorAll("[data-en]").forEach(function (el) {
      if (el.dataset.de === undefined) el.dataset.de = el.innerHTML;
      el.innerHTML = l === "en" ? el.dataset.en : el.dataset.de;
    });
    document.querySelectorAll("[data-en-ph]").forEach(function (el) {
      if (el.dataset.dePh === undefined) el.dataset.dePh = el.getAttribute("placeholder") || "";
      el.setAttribute("placeholder", l === "en" ? el.dataset.enPh : el.dataset.dePh);
    });
    document.querySelectorAll("[data-lang-btn]").forEach(function (b) {
      b.textContent = l === "en" ? "DE" : "EN";
    });
    try { localStorage.setItem("zhlLang", l); } catch (e) {}
    cur = l;
  }

  window.toggleLang = function () { applyLang(cur === "de" ? "en" : "de"); };
  window.applyLang = applyLang;

  function init() {
    var saved;
    try { saved = localStorage.getItem("zhlLang"); } catch (e) {}
    if (saved === "en") applyLang("en");
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
