<!DOCTYPE html>
<html lang="es" class="no-js">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="view-transition" content="same-origin">
    <script>document.documentElement.classList.replace('no-js', 'js');</script>
    <title>@yield('title', 'Admin') - CERSEU Letras UNMSM</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Tipografías (Inter + Playfair Display) auto-alojadas vía @fontsource,
         empaquetadas en app.css por Vite. Ya no se carga Google Fonts. --}}
    {{-- Iconos: Font Awesome migrado a SVG inline (owenvoke/blade-fontawesome).
         Sin CSS/webfont ni requests a CDN; sin flash de iconos. --}}

    <style>
        :root {
            /* --brand y --brand-dark: definidos globalmente en resources/css/app.css */
            --brand-accent: #d4a017;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .font-serif {
            font-family: 'Playfair Display', serif;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
        }

        .nav-link {
            border-left: 3px solid transparent;
        }

        .nav-link.active {
            background-color: rgba(20, 59, 99, 0.08);
            border-left-color: var(--brand);
            color: var(--brand);
        }

        .nav-link.active i {
            color: var(--brand);
        }

        .nav-link:hover {
            background-color: rgba(20, 59, 99, 0.04);
        }

        .nav-link:hover i {
            color: var(--brand);
        }

        /* Stat Cards */
        .stat-card {
            border-top: 4px solid var(--card-color, var(--brand));
        }

        .stat-brand {
            --card-color: var(--brand);
            --icon-bg: rgba(20, 59, 99, 0.1);
        }

        .stat-accent {
            --card-color: var(--brand-accent);
            --icon-bg: rgba(212, 160, 23, 0.1);
        }

        .stat-dark {
            --card-color: var(--brand-dark);
            --icon-bg: rgba(15, 43, 72, 0.1);
        }

        .stat-icon {
            background-color: var(--icon-bg);
            color: var(--card-color);
        }

        /* Quick Actions */
        .quick-action-card:hover {
            border-color: var(--brand-accent);
            box-shadow: 0 5px 15px rgba(212, 160, 23, 0.15);
        }

        .quick-action-card:hover .action-icon-circle {
            background: var(--brand);
            color: white;
        }

        /* Buttons */
        .btn-brand-outline {
            border-color: var(--brand);
            color: var(--brand);
        }

        .btn-brand-outline:hover {
            background: var(--brand);
            color: white;
        }

        .btn-accent-outline {
            border-color: var(--brand-accent);
            color: #b58500;
        }

        .btn-accent-outline:hover {
            background: var(--brand-accent);
            color: white;
        }

        /* Mobile */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* Custom Utilities Compatibility */
        .bg-brand-azul {
            background-color: var(--brand);
        }

        .text-brand-azul {
            color: var(--brand);
        }

        .border-brand-azul {
            border-color: var(--brand);
        }

        .ring-brand-azul {
            --tw-ring-color: var(--brand);
        }

        .focus\:ring-brand-azul:focus {
            --tw-ring-opacity: 1;
            --tw-ring-color: var(--brand);
        }

        .hover\:bg-brand-azul:hover {
            background-color: var(--brand-dark);
        }

        .hover\:border-brand-azul:hover {
            border-color: var(--brand);
        }

        .focus\:border-brand-azul:focus {
            border-color: var(--brand);
        }

        .bg-brand-gold {
            background-color: var(--brand-accent);
        }

        .text-brand-gold {
            color: var(--brand-accent);
        }

        .border-brand-gold {
            border-color: var(--brand-accent);
        }

        .ring-brand-gold {
            --tw-ring-color: var(--brand-accent);
        }

        .focus\:ring-brand-gold:focus {
            --tw-ring-opacity: 1;
            --tw-ring-color: var(--brand-accent);
        }

        .focus\:border-brand-gold:focus {
            border-color: var(--brand-accent);
        }

        .hover\:bg-brand-gold:hover {
            background-color: var(--brand-accent);
        }

        .hover\:text-brand-gold:hover {
            color: var(--brand-accent);
        }

        .hover\:border-brand-gold:hover {
            border-color: var(--brand-accent);
        }

        .text-brand-navy {
            color: #1e3a8a;
        }

        .bg-brand-navy {
            background-color: #1e3a8a;
        }

        .border-brand-navy {
            border-color: #1e3a8a;
        }

        .ring-brand-navy {
            --tw-ring-color: #1e3a8a;
        }

        .focus\:ring-brand-navy:focus {
            --tw-ring-opacity: 1;
            --tw-ring-color: #1e3a8a;
        }

        .focus\:border-brand-navy:focus {
            border-color: #1e3a8a;
        }

        .hover\:border-brand-navy:hover {
            border-color: #1e3a8a;
        }

        .hover\:bg-brand-navy:hover {
            background-color: #14285e;
        }

        /* Red de seguridad de foco visible: algunos formularios suprimen el
           outline nativo (focus:outline-none) confiando en focus:ring-* /
           focus:border-* con colores personalizados (brand-azul/gold/navy)
           que no siempre ganan la cascada frente a los estilos base de
           @tailwindcss/forms. Este outline explícito garantiza un foco
           visible en todos los campos del admin sin depender de esa cadena. */
        /* Red de seguridad de foco visible: varios formularios suprimen el
           outline nativo (focus:outline-none) confiando en focus:ring-*
           con colores personalizados que no siempre ganan la cascada.
           !important sin @layer (Tailwind aquí no compila con @layer real)
           garantiza un foco visible en todo el admin sin depender de esa
           cadena de utilidades. */
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        button:focus-visible,
        a:focus-visible,
        [tabindex]:focus-visible {
            outline: 2px solid var(--brand) !important;
            outline-offset: 1px !important;
        }
    </style>

    @stack('styles')
</head>

<body class="bg-gray-100 text-gray-800">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:text-brand-azul focus:font-bold focus:px-4 focus:py-2 focus:rounded focus:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-azul">
        Saltar al contenido principal
    </a>

    <!-- Overlay Mobile -->
    <div class="sidebar-overlay fixed inset-0 bg-black/40 z-40 hidden backdrop-blur-sm" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside
        class="sidebar fixed top-0 left-0 h-screen bg-white border-r border-gray-100 shadow-sm z-50 flex flex-col transition-transform duration-300"
        id="mainSidebar">
        <!-- Brand -->
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#143B63] to-[#0F2B48] text-white flex items-center justify-center shadow-lg">
                    <x-fas-book-open />
                </div>
                <div>
                    <h1 class="font-bold text-[#143B63] text-lg leading-tight">Letras</h1>
                    <p class="text-xs text-gray-500 font-medium">CERSEU Admin</p>
                </div>
            </div>
            <button class="lg:hidden absolute top-4 right-4 text-gray-400 hover:text-gray-600" id="closeSidebar">
                <x-fas-times class="text-lg" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-1" aria-label="Administración">
            <p class="text-xs font-bold uppercase tracking-wider text-[#d4a017] mb-2 ml-2">General</p>

            <a href="{{ route('admin.dashboard') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.dashboard') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-chart-pie class="w-5 text-lg {{ request()->routeIs('admin.dashboard') ? '' : 'text-gray-500' }}" />
                <span>Dashboard</span>
            </a>

            {{--
                Enlace al panel nuevo mientras dura la migración.

                Los dos paneles escriben en la misma base, así que no hay riesgo
                de que los datos se separen; lo que sí puede pasar es que alguien
                no sepa cuál es cuál. Este enlace lo dice.

                Se retira, junto con el resto de esta barra, cuando Filament
                cubra los 18 recursos y pase a ocupar /admin.
            --}}
            <a href="/panel"
                class="nav-link mt-6 flex items-center gap-3 rounded-lg border border-dashed border-[#d4a017] px-3 py-3 text-base font-medium text-gray-800 transition-all hover:bg-gray-50">
                <x-fas-wand-magic-sparkles class="w-5 text-lg text-[#d4a017]" />
                <span>Panel nuevo <span class="text-xs font-normal text-gray-500">(en migración)</span></span>
            </a>

            <p class="text-xs font-bold uppercase tracking-wider text-[#d4a017] mb-2 ml-2 mt-6">Gestión Académica</p>

            <a href="{{ route('admin.programas.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.programas.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-graduation-cap class="w-5 text-lg {{ request()->routeIs('admin.programas.*') ? '' : 'text-gray-500' }}" />
                <span>Cursos</span>
            </a>

            <a href="{{ route('admin.docentes.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.docentes.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-chalkboard-teacher class="w-5 text-lg {{ request()->routeIs('admin.docentes.*') ? '' : 'text-gray-500' }}" />
                <span>Docentes</span>
            </a>

            <p class="text-xs font-bold uppercase tracking-wider text-[#d4a017] mb-2 ml-2 mt-6">Administración</p>

            <a href="{{ route('admin.testimonios.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.testimonios.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-comments class="w-5 text-lg {{ request()->routeIs('admin.testimonios.*') ? '' : 'text-gray-500' }}" />
                <span>Testimonios</span>
            </a>

            <a href="{{ route('admin.documents.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.documents.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-folder-open class="w-5 text-lg {{ request()->routeIs('admin.documents.*') ? '' : 'text-gray-500' }}" />
                <span>Documentos</span>
            </a>

            <a href="{{ route('admin.directorio.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.directorio.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-address-book class="w-5 text-lg {{ request()->routeIs('admin.directorio.*') ? '' : 'text-gray-500' }}" />
                <span>Directorio</span>
            </a>

            <a href="{{ route('admin.cronograma.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.cronograma.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-calendar-alt class="w-5 text-lg {{ request()->routeIs('admin.cronograma.*') ? '' : 'text-gray-500' }}" />
                <span>Cronograma</span>
            </a>

            <a href="{{ route('admin.leads.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.leads.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-inbox class="w-5 text-lg {{ request()->routeIs('admin.leads.*') ? '' : 'text-gray-500' }}" />
                <span>Solicitudes</span>
                @php $leadsPendientes = \App\Models\Lead::where('created_at', '>=', now()->subDays(7))->count(); @endphp
                @if ($leadsPendientes > 0)
                    <span class="ml-auto px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-bold">{{ $leadsPendientes }}</span>
                @endif
            </a>

            <a href="{{ route('admin.cronograma-admision.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.cronograma-admision.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-timeline class="w-5 text-lg {{ request()->routeIs('admin.cronograma-admision.*') ? '' : 'text-gray-500' }}" />
                <span>Cronograma Admisión</span>
            </a>

            <a href="{{ route('admin.admision.index', 'talleres') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.admision.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-scroll class="w-5 text-lg {{ request()->routeIs('admin.admision.*') ? '' : 'text-gray-500' }}" />
                <span>Admisión Talleres</span>
            </a>

            <a href="{{ route('admin.informativos.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.informativos.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-info-circle class="w-5 text-lg {{ request()->routeIs('admin.informativos.*') ? '' : 'text-gray-500' }}" />
                <span>Recursos Informativos</span>
            </a>

            <a href="{{ route('admin.eventos.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.eventos.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-calendar-day class="w-5 text-lg {{ request()->routeIs('admin.eventos.*') ? '' : 'text-gray-500' }}" />
                <span>Eventos</span>
            </a>

            <a href="{{ route('admin.contenido.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.contenido.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-file-lines class="w-5 text-lg {{ request()->routeIs('admin.contenido.*') ? '' : 'text-gray-500' }}" />
                <span>Contenido</span>
            </a>

            <a href="{{ route('admin.anuncios.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.anuncios.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-bullhorn class="w-5 text-lg {{ request()->routeIs('admin.anuncios.*') ? '' : 'text-gray-500' }}" />
                <span>Anuncios de portada</span>
            </a>

            <a href="{{ route('admin.menu.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.menu.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-bars class="w-5 text-lg {{ request()->routeIs('admin.menu.*') ? '' : 'text-gray-500' }}" />
                <span>Menú de navegación</span>
            </a>

            <a href="{{ route('admin.papelera.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.papelera.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-trash-arrow-up class="w-5 text-lg {{ request()->routeIs('admin.papelera.*') ? '' : 'text-gray-500' }}" />
                <span>Papelera</span>
            </a>

            <a href="{{ route('admin.users.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.users.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-user-shield class="w-5 text-lg {{ request()->routeIs('admin.users.*') ? '' : 'text-gray-500' }}" />
                <span>Usuarios</span>
            </a>

            <a href="{{ route('admin.settings.index') }}"
                class="nav-link flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium transition-all {{ request()->routeIs('admin.settings.*') ? 'active' : 'text-gray-800 hover:bg-gray-50' }}">
                <x-fas-cog class="w-5 text-lg {{ request()->routeIs('admin.settings.*') ? '' : 'text-gray-500' }}" />
                <span>Configuración</span>
            </a>
        </nav>

        <!-- Logout -->
        <div class="p-4 border-t border-gray-100">
            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                <x-fas-sign-out-alt />
                <span>Cerrar Sesión</span>
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-[260px] min-h-screen flex flex-col transition-all duration-300">
        <!-- Header -->
        <header
            class="sticky top-0 z-30 bg-white border-b border-gray-100 px-4 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    class="lg:hidden w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 shadow-sm bg-white"
                    id="sidebarToggle">
                    <x-fas-bars class="text-gray-600" />
                </button>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">@yield('title', 'Dashboard')</h1>
                    @hasSection('subtitle')
                        <p class="text-sm text-gray-500">@yield('subtitle')</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a href="{{ url('/') }}" target="_blank" rel="noopener noreferrer"
                    class="hidden md:inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-full hover:bg-gray-50 transition-colors">
                    <x-fas-external-link-alt />
                    <span>Ver Web Pública</span>
                </a>

                <!-- User Dropdown -->
                <div class="relative" id="userDropdownWrapper">
                    <button id="userDropdownBtn" type="button" class="flex items-center gap-2">
                        <div class="hidden md:block text-right">
                            <p class="text-sm font-bold text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">Administrador</p>
                        </div>
                        <div
                            class="w-10 h-10 rounded-full bg-[#0F2B48] text-[#d4a017] flex items-center justify-center font-bold border-2 border-[#d4a017]">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    </button>
                    <div id="userDropdownMenu"
                        class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                        <p class="px-4 py-2 text-xs font-bold text-gray-400 uppercase">Mi Cuenta</p>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <x-fas-user-circle class="text-gray-400" /> Perfil
                        </a>
                        <a href="{{ route('admin.settings.index') }}"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <x-fas-cog class="text-gray-400" /> Configuración
                        </a>
                        <hr class="my-2 border-gray-100">
                        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <x-fas-sign-out-alt /> Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main id="main-content" class="flex-1 p-4 lg:p-8 w-full">
            {{-- Alerts (auto-descartables vía <x-flash-message>) --}}
            <x-flash-message type="success" />
            <x-flash-message type="error" />

            @if($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <div class="flex items-center mb-2">
                        <x-fas-exclamation-triangle class="mr-3 text-red-500" />
                        <strong>Errores de validación:</strong>
                    </div>
                    <ul class="list-disc list-inside ml-8 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar mobile
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('sidebarToggle');
            const close = document.getElementById('closeSidebar');

            toggle?.addEventListener('click', () => {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            });

            [close, overlay].forEach(el => {
                el?.addEventListener('click', () => {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            });

            // User dropdown
            const dropdownBtn  = document.getElementById('userDropdownBtn');
            const dropdownMenu = document.getElementById('userDropdownMenu');

            dropdownBtn?.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', function () {
                dropdownMenu?.classList.add('hidden');
            });

            dropdownMenu?.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
    </script>

    {{-- Modal de confirmación de eliminación reutilizable (event-driven, Alpine) --}}
    <x-confirm-delete-modal />

    @include('layouts.partials.toast-container')

    @stack('scripts')
</body>

</html>