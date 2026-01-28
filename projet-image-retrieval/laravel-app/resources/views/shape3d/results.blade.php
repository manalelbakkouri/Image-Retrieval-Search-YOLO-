@extends('layouts.app')
@section('title','VisioSeek — Résultats 3D')

@section('content')
<style>
  .vs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
  .vs-card{border-radius:16px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 22px rgba(15,23,42,.06)}
  .vs-thumb{width:100%;height:160px;object-fit:contain;background:#f8fafc;border-radius:14px;border:1px solid rgba(2,6,23,.08)}
  .vs-pill{border-radius:999px;padding:5px 10px;font-weight:700;background:rgba(2,132,199,.12);color:#075985;border:1px solid rgba(2,132,199,.22)}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Résultats similaires</h3>
    <div class="text-muted">Requête : <b>{{ $queryFile }}</b> — Top {{ $topK }}</div>
  </div>
  <a class="btn btn-outline-secondary" href="{{ route('shape3d.show',$queryFile) }}">⟵ Retour viewer</a>
</div>

@if(empty($results))
  <div class="alert alert-warning">Aucun résultat retourné.</div>
@else
  <div class="vs-grid">
    @foreach($results as $r)
      @php
        $label = $r['label'] ?? 'Unknown';
        $fname = $r['filename'] ?? '';
        $dist  = $r['distance'] ?? 0;

        // thumb resolve
        $thumb = null;
        foreach(($r['thumb_candidates'] ?? []) as $cand){
          if(file_exists(base_path($cand))) { $thumb = $cand; break; }
        }
      @endphp

      <div class="card vs-card">
        <div class="card-body">
          @if($thumb)
            <img class="vs-thumb mb-2" src="{{ asset($thumb) }}" alt="thumb">
          @else
            <div class="vs-thumb mb-2 d-flex align-items-center justify-content-center text-muted">No thumbnail</div>
          @endif

          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="vs-pill">{{ $label }}</span>
            <span class="text-muted small">d={{ number_format((float)$dist,4) }}</span>
          </div>

         <div class="fw-bold text-truncate" title="{{ $fname }}">{{ $fname }}</div>

        <div class="mt-2 d-flex gap-2">
          <a
            href="{{ route('shape3d.viewer.dataset', $fname) }}"
            target="_blank"
            class="btn btn-sm btn-outline-primary w-100"
          >
            Viewer 3D
          </a>
        </div>

        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection
