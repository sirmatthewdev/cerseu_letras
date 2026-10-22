@php
    $vista = \App\Support\EstadoDePublicacion::presentar($publicacion);
    $puedeReintentar = in_array($publicacion['estado'], ['fallido', 'atascado', 'desconocido', 'publicado'], true);
@endphp

{{-- Se refresca solo: quien acaba de guardar ve pasar de «pendiente» a
     «publicado» sin recargar. --}}
<x-filament-widgets::widget wire:poll.10s>
    <x-filament::callout
        :color="$vista['color']"
        :icon="$vista['icono']"
        :heading="$vista['titulo']"
        :description="$publicacion['detalle'] ? \Illuminate\Support\Str::limit($publicacion['detalle'], 600) : null"
    >
        @if ($puedeReintentar)
            <x-slot name="controls">
                <x-filament::button
                    wire:click="volverAPublicar"
                    :color="in_array($publicacion['estado'], ['fallido', 'atascado'], true) ? 'danger' : 'gray'"
                    size="sm"
                    icon="heroicon-o-arrow-path"
                >
                    Volver a publicar
                </x-filament::button>
            </x-slot>
        @endif
    </x-filament::callout>
</x-filament-widgets::widget>
