(function () {
  const metaEl = document.getElementById("pageMeta");
  if (!metaEl) return;

  const dataUrl = metaEl.getAttribute("data-data-url");
  if (!dataUrl) return;

  const listEl = document.getElementById("detectionsList");
  const imgEl = document.getElementById("mainImage");
  const canvas = document.getElementById("overlay");

  const statTotal = document.getElementById("statTotal");
  const statDesc = document.getElementById("statDesc");
  const statClasses = document.getElementById("statClasses");
  const statVisible = document.getElementById("statVisible");

  const searchInput = document.getElementById("searchDetInput");
  const classFilter = document.getElementById("classFilter");
  const btnClear = document.getElementById("btnClearFilters");
  const btnShowAll = document.getElementById("btnShowAll");

  let imageW = 1, imageH = 1;
  let detections = [];
  let filtered = [];
  let selectedId = null;
  let hoverId = null;

  function setText(el, txt) { if (el) el.textContent = txt; }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[s]));
  }

  function drawBoxes(highlightId) {
    if (!imgEl || !canvas) return;
    const rect = imgEl.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
    canvas.style.width = rect.width + "px";
    canvas.style.height = rect.height + "px";

    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (const det of filtered) {
      const [x1,y1,x2,y2] = det.bbox;
      const sx1 = (x1 / imageW) * canvas.width;
      const sy1 = (y1 / imageH) * canvas.height;
      const sx2 = (x2 / imageW) * canvas.width;
      const sy2 = (y2 / imageH) * canvas.height;

      const isHighlight = det.id === highlightId;
      ctx.lineWidth = isHighlight ? 4 : 2;
      ctx.strokeStyle = isHighlight ? "#ff0000" : "#00ff00";
      ctx.strokeRect(sx1, sy1, (sx2 - sx1), (sy2 - sy1));
    }
  }

  function updateStats() {
    setText(statTotal, String(detections.length));
    setText(statDesc, String(detections.filter(d => d.has_desc).length));
    setText(statClasses, String(new Set(detections.map(d => d.class_name)).size));
    setText(statVisible, `${filtered.length} affichés`);
  }

  function buildClassOptions() {
    if (!classFilter) return;
    const classes = Array.from(new Set(detections.map(d => d.class_name))).sort();
    classFilter.innerHTML = `<option value="">Toutes les classes</option>` +
      classes.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join("");
  }

  function applyFilters() {
    const q = (searchInput?.value || "").trim().toLowerCase();
    const cls = (classFilter?.value || "").trim();

    filtered = detections.filter(d => {
      const matchQ = !q || d.class_name.toLowerCase().includes(q);
      const matchC = !cls || d.class_name === cls;
      return matchQ && matchC;
    });

    filtered.sort((a,b) => (b.confidence||0) - (a.confidence||0));
    renderList();
    updateStats();

    const keep = selectedId && filtered.some(d => d.id === selectedId) ? selectedId : null;
    drawBoxes(keep || hoverId || null);
  }

  function renderList() {
    if (!listEl) return;
    listEl.innerHTML = "";

    if (!filtered.length) {
      listEl.innerHTML = '<div class="p-3 text-muted">Aucun objet ne correspond au filtre.</div>';
      return;
    }

    for (const det of filtered) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "list-group-item list-group-item-action";
      btn.id = "det-btn-" + det.id;

      btn.innerHTML =
        `<div class="d-flex justify-content-between align-items-center">
          <strong>${escapeHtml(det.class_name)}</strong>
          <span class="badge bg-primary">${(det.confidence*100).toFixed(1)}%</span>
        </div>
        <div class="small text-muted mt-1">${det.has_desc ? "Descripteurs ✅" : "Descripteurs ❌"}</div>`;

      btn.addEventListener("mouseenter", () => { hoverId = det.id; if (!selectedId) drawBoxes(hoverId); });
      btn.addEventListener("mouseleave", () => { hoverId = null; if (!selectedId) drawBoxes(null); });
      btn.addEventListener("click", () => selectDet(det.id));

      listEl.appendChild(btn);
    }
  }

  // ----------- Modal Tabs rendering helpers -----------

  function renderKeyValueTable(obj) {
    if (!obj || typeof obj !== "object") return `<div class="text-muted">Aucune donnée</div>`;
    const rows = Object.entries(obj).map(([k,v]) => {
      const val = (Array.isArray(v) || typeof v === "object") ? JSON.stringify(v) : String(v);
      return `<tr><td class="text-muted" style="width:45%">${escapeHtml(k)}</td><td>${escapeHtml(val)}</td></tr>`;
    }).join("");
    return `<table class="table table-sm mb-0"><tbody>${rows}</tbody></table>`;
  }

  function renderVector(vec, maxLen=220) {
    if (!Array.isArray(vec) || !vec.length) return "Aucun vecteur.";
    const short = vec.length > maxLen ? vec.slice(0, maxLen).concat(["…"]) : vec;
    return JSON.stringify(short);
  }

  function renderMiniBars(containerId, arr, take=32) {
    const el = document.getElementById(containerId);
    if (!el) return;

    if (!Array.isArray(arr) || !arr.length) {
      el.innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      return;
    }

    const slice = arr.slice(0, take).map(Number);
    const maxV = Math.max(...slice, 1);

    const bars = slice.map(v => {
      const h = Math.max(2, Math.round((v / maxV) * 48));
      return `<div style="width:8px;height:${h}px;background:#0d6efd;border-radius:3px;"></div>`;
    }).join("");

    el.innerHTML = `
      <div class="d-flex align-items-end gap-1" style="height:56px;">
        ${bars}
      </div>
      <div class="small text-muted mt-2">Bins affichés: 1–${Math.min(take, arr.length)} (max normalisé)</div>
    `;
  }

  function renderDominantColors(containerId, colors) {
    const el = document.getElementById(containerId);
    if (!el) return;

    if (!Array.isArray(colors) || !colors.length) {
      el.innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      return;
    }

    // On ne suppose pas le format (RGB/LAB). On affiche des “chips” avec valeurs.
    el.innerHTML = `
      <div class="d-flex flex-wrap gap-2">
        ${colors.slice(0, 8).map((c, i) => `
          <div class="border rounded-pill px-2 py-1 bg-light">
            <span class="text-muted small">#${i+1}</span>
            <span class="small ms-2">${escapeHtml(JSON.stringify(c))}</span>
          </div>
        `).join("")}
      </div>
      <div class="small text-muted mt-2">Affichage brut (selon ton format). Si tu veux, on convertit LAB → RGB plus tard.</div>
    `;
  }

  function fillModalTabs(det) {
    const sub = document.getElementById("detSubTitle");
    if (sub) sub.textContent = det.has_desc ? "Descripteurs disponibles ✅" : "Descripteurs non calculés ❌";

    // meta
    const meta = document.getElementById("detMeta");
    if (meta) {
      meta.innerHTML = `
        <div><b>Classe</b>: ${escapeHtml(det.class_name)}</div>
        <div><b>Confiance</b>: ${(det.confidence*100).toFixed(1)}%</div>
        <div><b>BBox</b>: [${det.bbox.map(v => Number(v).toFixed(1)).join(", ")}]</div>
      `;
    }

    // If no descriptors
    if (!det.desc) {
      renderDominantColors("colorsDominant", []);
      renderMiniBars("colorsHistMini", []);
      document.getElementById("tamuraBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("gaborBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("lbpBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("huBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("oriBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("vectorBox").textContent = "Aucun vecteur.";
      return;
    }

    // Map keys (adapte si tes noms sont différents)
    const dominant = det.desc.dominant_colors || det.desc.dominant_colors_lab || det.desc.dominant || [];
    const hist = det.desc.color_hist || det.desc.color_hist_hsv || det.desc.hist || [];

    renderDominantColors("colorsDominant", dominant);
    renderMiniBars("colorsHistMini", hist, 32);

    const tamura = det.desc.tamura || {};
    const gabor = det.desc.gabor || {};
    const lbp = (det.desc.extra && det.desc.extra.lbp_hist) ? det.desc.extra.lbp_hist : (det.desc.lbp_hist || []);

    document.getElementById("tamuraBox").innerHTML = renderKeyValueTable(tamura);
    document.getElementById("gaborBox").innerHTML = renderKeyValueTable(gabor);
    document.getElementById("lbpBox").innerHTML = `<div class="mb-2">${Array.isArray(lbp) ? "" : ""}</div>`;
    renderMiniBars("lbpBox", Array.isArray(lbp) ? lbp : [], 32);

    const hu = det.desc.hu_moments || det.desc.hu || [];
    const ori = det.desc.orientation_hist || det.desc.orientations || [];

    document.getElementById("huBox").innerHTML = Array.isArray(hu)
      ? `<pre class="bg-light p-2 rounded mb-0">${escapeHtml(JSON.stringify(hu, null, 2))}</pre>`
      : renderKeyValueTable(hu);

    renderMiniBars("oriBox", Array.isArray(ori) ? ori : [], 24);

    const vec = det.desc.feature_vector || det.desc.vector || [];
    const vecBox = document.getElementById("vectorBox");
    if (vecBox) vecBox.textContent = renderVector(vec, 260);

    // Copy button
    const btnCopy = document.getElementById("btnCopyVector");
    if (btnCopy) {
      btnCopy.onclick = async () => {
        try {
          await navigator.clipboard.writeText(Array.isArray(vec) ? JSON.stringify(vec) : "");
          btnCopy.textContent = "Copié ✅";
          setTimeout(()=> btnCopy.textContent = "Copier", 1200);
        } catch {
          btnCopy.textContent = "Erreur";
          setTimeout(()=> btnCopy.textContent = "Copier", 1200);
        }
      };
    }
  }

  // ----------- Selection -----------

  function selectDet(detId) {
    selectedId = detId;
    const det = detections.find(d => d.id === detId);
    if (!det) return;

    // crop input
    const hiddenCrop = document.getElementById("selected_detection_id");
    if (hiddenCrop) hiddenCrop.value = detId;

    // modal hidden
    const hiddenSearch = document.getElementById("search_detection_id");
    if (hiddenSearch) hiddenSearch.value = detId;

    // draw highlight
    drawBoxes(detId);

    // modal title
    const title = document.getElementById("detTitle");
    if (title) title.textContent = `${det.class_name} (${(det.confidence*100).toFixed(1)}%)`;

    fillModalTabs(det);

    const modalEl = document.getElementById("detModal");
    if (modalEl && window.bootstrap) {
      new bootstrap.Modal(modalEl).show();
    }
  }

  function wireEvents() {
    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (classFilter) classFilter.addEventListener("change", applyFilters);

    if (btnClear) btnClear.addEventListener("click", () => {
      if (searchInput) searchInput.value = "";
      if (classFilter) classFilter.value = "";
      selectedId = null;
      applyFilters();
    });

    if (btnShowAll) btnShowAll.addEventListener("click", () => {
      if (searchInput) searchInput.value = "";
      if (classFilter) classFilter.value = "";
      selectedId = null;
      applyFilters();
    });

    window.addEventListener("resize", () => drawBoxes(selectedId || hoverId || null));
  }

  async function init() {
    try {
      const res = await fetch(dataUrl, { headers: { "Accept": "application/json" } });
      const data = await res.json();

      imageW = data.image.width;
      imageH = data.image.height;

      detections = data.detections || [];
      filtered = [...detections];

      buildClassOptions();
      applyFilters();
      updateStats();
      wireEvents();

      if (imgEl && imgEl.complete) drawBoxes(null);
      else if (imgEl) imgEl.addEventListener("load", () => drawBoxes(null));
    } catch (e) {
      console.error(e);
      if (listEl) listEl.innerHTML = '<div class="p-3 text-danger">Erreur chargement des données.</div>';
    }
  }

  window.selectDet = selectDet; // for inline onclick if needed
  init();
})();
