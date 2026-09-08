<?php

namespace Tests\Feature;

use App\Mail\NuevaSolicitudInformacion;
use App\Models\Lead;
use App\Models\Programa;
use App\Models\SiteSetting;
use App\Filament\Resources\Leads\Pages\ListLeads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Aviso por correo de las solicitudes.
 *
 * El formulario dejo de ser una vista de Blade y pasa por la API, que es la
 * unica puerta que queda: el sitio publico es estatico. Lo que se comprueba
 * aqui no cambia —a donde llega el aviso, a quien responde, que la solicitud
 * se guarde aunque el correo falle—, solo por donde entra.
 */
class CorreoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    private function programa(): Programa
    {
        return Programa::create([
            'nombre' => 'Diplomado de prueba',
            'slug' => 'diplomado-de-prueba',
            'grado' => 'Taller',
            'is_active' => true,
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);
    }

    /** La API identifica el programa por slug, no por id. */
    private function enviar(array $extra = [])
    {
        return $this->postJson('/api/v1/solicitudes/talleres', $this->solicitud($extra));
    }

    private function solicitud(array $extra = []): array
    {
        return $extra + [
            'nombres' => 'María',
            'apellidos' => 'Quispe Ríos',
            'correo' => 'maria.quispe@ejemplo.pe',
            'pais' => 'PE',
            'region' => 'Cusco',
            'telefono' => '987 654 321',
            'programa' => $this->programa()->slug,
        ];
    }

    public function test_al_enviar_el_formulario_sale_un_aviso(): void
    {
        Mail::fake();

        $this->enviar()->assertCreated();

        Mail::assertSent(NuevaSolicitudInformacion::class);
    }

    public function test_el_aviso_va_al_correo_de_admision_del_panel(): void
    {
        Mail::fake();

        // El destino sale de Configuración → Contacto, no de un valor fijo en
        // el código: cambiarlo ahí cambia a dónde llegan las solicitudes.
        $ajustes = SiteSetting::first() ?: SiteSetting::create(['site_name' => 'Posgrado']);
        $ajustes->update(['email_admision' => 'admisionposgrado.letras@unmsm.edu.pe']);
        SiteSetting::clearCache();

        $this->enviar();

        Mail::assertSent(
            NuevaSolicitudInformacion::class,
            fn ($correo) => $correo->hasTo('admisionposgrado.letras@unmsm.edu.pe')
        );
    }

    public function test_responder_al_aviso_escribe_al_solicitante(): void
    {
        Mail::fake();

        $this->enviar();

        // Sin esto habría que copiar el correo a mano desde el cuerpo.
        Mail::assertSent(
            NuevaSolicitudInformacion::class,
            fn ($correo) => $correo->hasReplyTo('maria.quispe@ejemplo.pe')
        );
    }

    public function test_el_remitente_es_un_buzon_real_del_dominio(): void
    {
        // Estaba en «hello@example.com», el valor de fábrica de Laravel: con un
        // remitente ajeno al dominio, SPF y DKIM mandan el correo a spam.
        $remitente = config('mail.from.address');

        $this->assertStringEndsWith('@unmsm.edu.pe', $remitente);
        $this->assertNotSame('hello@example.com', $remitente);
    }

    public function test_el_mensaje_lleva_los_datos_de_la_solicitud(): void
    {
        Mail::fake();

        $this->enviar();

        Mail::assertSent(NuevaSolicitudInformacion::class, function ($correo) {
            $cuerpo = $correo->render();

            return str_contains($cuerpo, 'María')
                && str_contains($cuerpo, 'Quispe Ríos')
                && str_contains($cuerpo, 'maria.quispe@ejemplo.pe')
                && str_contains($cuerpo, '987 654 321');
        });
    }

    public function test_la_solicitud_se_guarda_aunque_el_correo_falle(): void
    {
        // El envío va dentro de un try/catch: un fallo de SMTP no puede hacer
        // que se pierda la solicitud ni que el visitante vea un error.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        // Sin sesion que comprobar: la API responde 201 y el mensaje va en el
        // cuerpo, que es lo que lee el formulario del sitio estatico.
        $this->enviar()
            ->assertCreated()
            ->assertJsonStructure(['message']);

        $this->assertSame(1, Lead::count());
    }

    public function test_un_fallo_de_envio_queda_anotado_en_la_solicitud(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->enviar();

        $lead = Lead::firstOrFail();

        // Lo que hace visible el fallo en el panel. Sin esto, una solicitud sin
        // avisar es idéntica a una avisada.
        $this->assertTrue($lead->avisoPendiente());
        $this->assertStringContainsString('SMTP caído', $lead->aviso_error);
    }

    public function test_un_envio_correcto_deja_la_solicitud_sin_marca(): void
    {
        Mail::fake();

        $this->enviar();

        $lead = Lead::firstOrFail();

        $this->assertFalse($lead->avisoPendiente());
        $this->assertNotNull($lead->aviso_enviado_en);
    }

    public function test_el_modo_log_no_cuenta_como_enviado(): void
    {
        // En modo `log` Mail no lanza excepción, así que sin la comprobación
        // explícita el panel daría por avisadas solicitudes que nadie recibió.
        config(['mail.default' => 'log']);

        $this->enviar();

        $this->assertTrue(Lead::firstOrFail()->avisoPendiente());
    }

    public function test_se_puede_reenviar_el_aviso_desde_el_panel(): void
    {
        $lead = Lead::create($this->solicitud());
        $lead->forceFill(['aviso_error' => 'SMTP caído'])->save();

        Mail::fake();

        $this->actingAs($this->administrador());

        Livewire::test(ListLeads::class)
            ->callTableAction('reenviar', $lead);

        Mail::assertSent(NuevaSolicitudInformacion::class);
        $this->assertFalse($lead->fresh()->avisoPendiente());
    }

    public function test_reenviar_el_aviso_exige_haber_entrado_al_panel(): void
    {
        Lead::create($this->solicitud());

        // La accion vive dentro del panel, asi que la puerta es la del panel:
        // sin sesion no se llega siquiera a la pantalla que la ofrece.
        $this->get('/panel/leads')->assertRedirect(route('login'));
    }

    private function administrador(): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@unmsm.edu.pe',
            'password' => bcrypt('secreta-de-prueba'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
