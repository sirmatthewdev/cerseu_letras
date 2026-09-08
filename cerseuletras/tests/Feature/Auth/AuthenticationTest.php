<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * El destino depende del rol: el panel no es para todo el mundo.
     *
     * Esta prueba afirmaba un unico `route('dashboard')`, que en este proyecto
     * no existe —el indice del panel se llama `admin.dashboard`—, asi que
     * fallaba con RouteNotFoundException desde antes del rebrand.
     */
    public function test_un_admin_entra_al_panel_al_iniciar_sesion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/panel');
    }

    public function test_un_usuario_sin_rol_admin_vuelve_a_la_portada(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // Al panel: el sitio publico es estatico y no tiene sesion, asi que
        // quien se autentica lo hace para administrar.
        $response->assertRedirect('/panel');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
