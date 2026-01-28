@extends('layouts.app')

@section('content')
<div class="container mt-4">

  <div class="text-center mb-5">
    <h1 class="fw-bold">VisioSeek</h1>
    <p class="text-muted">Plateforme de recherche par le contenu (CBIR 2D & 3D)</p>
  </div>

  <div class="row g-4">

    <!-- 2D -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center">
          <h4>Recherche d’images 2D</h4>
          <p class="text-muted">
            Détection d’objets, descripteurs visuels, recherche par similarité
          </p>
          <a href="{{ route('images.index') }}" class="btn btn-primary">
            Accéder à la recherche 2D
          </a>
        </div>
      </div>
    </div>

    <!-- 3D -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center">
          <h4>Recherche de modèles 3D</h4>
          <p class="text-muted">
            Recherche par similarité géométrique basée sur descripteurs locaux
          </p>
          <a href="{{ route('shape3d.models') }}" class="btn btn-success">
            Accéder à la recherche 3D
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
