@extends('admin.layout.app')

@section('title', 'Vista previa · ' . $titulo)

@section('content')
{{--
    Pantalla de espera de la vista previa.

    El render es un `astro build` completo: 17 segundos medidos, y más si la
    máquina está cargada. Lo que no se puede hacer con esa espera es dejar la
    pantalla en blanco —veinte segundos sin señal se leen como «esto está
    roto»—, de ahí la barra y el aviso de cuánto suele tardar.

    Cuando termina, la página real se enseña dentro de un iframe. Es la misma
    que verá el visitante: no hay un segundo diseño que mantener.
--}}
<div class="p-6" data-vista-previa data-estado="{{ $estadoUrl }}"
     data-destino="{{ route('admin.vista-previa.archivo', ['ruta' => '']) }}"
     data-ruta="{{ $ruta }}">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Vista previa</h1>
            <p class="text-sm text-gray-500">{{ $titulo }} <span class="text-gray-400">· {{ $ruta }}</span></p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.programas.index') }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Volver
            </a>
            <button type="button" data-rehacer
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Rehacer la vista previa
            </button>
        </div>
    </div>

    <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <strong>Esto no está publicado.</strong>
        Se genera aparte y nadie más puede verlo. Para que salga en el sitio hay que publicarlo.
    </div>

    <div data-esperando class="mt-6 rounded-xl border border-gray-200 bg-white p-8 text-center">
        <div class="mx-auto h-1.5 w-64 overflow-hidden rounded-full bg-gray-200">
            <div data-barra class="h-full w-1/3 rounded-full bg-blue-600 transition-all duration-500"></div>
        </div>
        <p class="mt-4 text-sm font-medium text-gray-700">Generando la vista previa…</p>
        <p class="mt-1 text-xs text-gray-500">
            Se reconstruye la página igual que al publicar. Suele tardar unos 20 segundos.
        </p>
        <p data-error class="mt-3 hidden text-sm font-medium text-red-600"></p>
    </div>

    <iframe data-marco hidden title="Vista previa de {{ $titulo }}"
            class="mt-6 h-[75vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
</div>

<script>
(function () {
    var caja = document.querySelector('[data-vista-previa]');
    if (!caja) return;

    var esperando = caja.querySelector('[data-esperando]');
    var marco = caja.querySelector('[data-marco]');
    var barra = caja.querySelector('[data-barra]');
    var error = caja.querySelector('[data-error]');
    var rehacer = caja.querySelector('[data-rehacer]');

    var destino = caja.dataset.destino.replace(/\/$/, '') + caja.dataset.ruta;
    var intentos = 0;

    function fallar(mensaje) {
        error.textContent = mensaje;
        error.classList.remove('hidden');
        barra.style.width = '100%';
        barra.classList.replace('bg-blue-600', 'bg-red-500');
    }

    function mostrar() {
        marco.src = destino;
        marco.hidden = false;
        esperando.remove();
    }

    function revisar() {
        // Tope de intentos: si el servicio de build no responde nunca, esto no
        // puede quedarse dando vueltas para siempre contra el servidor.
        if (++intentos > 60) {
            return fallar('La vista previa está tardando demasiado. Prueba a rehacerla.');
        }

        barra.style.width = Math.min(90, 20 + intentos * 6) + '%';

        fetch(caja.dataset.estado, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (datos) {
                if (datos.construyendo) return setTimeout(revisar, 1500);

                if (datos.ultimo && datos.ultimo.estado === 'fallido') {
                    return fallar('El render falló. Revisa el registro del servicio de build.');
                }

                // Aunque haya terminado bien, puede ser el resultado de un
                // render anterior: se comprueba que la pagina exista de verdad
                // antes de ensenarla, o el iframe mostraria un 404.
                fetch(destino, { method: 'HEAD' })
                    .then(function (r) {
                        if (r.ok) return mostrar();
                        if (intentos > 3) {
                            return fallar('La página no aparece en la vista previa. ¿Tiene el borrador un enlace válido?');
                        }
                        setTimeout(revisar, 1500);
                    })
                    .catch(function () { setTimeout(revisar, 1500); });
            })
            .catch(function () { setTimeout(revisar, 2000); });
    }

    rehacer && rehacer.addEventListener('click', function () { location.reload(); });

    setTimeout(revisar, 1500);
})();
</script>
@endsection
