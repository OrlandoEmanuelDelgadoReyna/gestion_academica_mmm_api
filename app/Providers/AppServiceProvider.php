<?php

namespace App\Providers;

use App\Repositories\Contracts\AnuncioRepositoryInterface;
use App\Repositories\Contracts\AsistenciaRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\BloqueCultoRepositoryInterface;
use App\Repositories\Contracts\CalificacionRepositoryInterface;
use App\Repositories\Contracts\CertificadoRepositoryInterface;
use App\Repositories\Contracts\CriterioEvaluacionRepositoryInterface;
use App\Repositories\Contracts\CursoRepositoryInterface;
use App\Repositories\Contracts\CultoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;
use App\Repositories\Contracts\EventoRepositoryInterface;
use App\Repositories\Contracts\ExamenFinalRepositoryInterface;
use App\Repositories\Contracts\IglesiaRepositoryInterface;
use App\Repositories\Contracts\IntentoExamenRepositoryInterface;
use App\Repositories\Contracts\MatriculaRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\MiembroRepositoryInterface;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use App\Repositories\Contracts\ParticipacionCultoRepositoryInterface;
use App\Repositories\Contracts\PermisoRepositoryInterface;
use App\Repositories\Contracts\ProgramacionAcademicaRepositoryInterface;
use App\Repositories\Contracts\ReporteRepositoryInterface;
use App\Repositories\Contracts\RolRepositoryInterface;
use App\Repositories\Contracts\SesionRepositoryInterface;
use App\Repositories\Contracts\TareaRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Repositories\Eloquent\EloquentAnuncioRepository;
use App\Repositories\Eloquent\EloquentAsistenciaRepository;
use App\Repositories\Eloquent\EloquentAuditoriaRepository;
use App\Repositories\Eloquent\EloquentBloqueCultoRepository;
use App\Repositories\Eloquent\EloquentCalificacionRepository;
use App\Repositories\Eloquent\EloquentCertificadoRepository;
use App\Repositories\Eloquent\EloquentCriterioEvaluacionRepository;
use App\Repositories\Eloquent\EloquentCursoRepository;
use App\Repositories\Eloquent\EloquentCultoRepository;
use App\Repositories\Eloquent\EloquentDatabaseTransactionRepository;
use App\Repositories\Eloquent\EloquentEntregaTareaRepository;
use App\Repositories\Eloquent\EloquentEventoRepository;
use App\Repositories\Eloquent\EloquentExamenFinalRepository;
use App\Repositories\Eloquent\EloquentIglesiaRepository;
use App\Repositories\Eloquent\EloquentIntentoExamenRepository;
use App\Repositories\Eloquent\EloquentMatriculaRepository;
use App\Repositories\Eloquent\EloquentMaterialRepository;
use App\Repositories\Eloquent\EloquentMiembroRepository;
use App\Repositories\Eloquent\EloquentNotificacionRepository;
use App\Repositories\Eloquent\EloquentParticipacionCultoRepository;
use App\Repositories\Eloquent\EloquentPermisoRepository;
use App\Repositories\Eloquent\EloquentProgramacionAcademicaRepository;
use App\Repositories\Eloquent\EloquentReporteRepository;
use App\Repositories\Eloquent\EloquentRolRepository;
use App\Repositories\Eloquent\EloquentSesionRepository;
use App\Repositories\Eloquent\EloquentTareaRepository;
use App\Repositories\Eloquent\EloquentUsuarioRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registers application service bindings.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseTransactionRepositoryInterface::class, EloquentDatabaseTransactionRepository::class);
        $this->app->bind(UsuarioRepositoryInterface::class, EloquentUsuarioRepository::class);
        $this->app->bind(AuditoriaRepositoryInterface::class, EloquentAuditoriaRepository::class);
        $this->app->bind(IglesiaRepositoryInterface::class, EloquentIglesiaRepository::class);
        $this->app->bind(MiembroRepositoryInterface::class, EloquentMiembroRepository::class);
        $this->app->bind(RolRepositoryInterface::class, EloquentRolRepository::class);
        $this->app->bind(PermisoRepositoryInterface::class, EloquentPermisoRepository::class);
        $this->app->bind(CursoRepositoryInterface::class, EloquentCursoRepository::class);
        $this->app->bind(ProgramacionAcademicaRepositoryInterface::class, EloquentProgramacionAcademicaRepository::class);
        $this->app->bind(MatriculaRepositoryInterface::class, EloquentMatriculaRepository::class);
        $this->app->bind(SesionRepositoryInterface::class, EloquentSesionRepository::class);
        $this->app->bind(AsistenciaRepositoryInterface::class, EloquentAsistenciaRepository::class);
        $this->app->bind(MaterialRepositoryInterface::class, EloquentMaterialRepository::class);
        $this->app->bind(TareaRepositoryInterface::class, EloquentTareaRepository::class);
        $this->app->bind(EntregaTareaRepositoryInterface::class, EloquentEntregaTareaRepository::class);
        $this->app->bind(ExamenFinalRepositoryInterface::class, EloquentExamenFinalRepository::class);
        $this->app->bind(CriterioEvaluacionRepositoryInterface::class, EloquentCriterioEvaluacionRepository::class);
        $this->app->bind(IntentoExamenRepositoryInterface::class, EloquentIntentoExamenRepository::class);
        $this->app->bind(CalificacionRepositoryInterface::class, EloquentCalificacionRepository::class);
        $this->app->bind(CertificadoRepositoryInterface::class, EloquentCertificadoRepository::class);
        $this->app->bind(CultoRepositoryInterface::class, EloquentCultoRepository::class);
        $this->app->bind(BloqueCultoRepositoryInterface::class, EloquentBloqueCultoRepository::class);
        $this->app->bind(ParticipacionCultoRepositoryInterface::class, EloquentParticipacionCultoRepository::class);
        $this->app->bind(EventoRepositoryInterface::class, EloquentEventoRepository::class);
        $this->app->bind(AnuncioRepositoryInterface::class, EloquentAnuncioRepository::class);
        $this->app->bind(NotificacionRepositoryInterface::class, EloquentNotificacionRepository::class);
        $this->app->bind(ReporteRepositoryInterface::class, EloquentReporteRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
