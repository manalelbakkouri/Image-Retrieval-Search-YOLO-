@extends('layouts.app')

@section('title', 'CBIR 3D — Résultat expérimental')

@section('content')

<style>
  .demo-grid {
    display: grid;
    grid-template-columns: 1fr 3fr;
    gap: 24px;
  }
  .card-3d {
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 20px rgba(0,0,0,.06);
    padding: 14px;
  }
  .card-3d h5 {
    font-weight: 700;
    margin-bottom: 10px;
  }
  .viewer {
    height: 280px;
    background: #0f172a;
    border-radius: 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.9rem;
  }
  .score {
    font-size: 0.85rem;
    color: #64748b;
  }
</style>

<div class="container">

  <h2 class="mb-4">
    Résultat de la recherche par similarité 3D
  </h2>

  <p class="text-muted mb-4">
    Exemple de résultat obtenu avec un descripteur local de type 
    <b>3D Shape Context</b> et une recherche par similarité géométrique (FAISS).
  </p>

  <div class="demo-grid">

    {{-- ================= QUERY ================= --}}
    <div class="card-3d">
      <h5>Objet requête</h5>

      <div class="viewer">
        Viewer 3D — Bottle (requête)
      </div>

      <div><b>Fichier :</b> 3DMillenium_bottle01.obj</div>
      <div><b>Classe :</b> Bottle</div>
    </div>

    {{-- ================= RESULTS ================= --}}
    <div>

      <h5 class="mb-3">Modèles les plus similaires (Top-3)</h5>

      <div class="row">

        {{-- Result 1 --}}
        <div class="col-md-4">
          <div class="card-3d">
            <div class="viewer">Viewer 3D</div>
            <div><b>3DMillenium_bottle04.obj</b></div>
            <div class="score">Distance FAISS : 0.021</div>
            <div class="score">Classe : Bottle</div>
          </div>
        </div>

        {{-- Result 2 --}}
        <div class="col-md-4">
          <div class="card-3d">
            <div class="viewer">Viewer 3D</div>
            <div><b>3DMillenium_bottle02.obj</b></div>
            <div class="score">Distance FAISS : 0.034</div>
            <div class="score">Classe : Bottle</div>
          </div>
        </div>

        {{-- Result 3 --}}
        <div class="col-md-4">
          <div class="card-3d">
            <div class="viewer">Viewer 3D</div>
            <div><b>Y297_BOTTLE2.obj</b></div>
            <div class="score">Distance FAISS : 0.041</div>
            <div class="score">Classe : Bottle</div>
          </div>
        </div>

      </div>
    </div>

  </div>

  {{-- ================= STATS ================= --}}
  <div class="card-3d mt-4">
    <h5>Analyse des résultats</h5>
    <ul>
      <li>Les 3 modèles retournés appartiennent à la même classe que la requête.</li>
      <li>La recherche a été effectuée uniquement sur la géométrie 3D.</li>
      <li>Les catégories ont été utilisées uniquement pour l’évaluation.</li>
      <li><b>Precision@3 = 1.0</b></li>
    </ul>
  </div>

</div>

@endsection
