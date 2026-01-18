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
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[s]));
  }

  // ----------------- Color helpers -----------------

  function clamp(v, a = 0, b = 255) { return Math.min(b, Math.max(a, v)); }
  function round2(x) { return Math.round(Number(x) * 100) / 100; }

  // LAB -> RGB (approx sRGB)
  function labToRgb(L, a, b) {
    let y = (L + 16) / 116;
    let x = a / 500 + y;
    let z = y - b / 200;

    const f = t => {
      const t3 = t * t * t;
      return (t3 > 0.008856) ? t3 : (t - 16 / 116) / 7.787;
    };

    x = 0.95047 * f(x);
    y = 1.00000 * f(y);
    z = 1.08883 * f(z);

    let r = x * 3.2406 + y * -1.5372 + z * -0.4986;
    let g = x * -0.9689 + y * 1.8758 + z * 0.0415;
    let bb = x * 0.0557 + y * -0.2040 + z * 1.0570;

    const gamma = u => (u <= 0.0031308) ? 12.92 * u : 1.055 * Math.pow(u, 1 / 2.4) - 0.055;

    r = gamma(r); g = gamma(g); bb = gamma(bb);

    return {
      r: clamp(Math.round(r * 255)),
      g: clamp(Math.round(g * 255)),
      b: clamp(Math.round(bb * 255)),
    };
  }

  // Accepts: [r,g,b] OR number OR {r,g,b} OR {lab:[L,a,b]} OR [L,a,b]
  function parseColorToRgb(c) {
    if (c == null) return null;

    if (typeof c === "number") {
      const v = clamp(Math.round(c));
      return { r: v, g: v, b: v, label: `gray(${v})` };
    }

    if (Array.isArray(c) && c.length === 3) {
      const A = Number(c[0]), B = Number(c[1]), C = Number(c[2]);

      // heuristic LAB
      const isLab = (A >= 0 && A <= 100 && Math.abs(B) <= 160 && Math.abs(C) <= 160);
      if (isLab) {
        const rgb = labToRgb(A, B, C);
        return { ...rgb, label: `LAB(${round2(A)},${round2(B)},${round2(C)})` };
      }
      const rgb = { r: clamp(Math.round(A)), g: clamp(Math.round(B)), b: clamp(Math.round(C)) };
      return { ...rgb, label: `RGB(${rgb.r},${rgb.g},${rgb.b})` };
    }

    if (typeof c === "object") {
      if ("r" in c && "g" in c && "b" in c) {
        const rgb = { r: clamp(Math.round(c.r)), g: clamp(Math.round(c.g)), b: clamp(Math.round(c.b)) };
        return { ...rgb, label: `RGB(${rgb.r},${rgb.g},${rgb.b})` };
      }
      if ("lab" in c && Array.isArray(c.lab) && c.lab.length === 3) {
        const [L, a, b] = c.lab.map(Number);
        const rgb = labToRgb(L, a, b);
        return { ...rgb, label: `LAB(${round2(L)},${round2(a)},${round2(b)})` };
      }
    }

    return null;
  }

  // ----------------- Canvas overlay -----------------

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
      const [x1, y1, x2, y2] = det.bbox;
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

  // ----------------- Filtering / list -----------------

  function updateStats() {
    setText(statTotal, String(detections.length));
    setText(statDesc, String(detections.filter(d => d.has_desc).length));
    setText(statClasses, String(new Set(detections.map(d => d.class_name)).size));
    setText(statVisible, `${filtered.length} affichés`);
  }

  function buildClassOptions() {
    if (!classFilter) return;
    const classes = Array.from(new Set(detections.map(d => d.class_name))).sort();
    classFilter.innerHTML =
      `<option value="">Toutes les classes</option>` +
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

    filtered.sort((a, b) => (b.confidence || 0) - (a.confidence || 0));
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

      btn.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
          <strong>${escapeHtml(det.class_name)}</strong>
          <span class="badge bg-primary">${(det.confidence * 100).toFixed(1)}%</span>
        </div>
        <div class="small text-muted mt-1">${det.has_desc ? "Descripteurs disponibles" : "Descripteurs non calculés"}</div>
      `;

      btn.addEventListener("mouseenter", () => { hoverId = det.id; if (!selectedId) drawBoxes(hoverId); });
      btn.addEventListener("mouseleave", () => { hoverId = null; if (!selectedId) drawBoxes(null); });
      btn.addEventListener("click", () => selectDet(det.id));

      listEl.appendChild(btn);
    }
  }

  // ----------------- Render helpers (modal tabs) -----------------

  function renderKeyValueTable(obj) {
    if (!obj || typeof obj !== "object") return `<div class="text-muted">Aucune donnée</div>`;

    const rows = Object.entries(obj).map(([k, v]) => {
      const num = (typeof v === "number") ? round2(v) : v;
      return `
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div class="text-muted small">${escapeHtml(k)}</div>
          <div class="fw-semibold">${escapeHtml(String(num))}</div>
        </div>
      `;
    }).join("");

    return `<div class="rounded-3 border bg-white p-2">${rows}</div>`;
  }

  function renderVector(vec, maxLen = 220) {
    if (!Array.isArray(vec) || !vec.length) return "Aucun vecteur.";
    const short = vec.length > maxLen ? vec.slice(0, maxLen).concat(["…"]) : vec;
    return JSON.stringify(short);
  }

  function renderMiniBars(containerId, arr, take = 32) {
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
      return `<div style="width:8px;height:${h}px;background:linear-gradient(180deg,#4f46e5,#06b6d4);border-radius:6px;"></div>`;
    }).join("");

    el.innerHTML = `
      <div class="d-flex align-items-end gap-1" style="height:56px;">
        ${bars}
      </div>
      <div class="small text-muted mt-2">Bins affichés: 1–${Math.min(take, arr.length)} (max normalisé)</div>
    `;
  }

  function renderDominantColors(containerId, colors, ratios) {
    const el = document.getElementById(containerId);
    if (!el) return;

    if (!Array.isArray(colors) || !colors.length) {
      el.innerHTML = `
        <div class="text-muted">
          Aucune couleur dominante visuelle.
          <br><span class="small">Recalcule les descripteurs pour générer \`dominant\_colors\_rgb\`.</span>
        </div>`;
      return;
    }

    const parsed = colors.map(parseColorToRgb).filter(Boolean).slice(0, 10);

    if (!parsed.length) {
      el.innerHTML = `<pre class="bg-light p-2 rounded mb-0">${escapeHtml(JSON.stringify(colors, null, 2))}</pre>`;
      return;
    }

    const r = Array.isArray(ratios) ? ratios : [];

    el.innerHTML = `
      <div class="d-flex flex-wrap gap-2">
        ${parsed.map((c, i) => {
          const pct = (r[i] != null) ? Math.round(Number(r[i]) * 100) : null;
          return `
            <div class="vs-swatch" title="${escapeHtml(c.label || '')}">
              <div class="vs-swatch-color" style="background: rgb(${c.r},${c.g},${c.b});"></div>
              <div class="vs-swatch-meta">
                <div class="small text-muted">#${i + 1}${pct != null ? ` • ${pct}%` : ""}</div>
                <div class="small fw-semibold">rgb(${c.r},${c.g},${c.b})</div>
                ${pct != null ? `
                  <div class="mt-1" style="height:6px;width:120px;background:#e9ecef;border-radius:999px;overflow:hidden;">
                    <div style="height:6px;width:${clamp(pct,0,100)}%;background:linear-gradient(90deg,#4f46e5,#06b6d4);"></div>
                  </div>
                ` : ""}
              </div>
            </div>
          `;
        }).join("")}
      </div>
    `;
  }

  // ----------------- Modal content -----------------

  function fillModalTabs(det) {
    const sub = document.getElementById("detSubTitle");
    if (sub) sub.textContent = det.has_desc ? "Descripteurs disponibles" : "Descripteurs non calculés";

    const meta = document.getElementById("detMeta");
    if (meta) {
      meta.innerHTML = `
        <div><b>Classe</b>: ${escapeHtml(det.class_name)}</div>
        <div><b>Confiance</b>: ${(det.confidence * 100).toFixed(1)}%</div>
        <div><b>BBox</b>: [${det.bbox.map(v => Number(v).toFixed(1)).join(", ")}]</div>
      `;
    }

    if (!det.desc) {
      renderDominantColors("colorsDominant", [], []);
      renderMiniBars("colorsHistMini", []);
      document.getElementById("tamuraBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("gaborBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("lbpBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("huBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("oriBox").innerHTML = `<div class="text-muted">Aucune donnée</div>`;
      document.getElementById("vectorBox").textContent = "Aucun vecteur.";
      return;
    }

    // ✅ IMPORTANT: prefer real RGB dominant colors
    const dominant = det.desc.dominant_colors_rgb || det.desc.dominant_colors || det.desc.dominant || [];
    const ratios = det.desc.dominant_colors_ratio || det.desc.dominant_ratio || [];

    // histogram HSV
    const hist = det.desc.color_hist_hsv || det.desc.color_hist || det.desc.hist || [];

    renderDominantColors("colorsDominant", dominant, ratios);
    renderMiniBars("colorsHistMini", hist, 32);

    // Tamura/Gabor more readable
    const tamura = det.desc.tamura || {};
    const gabor = det.desc.gabor || {};
    document.getElementById("tamuraBox").innerHTML = renderKeyValueTable(tamura);
    document.getElementById("gaborBox").innerHTML = renderKeyValueTable(gabor);

    // LBP
    const lbp = det.desc.lbp_hist || (det.desc.extra && det.desc.extra.lbp_hist) || [];
    renderMiniBars("lbpBox", Array.isArray(lbp) ? lbp : [], 32);

    // Hu moments
    const hu = det.desc.hu_moments || det.desc.hu || [];
    document.getElementById("huBox").innerHTML =
      Array.isArray(hu) && hu.length
        ? `<div class="rounded-3 border bg-white p-2">
            ${hu.slice(0, 12).map((v, i) => `
              <div class="d-flex justify-content-between py-2 border-bottom">
                <div class="text-muted small">Hu${i + 1}</div>
                <div class="fw-semibold">${round2(v)}</div>
              </div>
            `).join("")}
          </div>`
        : `<div class="text-muted">Aucune donnée</div>`;

    // Orientation hist
    const ori = det.desc.orientation_hist || det.desc.orientations || [];
    renderMiniBars("oriBox", Array.isArray(ori) ? ori : [], 24);

    // Feature vector
    const vec = det.desc.feature_vector || det.desc.vector || [];
    const vecBox = document.getElementById("vectorBox");
    if (vecBox) vecBox.textContent = renderVector(vec, 260);

    // Copy vector
    const btnCopy = document.getElementById("btnCopyVector");
    if (btnCopy) {
      btnCopy.onclick = async () => {
        try {
          await navigator.clipboard.writeText(Array.isArray(vec) ? JSON.stringify(vec) : "");
          btnCopy.textContent = "Copié ✅";
          setTimeout(() => btnCopy.textContent = "Copier", 1200);
        } catch {
          btnCopy.textContent = "Erreur";
          setTimeout(() => btnCopy.textContent = "Copier", 1200);
        }
      };
    }
  }

  // ----------------- Selection -----------------

  function selectDet(detId) {
    selectedId = detId;
    const det = detections.find(d => d.id === detId);
    if (!det) return;

    const hiddenCrop = document.getElementById("selected_detection_id");
    if (hiddenCrop) hiddenCrop.value = detId;

    const hiddenSearch = document.getElementById("search_detection_id");
    if (hiddenSearch) hiddenSearch.value = detId;

    drawBoxes(detId);

    const title = document.getElementById("detTitle");
    if (title) title.textContent = `${det.class_name} (${(det.confidence * 100).toFixed(1)}%)`;

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

  // ----------------- Init -----------------

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

  window.selectDet = selectDet;
  init();
})();
