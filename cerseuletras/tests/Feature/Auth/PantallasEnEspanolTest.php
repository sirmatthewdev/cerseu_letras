<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las pantallas de sesión, en español.
 *
 * /login llevaba meses a medias: las etiquetas de los dos campos estaban
 * escritas a mano en español y todo lo demás —«Remember me», «Forgot your
 * password?», «LOG IN»— salía en inglés, porque venía de `__()` y no había
 * ningún archivo de idioma que lo tradujera. Los mensajes de error estaban
 * peor: con `APP_LOCALE=es` y sin `lang/es/`, un formulario enviado en blanco
 * respondía «validation.required», la clave en crudo.
 *
 * Se comprueba en las dos direcciones: que el español aparece y que el inglés
 * ya no. Sin la segunda mitad, un `lang/es.json` al que le falte una clave pasa
 * la prueba igual —la cadena en inglés sigue ahí, junto a las traducidas— y es
 * exactamente el estado del que se viene.
 */
class PantallasEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_de_inicio_de_sesion_esta_en_espanol(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Correo electrónico');
        $response->assertSee('Contraseña');
        $response->assertSee('Recordarme');
        $response->assertSee('¿Olvidó su contraseña?');
        $response->assertSee('Iniciar sesión');

        $response->assertDontSee('Remember me');
        $response->assertDontSee('Forgot your password?');
        $response->assertDontSee('Log in');
    }

    public function test_el_correo_o_la_contrasena_equivocados_lo_dicen_en_espanol(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'la-que-no-es',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ]);
    }

    /**
     * El caso que delataba la falta de `lang/es/validation.php`: sin él, el
     * mensaje era la clave, no una frase.
     */
    public function test_un_campo_vacio_se_reclama_por_su_nombre_en_espanol(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
    }

    public function test_la_pantalla_de_recuperar_contrasena_esta_en_espanol(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertSee('Enviar el enlace por correo');
        $response->assertDontSee('Email Password Reset Link');
        $response->assertDontSee('No problem');
    }

    public function test_el_aviso_de_enlace_enviado_esta_en_espanol(): void
    {
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $this->assertSame(
            'Le hemos enviado por correo el enlace para restablecer la contraseña.',
            session('status'),
        );
    }

    public function test_la_pagina_de_perfil_esta_en_espanol(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertSee('Datos del perfil');
        $response->assertSee('Cambiar la contraseña');
        $response->assertSee('Eliminar cuenta');

        $response->assertDontSee('Profile Information');
        $response->assertDontSee('Update Password');
        $response->assertDontSee('Delete Account');
        $response->assertDontSee('Log Out');
    }
}
