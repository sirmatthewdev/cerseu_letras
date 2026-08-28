@extends('admin.layout.app')

@section('title', 'Gestión de Cursos')

@section('content')
    <!-- Header with Actions -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-serif font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Cursos Académicos
            </h2>
            <p class="mt-1 text-sm text-gray-500">Gestiona la oferta de Cursos y Talleres.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="{{ route('admin.programas.create') }}"
               class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand-azul hover:bg-unmsm-azul-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-azul transition-colors">
                <x-fas-plus class="mr-2" />
                Nuevo Curso
            </a>
        </div>
    </div>

    <!-- Filters / Search -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
        <form method="GET" action="{{ route('admin.programas.index') }}" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <x-fas-search class="text-gray-400" />
                </div>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-brand-azul focus:border-brand-azul sm:text-sm transition duration-150 ease-in-out"
                       placeholder="Buscar por nombre...">
            </div>
            <div class="w-full md:w-48">
                <select name="tipo"
                        class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-brand-azul focus:border-brand-azul sm:text-sm">
                    <option value="">Todos los tipos</option>
                    @foreach (\App\Models\TipoOferta::cases() as $t)
                        <option value="{{ $t->value }}" {{ request('tipo') == $t->value ? 'selected' : '' }}>{{ $t->plural() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-azul">
                <x-fas-filter class="mr-2" />
                Filtrar
            </button>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-serif">
                            Nombre del Curso
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-serif">
                            Tipo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-serif">
                            Mención
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider font-serif">
                            Estado
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($programas as $programa)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 {{ $programa->tipo == 'maestria' ? 'bg-yellow-50 text-brand-gold' : ($programa->tipo == 'diplomado' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-brand-navy') }} rounded-full flex items-center justify-center">
                                        <x-dynamic-component :component="'fas-' . str_replace('fa-', '', $programa->tipo == 'maestria' ? 'fa-graduation-cap' : ($programa->tipo == 'diplomado' ? 'fa-scroll' : 'fa-medal'))" class="text-xl" />
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $programa->titulo_completo }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $programa->tipo == 'maestria' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : ($programa->tipo == 'diplomado' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-100 text-blue-800 border border-blue-200') }}">
                                    {{ $programa->grado }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $programa->mencion ?? 'Sin mención' }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $programa->estado_badge }}"
                                    title="{{ \App\Models\Programa::ESTADOS[$programa->estado]['ayuda'] ?? '' }}">
                                    <span class="w-1.5 h-1.5 {{ $programa->estado_punto }} rounded-full mr-1.5"></span>
                                    {{ $programa->estado_label }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-3">
                                    {{--
                                        Un borrador no está en el sitio, así que
                                        «ver en el sitio web» llevaba a un 404.
                                        En su lugar va la vista previa, que lo
                                        genera aparte sin publicarlo.
                                    --}}
                                    @if ($programa->estado === \App\Models\Programa::ESTADO_BORRADOR)
                                        <a href="{{ route('admin.vista-previa.programa', $programa) }}"
                                           class="text-amber-600 transition-colors hover:text-amber-800" title="Vista previa (sin publicar)"
                                           aria-label="Vista previa de «{{ $programa->nombre }}», todavía sin publicar">
                                            <x-fas-eye-slash class="text-lg" />
                                        </a>
                                    @else
                                        <a href="{{ $programa->url }}" target="_blank" rel="noopener noreferrer"
                                           class="text-brand-navy hover:text-brand-gold transition-colors" title="Ver en sitio web"
                                           aria-label="Ver «{{ $programa->nombre }}» en el sitio web">
                                            <x-fas-eye class="text-lg" />
                                        </a>
                                    @endif

                                    <a href="{{ route('admin.programas.edit', $programa) }}"
                                       class="text-blue-600 hover:text-blue-800 transition-colors" title="Editar"
                                       aria-label="Editar «{{ $programa->nombre }}»">
                                        <x-fas-edit class="text-lg" />
                                    </a>

                                    <form action="{{ route('admin.programas.toggle', $programa) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="{{ $programa->is_active ? 'text-orange-500 hover:text-orange-700' : 'text-green-500 hover:text-green-700' }} transition-colors"
                                                title="{{ $programa->is_active ? 'Desactivar' : 'Activar' }}"
                                                aria-label="{{ $programa->is_active ? 'Desactivar' : 'Activar' }} «{{ $programa->nombre }}»">
                                            <x-dynamic-component :component="'fas-' . ($programa->is_active ? 'lock' : 'lock-open')" class="text-lg" />
                                        </button>
                                    </form>

                                    <button type="button"
                                            onclick="window.dispatchEvent(new CustomEvent('confirm-delete', { detail: { action: '{{ route('admin.programas.destroy', $programa) }}', name: '{{ addslashes($programa->nombre) }}', title: '¿Eliminar programa?' } }))"
                                            class="text-red-500 hover:text-red-700 transition-colors"
                                            title="Eliminar"
                                            aria-label="Eliminar «{{ $programa->nombre }}»">
                                        <x-fas-trash-alt class="text-lg" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <x-fas-graduation-cap class="text-2xl text-gray-400" />
                                    </div>
                                    <p class="text-gray-500">No hay cursos registrados</p>
                                    <a href="{{ route('admin.programas.create') }}" class="mt-3 text-brand-azul text-sm font-medium hover:underline">
                                        Crear el primer curso
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación: el listado ya no trae todas las filas de golpe. --}}
        <div class="px-4 py-3 border-t border-gray-100">
            {!! $programas->onEachSide(1)->links() !!}
        </div>

        </div>
    </div>

@endsection
