<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia de seguridad de la base de datos.
 *
 * Pensada para ejecutarla **antes de migrar**: varias migraciones de esta
 * versión eliminan columnas con contenido, y aunque hay migraciones que lo
 * rescatan (`preservar_datos_antes_de_migrar`), un respaldo previo es lo único
 * que deja marcha atrás completa.
 *
 * Funciona con SQLite (copia del archivo) y con MySQL/MariaDB (`mysqldump`).
 */
class RespaldarBase extends Command
{
    protected $signature = 'db:respaldar {--destino= : Carpeta donde dejar el archivo}';

    protected $description = 'Guarda una copia de la base de datos antes de migrar';

    public function handle(): int
    {
        $carpeta = $this->option('destino') ?: storage_path('backups');

        if (! is_dir($carpeta) && ! mkdir($carpeta, 0755, true)) {
            $this->error("No se pudo crear la carpeta {$carpeta}.");

            return self::FAILURE;
        }

        $conexion = config('database.default');
        $marca = now()->format('Y-m-d_His');

        return match ($conexion) {
            'sqlite' => $this->respaldarSqlite($carpeta, $marca),
            'mysql', 'mariadb' => $this->respaldarMysql($carpeta, $marca),
            default => $this->noSoportado($conexion),
        };
    }

    private function respaldarSqlite(string $carpeta, string $marca): int
    {
        $origen = config('database.connections.sqlite.database');

        if (! is_file($origen)) {
            $this->error("No se encuentra la base en {$origen}.");

            return self::FAILURE;
        }

        $destino = "{$carpeta}/respaldo_{$marca}.sqlite";

        // Se fuerza el volcado del WAL antes de copiar: si no, los últimos
        // cambios pueden quedarse fuera del archivo principal.
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');

        if (! copy($origen, $destino)) {
            $this->error('No se pudo copiar el archivo de la base.');

            return self::FAILURE;
        }

        return $this->informar($destino);
    }

    private function respaldarMysql(string $carpeta, string $marca): int
    {
        $c = config('database.connections.' . config('database.default'));
        $destino = "{$carpeta}/respaldo_{$marca}.sql";

        // La contraseña va por variable de entorno, no en la línea de comandos:
        // ahí la vería cualquiera que liste los procesos del servidor.
        $orden = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --routines --events %s > %s',
            escapeshellarg($c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg($c['username']),
            escapeshellarg($c['database']),
            escapeshellarg($destino)
        );

        $entorno = ['MYSQL_PWD' => $c['password']] + $_ENV;
        $proceso = proc_open($orden, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, null, $entorno);

        if (! is_resource($proceso)) {
            $this->error('No se pudo lanzar mysqldump. ¿Está instalado y en el PATH?');

            return self::FAILURE;
        }

        $error = stream_get_contents($tubos[2]);
        array_map('fclose', $tubos);

        if (proc_close($proceso) !== 0) {
            // La redirección del shell crea el fichero antes de que mysqldump
            // arranque: si falla, queda uno vacío que parece un respaldo.
            @unlink($destino);
            $this->error('mysqldump falló: ' . trim($error));
            $this->line('En Docker, la imagen de PHP no trae mysqldump: usa scripts/respaldo_bd.sh.');

            return self::FAILURE;
        }

        return $this->informar($destino);
    }

    private function noSoportado(string $conexion): int
    {
        $this->error("Respaldo no implementado para «{$conexion}».");
        $this->line('Haz la copia con la herramienta propia del motor antes de migrar.');

        return self::FAILURE;
    }

    private function informar(string $destino): int
    {
        $this->info('Respaldo guardado.');
        $this->line('  ' . $destino);
        $this->line('  ' . number_format(filesize($destino) / 1048576, 2) . ' MB');
        $this->newLine();
        $this->line('Guárdalo fuera del servidor antes de ejecutar `php artisan migrate`.');

        return self::SUCCESS;
    }
}
