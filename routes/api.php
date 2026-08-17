<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AnuncioController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AutenticacionController;
use App\Http\Controllers\Api\V1\BloqueCultoController;
use App\Http\Controllers\Api\V1\CalificacionController;
use App\Http\Controllers\Api\V1\CertificadoController;
use App\Http\Controllers\Api\V1\CriterioEvaluacionController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\CultoController;
use App\Http\Controllers\Api\V1\EntregaTareaController;
use App\Http\Controllers\Api\V1\EventoController;
use App\Http\Controllers\Api\V1\ExamenFinalController;
use App\Http\Controllers\Api\V1\IglesiaController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\MatriculaController;
use App\Http\Controllers\Api\V1\IntentoExamenController;
use App\Http\Controllers\Api\V1\MiembroController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\ParticipacionCultoController;
use App\Http\Controllers\Api\V1\PermisoController;
use App\Http\Controllers\Api\V1\ProgramacionAcademicaController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\SesionController;
use App\Http\Controllers\Api\V1\TareaController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('certificados/verificar/{codigo}', [CertificadoController::class, 'verificar']);

    Route::post('login', [AutenticacionController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'usuario.activo'])->group(function (): void {
        Route::post('logout', [AutenticacionController::class, 'logout']);
        Route::get('me', [AutenticacionController::class, 'me']);
        Route::put('contrasena', [AutenticacionController::class, 'changePassword']);
        Route::apiResource('usuarios', UsuarioController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('iglesias', IglesiaController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('miembros', MiembroController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('permisos', PermisoController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('roles', [RolController::class, 'index']);
        Route::post('roles', [RolController::class, 'store']);
        Route::get('roles/{rol}', [RolController::class, 'show']);
        Route::put('roles/{rol}', [RolController::class, 'update']);
        Route::get('roles/{rol}/permisos', [RolController::class, 'permissions']);
        Route::put('roles/{rol}/permisos', [RolController::class, 'syncPermissions']);
        Route::delete('roles/{rol}/permisos', [RolController::class, 'revokePermissions']);
        Route::post('miembros/{miembro}/transiciones', [MiembroController::class, 'transition']);

        Route::apiResource('cursos', CursoController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('programaciones-academicas', ProgramacionAcademicaController::class)
            ->parameters(['programaciones-academicas' => 'programacionAcademica'])
            ->only(['index', 'store', 'show', 'update']);
        Route::post('programaciones-academicas/{programacionAcademica}/transiciones', [ProgramacionAcademicaController::class, 'transition']);
        Route::post('programaciones-academicas/{programacionAcademica}/sesiones/generar', [ProgramacionAcademicaController::class, 'generarSesiones']);
        Route::apiResource('matriculas', MatriculaController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('matriculas/{matricula}/transiciones', [MatriculaController::class, 'transition']);
        Route::apiResource('sesiones', SesionController::class)
            ->parameters(['sesiones' => 'sesion'])
            ->only(['index', 'store', 'show', 'update']);
        Route::apiResource('asistencias', AsistenciaController::class)
            ->parameters(['asistencias' => 'asistencia'])
            ->only(['index', 'store', 'show', 'update']);
        Route::apiResource('materiales', MaterialController::class)->only(['index', 'store', 'show', 'update']);

        Route::apiResource('tareas', TareaController::class);
        Route::apiResource('entregas-tarea', EntregaTareaController::class)->only(['store', 'show', 'update']);
        Route::apiResource('examenes-finales', ExamenFinalController::class);
        Route::apiResource('criterios-evaluacion', CriterioEvaluacionController::class);
        Route::post('intentos-examen/iniciar', [IntentoExamenController::class, 'iniciar']);
        Route::post('intentos-examen/{intento_examen}/enviar', [IntentoExamenController::class, 'enviar']);
        Route::get('intentos-examen/{intento_examen}', [IntentoExamenController::class, 'show']);
        Route::post('matriculas/{matricula}/calificaciones/calcular', [CalificacionController::class, 'calcular']);

        Route::get('certificados', [CertificadoController::class, 'index']);
        Route::get('certificados/{certificado}', [CertificadoController::class, 'show']);
        Route::post('certificados/emitir', [CertificadoController::class, 'emitir']);
        Route::post('certificados/{certificado}/revocar', [CertificadoController::class, 'revocar']);
        Route::post('certificados/{certificado}/reemplazar', [CertificadoController::class, 'reemplazar']);

        Route::apiResource('cultos', CultoController::class);
        Route::apiResource('bloques-culto', BloqueCultoController::class)->parameters(['bloques-culto' => 'bloque_culto']);
        Route::apiResource('participaciones-culto', ParticipacionCultoController::class)->parameters(['participaciones-culto' => 'participacion_culto']);
        Route::apiResource('eventos', EventoController::class);
        Route::apiResource('anuncios', AnuncioController::class);
        Route::apiResource('notificaciones', NotificacionController::class);
        Route::post('notificaciones/{notificacion}/enviar', [NotificacionController::class, 'enviar']);
        Route::post('notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarLeida']);
        Route::get('reportes/academicos', [ReporteController::class, 'academicos']);
        Route::get('reportes/administrativos', [ReporteController::class, 'administrativos']);
        Route::get('reportes/certificados', [ReporteController::class, 'certificados']);
    });
});

Route::prefix('v2')->group(function (): void {
    // Reserved for non-breaking future API evolution.
});
