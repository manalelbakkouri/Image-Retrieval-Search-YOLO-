@extends('layouts.app')
@section('title','Résultats')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h2 class="title-xl mb-1">VisioSeek Results</h2>
    <div class="text-muted-2">Résultats classés, filtres rapides, score visuel.</div>

  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-soft" href="{{ route('images.show', $queryDetection->image) }}">Retour</a>
    <a class="btn btn-ghost" href="{{ route('images.index') }}">Galerie</a>
  </div>
</div>

<div class="row g-3">
  {{-- Filters --}}
  <div class="col-lg-3">
    <div class="card-glass p-3">
      <div class="fw-semibold mb-2">Filtres</div>

      <div class="mb-2">
        <label class="form-label small text-muted-2 mb-1">Nom d’image</label>
        <input id="qInput" class="form-control" placeholder="Ex : dog_12.jpg">
      </div>

      <div class="mb-2">
        <label class="form-label small text-muted-2 mb-1">Score minimum</label>
        <input id="minScore" type="range" class="form-range" min="0" max="100" value="0">
        <div class="d-flex justify-content-between small text-muted-2">
          <span>0</span><span id="minScoreLabel">0</span><span>100</span>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label small text-muted-2 mb-1">Tri</label>
        <select id="sortSel" class="form-select">
          <option value="best">Meilleurs d’abord</option>
          <option value="worst">Moins bons d’abord</option>
          <option value="name">Nom (A→Z)</option>
        </select>
      </div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-soft w-100" id="btnReset" type="button">Réinitialiser</button>
        <button class="btn btn-brand w-100" id="btnTop" type="button">Top 12</button>
      </div>

      <hr class="my-3">

      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-semibold">Résultats</div>
        <span class="badge-soft" id="statCount">—</span>
      </div>

      <div class="small text-muted-2 mt-2">
        Les barres sont normalisées sur la liste pour un affichage lisible.
      </div>
    </div>
  </div>

  {{-- Grid --}}
  <div class="col-lg-9">
    <div class="card-glass p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Images similaires</div>
        <div class="small text-muted-2">Cliquez une vignette pour ouvrir l’image.</div>
      </div>

      <div class="row" id="grid">
        @foreach($results as $r)
          @php $img = $images[$r['image_id']] ?? null; @endphp
          @if($img)
            <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4 result-card"
                 data-name="{{ strtolower($img->original_name) }}"
                 data-score="{{ $r['score'] }}"
                 data-imageid="{{ $img->id }}">
              <div class="card-soft overflow-hidden h-100 lift">

                <a href="{{ route('images.show', $img) }}" class="text-decoration-none text-dark">
                  <div style="position:relative;">
                    <img src="{{ asset('storage/'.$img->path) }}" class="w-100" style="height:165px; object-fit:cover;" alt="thumb">
                    <div class="thumb-overlay"></div>
                    <div style="position:absolute; left:12px; bottom:10px;">
                      <span class="badge bg-dark bg-opacity-75">Image #{{ $img->id }}</span>
                    </div>
                  </div>
                </a>

                <div class="p-3">
                  <div class="fw-semibold text-truncate" title="{{ $img->original_name }}">{{ $img->original_name }}</div>
                  <div class="small text-muted-2">{{ $img->width }}×{{ $img->height }}</div>

                  <div class="mt-2">
                    <div class="d-flex justify-content-between small text-muted-2">
                      <span>Score</span>
                      <span class="scoreLabel">—</span>
                    </div>
                    <div class="progress" style="height:8px; border-radius:999px;">
                      <div class="progress-bar" role="progressbar" style="width:0%; background: linear-gradient(135deg, var(--brand-1), var(--brand-2));"></div>
                    </div>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="badge-brand">{{ $queryDetection->class_name }}</span>
                    <span class="badge-soft">raw: {{ number_format($r['score'], 4) }}</span>
                  </div>
                </div>

              </div>
            </div>
          @endif
        @endforeach
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const cards = Array.from(document.querySelectorAll('.result-card'));
  const qInput = document.getElementById('qInput');
  const minScore = document.getElementById('minScore');
  const minScoreLabel = document.getElementById('minScoreLabel');
  const sortSel = document.getElementById('sortSel');
  const statCount = document.getElementById('statCount');
  const btnReset = document.getElementById('btnReset');
  const btnTop = document.getElementById('btnTop');

  if (!cards.length) return;

  const rawScores = cards.map(c => parseFloat(c.getAttribute('data-score') || '0'));
  const minRaw = Math.min(...rawScores);
  const maxRaw = Math.max(...rawScores);
  const denom = (maxRaw - minRaw) || 1;

  // Normalisation visuelle 0..100 (par défaut : score plus grand = meilleur)
  cards.forEach(c => {
    const raw = parseFloat(c.getAttribute('data-score') || '0');
    let norm = 1 - ((raw - minRaw) / denom);     // 0..1
    let pct = Math.round(norm * 100);

    c.dataset.norm = String(pct);

    const bar = c.querySelector('.progress-bar');
    const label = c.querySelector('.scoreLabel');
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = pct + '/100';
  });

  function apply(){
    const q = (qInput.value || '').trim().toLowerCase();
    const minPct = parseInt(minScore.value || '0', 10);
    minScoreLabel.textContent = String(minPct);

    let visible = cards.filter(c => {
      const name = c.dataset.name || '';
      const pct = parseInt(c.dataset.norm || '0', 10);
      return (!q || name.includes(q)) && (pct >= minPct);
    });

    const mode = sortSel.value;
    if (mode === 'best') visible.sort((a,b)=> parseInt(b.dataset.norm)-parseInt(a.dataset.norm));
    if (mode === 'worst') visible.sort((a,b)=> parseInt(a.dataset.norm)-parseInt(b.dataset.norm));
    if (mode === 'name') visible.sort((a,b)=> (a.dataset.name||'').localeCompare(b.dataset.name||''));

    cards.forEach(c => c.style.display = 'none');
    visible.forEach(c => c.style.display = '');

    const grid = document.getElementById('grid');
    visible.forEach(c => grid.appendChild(c));

    statCount.textContent = `${visible.length} affichés`;
  }

  qInput.addEventListener('input', apply);
  minScore.addEventListener('input', apply);
  sortSel.addEventListener('change', apply);

  btnReset.addEventListener('click', () => {
    qInput.value = '';
    minScore.value = '0';
    sortSel.value = 'best';
    apply();
  });

  btnTop.addEventListener('click', () => {
    qInput.value = '';
    minScore.value = '0';
    sortSel.value = 'best';
    apply();

    const visible = cards
      .filter(c => c.style.display !== 'none')
      .sort((a,b)=> parseInt(b.dataset.norm)-parseInt(a.dataset.norm))
      .slice(12);

    visible.forEach(c => c.style.display = 'none');
    statCount.textContent = `${cards.filter(c => c.style.display !== 'none').length} affichés`;
  });

  apply();
})();
</script>
@endpush
@endsection
