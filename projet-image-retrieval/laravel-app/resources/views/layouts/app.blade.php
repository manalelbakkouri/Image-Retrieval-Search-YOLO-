<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'VisioSeek')</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --brand-1:#4f46e5;   /* indigo */
      --brand-2:#06b6d4;   /* cyan */
      --ink:#0f172a;       /* slate-900 */
      --muted:#64748b;     /* slate-500 */
      --card: rgba(255,255,255,.78);
      --border: rgba(2,6,23,.08);
      --shadow: 0 18px 45px rgba(2,6,23,.10);
    }

    body{
      color: var(--ink);
      background:
        radial-gradient(1200px 600px at 10% 0%, rgba(79,70,229,.22), transparent 60%),
        radial-gradient(900px 500px at 90% 10%, rgba(6,182,212,.18), transparent 55%),
        radial-gradient(900px 500px at 50% 100%, rgba(79,70,229,.10), transparent 55%),
        #f8fafc;
      min-height: 100vh;
    }

    /* Topbar */
    .topbar{
      background: rgba(15,23,42,.72);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .brand-dot{
      width: 10px; height: 10px; border-radius: 50%;
      background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
      display:inline-block;
      box-shadow: 0 0 0 4px rgba(79,70,229,.20);
    }

    /* Containers / cards */
    .app-shell{ padding-top: 26px; padding-bottom: 30px; }
    .card-glass{
      background: var(--card);
      backdrop-filter: blur(10px);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      border-radius: 18px;
    }
    .card-soft{
      background: rgba(255,255,255,.92);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 12px 28px rgba(2,6,23,.08);
    }

    /* Typography */
    .text-muted-2{ color: var(--muted); }
    .title-xl{
      font-weight: 750;
      letter-spacing: -.02em;
    }

    /* Buttons */
    .btn-brand{
      border: 0;
      background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
      color: #fff;
      box-shadow: 0 12px 22px rgba(79,70,229,.20);
    }
    .btn-brand:hover{ filter: brightness(1.03); transform: translateY(-1px); }
    .btn-ghost{
      background: rgba(255,255,255,.10);
      border: 1px solid rgba(255,255,255,.18);
      color: #fff;
    }
    .btn-ghost:hover{ background: rgba(255,255,255,.16); }
    .btn-soft{
      background: rgba(79,70,229,.08);
      border: 1px solid rgba(79,70,229,.18);
      color: var(--brand-1);
    }
    .btn-soft:hover{ background: rgba(79,70,229,.12); }
    .btn-danger-soft{
      background: rgba(239,68,68,.10);
      border: 1px solid rgba(239,68,68,.22);
      color: #ef4444;
    }
    .btn-danger-soft:hover{ background: rgba(239,68,68,.14); }

    .btn{ border-radius: 14px; transition: all .12s ease; }
    .btn-sm{ border-radius: 12px; }

    /* Badges */
    .badge-soft{
      background: rgba(2,6,23,.06);
      border: 1px solid rgba(2,6,23,.08);
      color: var(--ink);
      border-radius: 999px;
      padding: .35rem .55rem;
      font-weight: 600;
    }
    .badge-brand{
      background: rgba(79,70,229,.12);
      border: 1px solid rgba(79,70,229,.22);
      color: var(--brand-1);
      border-radius: 999px;
      padding: .35rem .55rem;
      font-weight: 650;
    }

    /* Inputs */
    .form-control, .form-select{
      border-radius: 14px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(255,255,255,.9);
    }
    .form-control:focus, .form-select:focus{
      box-shadow: 0 0 0 .25rem rgba(79,70,229,.18);
      border-color: rgba(79,70,229,.35);
    }

    /* Cards hover */
    .lift{ transition: transform .14s ease, box-shadow .14s ease; }
    .lift:hover{ transform: translateY(-2px); box-shadow: 0 20px 45px rgba(2,6,23,.14); }

    /* Thumbnails */
    .thumb{
      height: 185px;
      object-fit: cover;
    }
    .thumb-overlay{
      position:absolute; inset:0;
      background: linear-gradient(to top, rgba(2,6,23,.55), rgba(2,6,23,0));
    }

    /* Modal */
    .modal-content{ border-radius: 18px; border: 1px solid rgba(2,6,23,.10); }
    /* VisioSeek Brand */
    .brand-dot{
      width: 12px; height: 12px; border-radius: 50%;
      background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
      display:inline-block;
      box-shadow:
        0 0 0 5px rgba(79,70,229,.16),
        0 0 22px rgba(6,182,212,.35);
    }

    .brand-name{
      font-weight: 850;
      letter-spacing: -.03em;
    }

    .brand-badge{
      background: linear-gradient(135deg, rgba(79,70,229,.18), rgba(6,182,212,.16));
      border: 1px solid rgba(255,255,255,.18);
      color: #e2e8f0;
      border-radius: 999px;
      padding: .28rem .55rem;
      font-weight: 650;
      font-size: .75rem;
    }

    /* Topbar more premium */
    .topbar{
      background: rgba(2,6,23,.72);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,.10);
    }

    .navbar .nav-link{
      color: rgba(226,232,240,.88);
    }
    .navbar .nav-link:hover{
      color: #fff;
    }
    .vs-swatch{
    display:flex;
    gap:10px;
    align-items:center;
    padding:10px 12px;
    border-radius:16px;
    border:1px solid rgba(2,6,23,.12);
    background: rgba(255,255,255,.95);
    box-shadow: 0 10px 20px rgba(2,6,23,.06);
  }
  .vs-swatch-color{
    width:44px;
    height:44px;
    border-radius:14px;
    border:1px solid rgba(2,6,23,.14);
    box-shadow: inset 0 0 0 2px rgba(255,255,255,.35);
  }
  .vs-swatch-meta{ line-height:1.1; }


  </style>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  @stack('styles')
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-dark topbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('images.index') }}">
      <span class="brand-dot"></span>
      <span class="brand-name">VisioSeek</span>
      <span class="brand-badge ms-2">Visual Search</span>
    </a>


    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item">
          <a class="nav-link" href="{{ route('images.index') }}">Galerie</a>
        </li>
        <li class="nav-item">
          <a class="btn btn-ghost btn-sm" href="{{ route('images.create') }}">Importer</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container app-shell">

  @if(session('success'))
    <div class="alert alert-success card-soft">
      {{ session('success') }}
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger card-soft">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>