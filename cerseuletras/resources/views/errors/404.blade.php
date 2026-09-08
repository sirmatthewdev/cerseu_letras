{{--
    404 de Laravel.

    Ya no es la del sitio: el sitio público lo sirve Nginx desde el `dist/` de
    Astro y su 404 está allí (sitio/src/pages/404.astro). Aquí solo llegan las
    direcciones que Laravel sigue atendiendo —/admin y la sesión—, así que la
    página se dirige a quien administra y no al visitante.

    Autocontenida, como las de 500 y 503: una plantilla de error que hereda de
    un layout se cae cuando el layout es justo lo que falta, y devuelve un 500
    en lugar del 404 que se pedía.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada — {{ config('app.name') }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { color-scheme: light; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f2744;
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            text-align: center;
            padding: 2rem;
        }

        .codigo { font-size: 4rem; font-weight: 700; color: #b6a350; margin: 0; }
        h1 { font-size: 1.5rem; margin: .5rem 0 0; }
        p { color: #cbd5e1; margin: 1rem 0 2rem; }
        .enlaces { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; }

        a {
            border-radius: .5rem;
            padding: .7rem 1.4rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgb(255 255 255 / .3);
            color: #fff;
        }

        a.principal { background: #b6a350; border-color: #b6a350; color: #0f2744; }
    </style>
</head>

<body>
    <main>
        <p class="codigo">404</p>
        <h1>Esta dirección no existe</h1>
        <p>Puede que el enlace esté mal escrito o que la pantalla se haya retirado.</p>

        <div class="enlaces">
            <a class="principal" href="{{ \Filament\Facades\Filament::getPanel('admin')->getUrl() }}">Ir al panel</a>
            <a href="{{ url('/') }}">Ir al sitio</a>
        </div>
    </main>
</body>

</html>
