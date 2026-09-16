<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AnuncioVigencia;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AnuncioVigenciaTest extends TestCase
{
    public function test_date_only_vence_at_is_end_of_lima_day_in_utc(): void
    {
        $parsed = AnuncioVigencia::parseVenceAt('2026-09-16');
        $expected = Carbon::parse('2026-09-16 23:59:59', 'America/Lima')->utc();

        $this->assertNotNull($parsed);
        $this->assertTrue($expected->equalTo($parsed));
    }

    public function test_naive_end_of_day_vence_at_is_lima_not_utc(): void
    {
        $parsed = AnuncioVigencia::parseVenceAt('2026-09-16 23:59:59');
        $expected = Carbon::parse('2026-09-16 23:59:59', 'America/Lima')->utc();

        $this->assertNotNull($parsed);
        $this->assertTrue($expected->equalTo($parsed));
        $this->assertSame('2026-09-17 04:59:59', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_naive_midnight_vence_at_is_end_of_lima_day(): void
    {
        $parsed = AnuncioVigencia::parseVenceAt('2026-09-16 00:00:00');
        $expected = Carbon::parse('2026-09-16 23:59:59', 'America/Lima')->utc();

        $this->assertNotNull($parsed);
        $this->assertTrue($expected->equalTo($parsed));
        $this->assertSame('2026-09-17 04:59:59', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_stored_utc_midnight_vence_at_is_that_lima_calendar_day(): void
    {
        $stored = Carbon::parse('2026-09-16 00:00:00', 'UTC');
        $effective = AnuncioVigencia::effectiveVenceAt($stored);
        $expected = Carbon::parse('2026-09-16 23:59:59', 'America/Lima')->utc();

        $this->assertNotNull($effective);
        $this->assertTrue($expected->equalTo($effective));
        $this->assertSame('2026-09-16', AnuncioVigencia::limaDateString($stored));
    }

    public function test_naive_publicado_at_is_lima_wall_clock(): void
    {
        $parsed = AnuncioVigencia::parsePublicadoAt('2026-09-16 11:20:00');
        $expected = Carbon::parse('2026-09-16 11:20:00', 'America/Lima')->utc();

        $this->assertNotNull($parsed);
        $this->assertTrue($expected->equalTo($parsed));
        $this->assertSame('2026-09-16 16:20:00', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_existing_utc_carbon_publicado_at_is_not_shifted_again(): void
    {
        $utc = Carbon::parse('2026-09-16 16:20:00', 'UTC');

        $parsed = AnuncioVigencia::parsePublicadoAt($utc);

        $this->assertNotNull($parsed);
        $this->assertTrue($utc->equalTo($parsed));
    }

    public function test_open_window_uses_inclusive_lima_calendar_day(): void
    {
        $publicado = Carbon::parse('2026-09-16 08:00:00', 'America/Lima')->utc();
        $vence = Carbon::parse('2026-09-16 00:00:00', 'UTC');
        $afternoon = Carbon::parse('2026-09-16 16:30:00', 'America/Lima');
        $nextMidnight = Carbon::parse('2026-09-17 00:00:00', 'America/Lima');

        $this->assertTrue(AnuncioVigencia::isOpen($publicado, $vence, $afternoon));
        $this->assertFalse(AnuncioVigencia::isOpen($publicado, $vence, $nextMidnight));
    }
}
