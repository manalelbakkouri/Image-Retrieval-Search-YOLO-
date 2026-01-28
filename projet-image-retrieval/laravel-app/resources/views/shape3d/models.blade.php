@extends('layouts.app')

@section('title','VisioSeek — Modèles 3D')

@section('content')
<style>
  .vs-hero{
    background:linear-gradient(135deg,#111827,#0ea5e9);
    border-radius:18px;
    color:#fff;
    padding:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.15)
  }
  .vs-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
    gap:14px
  }
  .vs-card{
    border-radius:16px;
    border:1px solid rgba(15,23,42,.08);
    box-shadow:0 8px 22px rgba(15,23,42,.06);
    overflow:hidden
  }
  .vs-btn{border-radius:12px;font-weight:700}
  .vs-mini{font-size:.85rem;color:#64748b}
</style>

{{-- ========================= --}}
{{-- HERO + UPLOAD --}}
{{-- ========================= --}}
<div class="vs-hero mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h3 class="mb-1">Catalogue des modèles 3D</h3>
    <div class="opacity-75">
      Modèles <b>.obj</b> uploadés — visualisation 3D & recherche par similarité
    </div>
  </div>

  {{-- Upload --}}
  <form method="POST"
        action="{{ route('shape3d.upload') }}"
        enctype="multipart/form-data"
        class="d-flex gap-2">
    @csrf
    <input type="file"
           name="model"
           accept=".obj"
           class="form-control form-control-sm"
           required
           style="max-width:220px;">
    <button class="btn btn-light vs-btn btn-sm">
      Importer
    </button>
  </form>
</div>

{{-- ========================= --}}
{{-- MESSAGES --}}
{{-- ========================= --}}
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if(session('success'))
  <div class="alert alert-success">
    {{ session('success') }}
  </div>
@endif

{{-- ========================= --}}
{{-- CATALOGUE --}}
{{-- ========================= --}}
@if($files->isEmpty())
  <div class="alert alert-info">
    Aucun modèle 3D uploadé pour le moment.
  </div>
@else
  <div class="vs-grid">
    @foreach($files as $f)
      @php
        $filename = basename($f);
      @endphp

      <div class="card vs-card">
        <div class="card-body">

          <div class="fw-bold text-truncate"
               title="{{ $filename }}">
            {{ $filename }}
          </div>

          <div class="vs-mini mt-1">
            Visualiser le modèle ou lancer une recherche CBIR 3D
          </div>

        <div class="d-flex gap-2 mt-3">

  {{-- Viewer --}}
  <a href="{{ route('shape3d.show', $f) }}"
     class="btn btn-primary vs-btn btn-sm w-100">
    Viewer
  </a>

  {{-- Indexer --}}
  <form method="POST"
        action="{{ route('shape3d.index.model', $f) }}">
    @csrf
    <button class="btn btn-warning vs-btn btn-sm">
      Indexer
    </button>
  </form>

</div>


        </div>
      </div>
    @endforeach
  </div>
@endif

@endsection
