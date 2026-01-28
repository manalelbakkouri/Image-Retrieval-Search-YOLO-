@extends('layouts.app')

@section('title', 'VisioSeek — Recherche 3D')

@section('content')
<style>
  /* --- VisioSeek 3D UI theme --- */
  .vs-hero {
    background: linear-gradient(135deg, #1f2937 0%, #111827 40%, #0ea5e9 140%);
    border-radius: 18px;
    color: #fff;
    padding: 22px 22px;
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
  }
  .vs-card {
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 8px 22px rgba(15,23,42,.06);
  }
  .vs-btn {
    border-radius: 12px;
    padding: 10px 16px;
    font-weight: 600;
  }
  .vs-btn-primary {
    background: linear-gradient(90deg, #4f46e5, #06b6d4);
    border: none;
    color: #fff !important;
  }
  .vs-pill {
    border-radius: 999px;
    padding: 5px 10px;
    font-weight: 600;
    background: rgba(2,132,199,.12);
    color: #075985;
    border: 1px solid rgba(2,132,199,.22);
  }
  .vs-scorebar {
    height: 10px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
  }
  .vs-scorebar > div {
    height: 100%;
    background: linear-gradient(90deg, #22c55e, #0ea5e9);
    border-radius: 999px;
  }
  .vs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
  }
  .vs-thumb {
    width: 100%;
    height: 160px;
    object-fit: contain;
    background: #f8fafc;
    border-radius: 14px;
    border: 1px solid rgba(2,6,23,.08);
  }
  .vs-meta {
    font-size: .85rem;
    color: #64748b;
  }
  .vs-kpi {
    border-radius: 16px;
    padding: 14px;
    background: #fff;
    border: 1px solid rgba(2,6,23,.06);
  }
  .vs-kpi .v { font-size: 1.35rem; font-weight: 800; }
</style>

<div class="vs-hero mb-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <h2 class="mb-1">VisioSeek — Recherche par Similarité 3D</h2>
      <div class="opacity-75">Charge un modèle <b>.obj</b> et retrouve les modèles les plus proches géométriquement (CBIR 3D).</div>
    </div>
    <div class="d-flex gap-2">
  <a href="{{ route('images.index') }}" class="btn btn-light vs-btn">⟵ 2D</a>
  <a href="{{ route('shape3d.models') }}" class="btn btn-outline-light vs-btn">
     Modèles 3D
  </a>
</div>

  </div>
</div>

{{-- Upload Form --}}
<div class="card vs-card mb-4">
  <div class="card-body">
    @if($errors->any())
      <div class="alert alert-danger">
        <div class="fw-bold">Erreur</div>
        <ul class="mb-0">
          @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('shape3d.search') }}" enctype="multipart/form-data" class="row g-3">
      @csrf
      <div class="col-lg-6">
        <label class="form-label fw-semibold">Modèle 3D (.obj)</label>
        <input type="file" name="model" class="form-control" accept=".obj" required>
        <div class="form-text">Le fichier est envoyé à l’API Flask (/search-3d).</div>
      </div>

      <div class="col-lg-2">
        <label class="form-label fw-semibold">Top-K</label>
        <input type="number" name="top_k" class="form-control" value="{{ $topK ?? 10 }}" min="1" max="50">
      </div>

      <div class="col-lg-4 d-flex align-items-end gap-2">
        <button class="btn vs-btn vs-btn-primary w-100">Rechercher similaires</button>
      </div>
    </form>
  </div>
</div>

{{-- Stats --}}
@if($stats)
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="vs-kpi">
        <div class="vs-meta">Label requête (proxy)</div>
        <div class="v">{{ $stats['query_label'] }}</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="vs-kpi">
        <div class="vs-meta">Corrects / Top-K</div>
        <div class="v">{{ $stats['correct'] }} / {{ $stats['total'] }}</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="vs-kpi">
        <div class="vs-meta">Precision@K</div>
        <div class="v">{{ $stats['precision'] }}</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="vs-kpi">
        <div class="vs-meta">Filtre label</div>
        <select id="labelFilter" class="form-select">
          <option value="">Tous</option>
          @php
            $labels = collect($results)->pluck('label')->unique()->sort()->values();
          @endphp
          @foreach($labels as $lab)
            <option value="{{ $lab }}">{{ $lab }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
@endif

{{-- Results grid --}}
@if($results)
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Résultats (Top-{{ $topK }})</h4>
    <div class="text-muted small">Triés par distance FAISS (L2). Plus petit = plus similaire.</div>
  </div>

  <div id="resultsGrid" class="vs-grid">
    @foreach($results as $r)
      @php
        $label = $r['label'] ?? 'Unknown';
        $fname = $r['filename'] ?? '';
        $dist  = $r['distance'] ?? 0;

        // Convert distance to similarity score (visual only)
        // sim = 1/(1+dist) -> [0,1]
        $sim = 1 / (1 + max(0.0, (float)$dist));
        $bar = (int)round($sim * 100);

        // Resolve thumbnail path if file exists
        $thumb = null;
        foreach(($r['thumb_candidates'] ?? []) as $cand){
          $abs = base_path($cand);
          if (file_exists($abs)) { $thumb = $cand; break; }
        }
      @endphp

      <div class="card vs-card result-card" data-label="{{ $label }}">
        <div class="card-body">
          @if($thumb)
            <img class="vs-thumb mb-2" src="{{ asset($thumb) }}" alt="thumb">
          @else
            <div class="vs-thumb mb-2 d-flex align-items-center justify-content-center text-muted">
              No thumbnail
            </div>
          @endif

          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="vs-pill">{{ $label }}</span>
            <span class="text-muted small">d={{ number_format((float)$dist, 4) }}</span>
          </div>

          <div class="fw-semibold text-truncate" title="{{ $fname }}">{{ $fname }}</div>

          <div class="vs-meta mt-2">Score (visual)</div>
          <div class="vs-scorebar">
              <div class="score-fill" data-width="{{ $bar }}"></div>

          </div>

          <div class="d-flex justify-content-between mt-2">
            <span class="vs-meta">sim≈{{ number_format($sim, 3) }}</span>
            <button class="btn btn-sm btn-outline-secondary" type="button"
              onclick="openViewer('{{ addslashes($fname) }}','{{ addslashes($label) }}')">
              Viewer
            </button>
          </div>

        </div>
      </div>
    @endforeach
  </div>
@endif

{{-- Modal viewer placeholder (thumbnail + file name) --}}
<div class="modal fade" id="viewerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-header">
        <div>
          <div class="fw-bold" id="vTitle">Viewer</div>
          <div class="text-muted small" id="vSub">...</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          Viewer 3D (Three.js) peut être ajouté ici. Pour l’instant: aperçu info + thumbnail.
        </div>
        <div id="vBody" class="text-muted"></div>
      </div>
    </div>
  </div>
</div>

<script>
  // Filter by label
  const filter = document.getElementById('labelFilter');
  if (filter) {
    filter.addEventListener('change', () => {
      const val = filter.value;
      document.querySelectorAll('.result-card').forEach(card => {
        const lab = card.getAttribute('data-label');
        card.style.display = (!val || lab === val) ? '' : 'none';
      });
    });
  }

  function openViewer(filename, label){
    document.getElementById('vTitle').textContent = label;
    document.getElementById('vSub').textContent = filename;
    document.getElementById('vBody').innerHTML =
      `<div><b>Fichier</b> : ${escapeHtml(filename)}</div>
       <div><b>Label</b> : ${escapeHtml(label)}</div>
       <div class="mt-2 small text-muted">
         (Option) Charger le .obj côté client et l’afficher avec Three.js.
       </div>`;

    const modal = new bootstrap.Modal(document.getElementById('viewerModal'));
    modal.show();
  }

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, s => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[s]));
  }
  
  document.querySelectorAll('.score-fill').forEach(el => {
  const w = el.dataset.width;
  el.style.width = w + '%';
    });

</script>

@endsection
