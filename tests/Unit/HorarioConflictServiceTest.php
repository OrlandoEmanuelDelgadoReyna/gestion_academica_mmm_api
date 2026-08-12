<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HorarioConflictService;
use PHPUnit\Framework\TestCase;

final class HorarioConflictServiceTest extends TestCase
{
    private HorarioConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HorarioConflictService;
    }

    public function test_detects_time_overlap(): void
    {
        $this->assertTrue($this->service->timesOverlap('19:00', '21:00', '20:00', '22:00'));
    }

    public function test_allows_consecutive_times_without_overlap(): void
    {
        $this->assertFalse($this->service->timesOverlap('19:00', '21:00', '21:00', '23:00'));
    }

    public function test_same_schedule_different_days_do_not_conflict(): void
    {
        $this->assertFalse($this->service->schedulesOverlap(
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ));
    }

    public function test_same_day_overlapping_schedules_conflict(): void
    {
        $this->assertTrue($this->service->schedulesOverlap(
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ));
    }

    public function test_detects_internal_overlap(): void
    {
        $this->assertTrue($this->service->hasInternalOverlap([
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ]));
    }
}
