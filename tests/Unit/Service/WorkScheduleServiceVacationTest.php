<?php

declare(strict_types=1);

namespace OCA\WorkTime\Tests\Unit\Service;

use DateTime;
use OCA\WorkTime\Db\Employee;
use OCA\WorkTime\Db\EmployeeMapper;
use OCA\WorkTime\Db\WorkSchedule;
use OCA\WorkTime\Db\WorkScheduleMapper;
use OCA\WorkTime\Service\AuditLogService;
use OCA\WorkTime\Service\CompanySettingsService;
use OCA\WorkTime\Service\WorkScheduleService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the pro-rated vacation entitlement calculation
 * (part-time factor + twelfthing for partial-year employment).
 */
class WorkScheduleServiceVacationTest extends TestCase {

    private WorkScheduleMapper $mapper;
    private EmployeeMapper $employeeMapper;
    private WorkScheduleService $service;

    protected function setUp(): void {
        $this->mapper = $this->createMock(WorkScheduleMapper::class);
        $this->employeeMapper = $this->createMock(EmployeeMapper::class);

        $this->service = new WorkScheduleService(
            $this->mapper,
            $this->employeeMapper,
            $this->createMock(CompanySettingsService::class),
            $this->createMock(AuditLogService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(IL10N::class),
        );
    }

    private function makeSchedule(int $workingDaysPerWeek, int $vacationDays): WorkSchedule {
        $days = ['8.00', '8.00', '8.00', '8.00', '8.00', '8.00', '8.00'];
        for ($i = $workingDaysPerWeek; $i < 7; $i++) {
            $days[$i] = '0.00';
        }
        $schedule = new WorkSchedule();
        $schedule->setMonHours($days[0]);
        $schedule->setTueHours($days[1]);
        $schedule->setWedHours($days[2]);
        $schedule->setThuHours($days[3]);
        $schedule->setFriHours($days[4]);
        $schedule->setSatHours($days[5]);
        $schedule->setSunHours($days[6]);
        $schedule->setVacationDays($vacationDays);
        return $schedule;
    }

    private function configure(WorkSchedule $schedule, ?string $entry, ?string $exit): void {
        $this->mapper->method('findForDate')->willReturn($schedule);

        $employee = new Employee();
        $employee->setEntryDate($entry !== null ? new DateTime($entry) : null);
        $employee->setExitDate($exit !== null ? new DateTime($exit) : null);
        $this->employeeMapper->method('find')->willReturn($employee);
    }

    public function testFullTimeFullYearReturnsFullEntitlement(): void {
        $this->configure($this->makeSchedule(5, 30), null, null);
        $this->assertSame(30.0, $this->service->getVacationDaysForYear(1, 2026));
    }

    public function testPartTimeThreeFullMonthsYieldsOneDay(): void {
        // 1 working day/week, 20 days baseline, employed 3 full calendar months.
        // 20 * (1/5) * (3/12) = 1.0
        $this->configure($this->makeSchedule(1, 20), '2026-01-01', '2026-03-31');
        $this->assertSame(1.0, $this->service->getVacationDaysForYear(1, 2026));
    }

    public function testEntryMidMonthSkipsThatMonth(): void {
        // Full-time, entry on Jan 15 -> January is not a full month; Feb-Dec = 11 months.
        // 24 * 1 * (11/12) = 22.0
        $this->configure($this->makeSchedule(5, 24), '2026-01-15', null);
        $this->assertSame(22.0, $this->service->getVacationDaysForYear(1, 2026));
    }

    public function testExitMidYearProratesToFullMonths(): void {
        // Full-time, exit Jun 30 -> Jan-Jun = 6 full months. 30 * (6/12) = 15.0
        $this->configure($this->makeSchedule(5, 30), null, '2026-06-30');
        $this->assertSame(15.0, $this->service->getVacationDaysForYear(1, 2026));
    }

    public function testRoundsToNearestHalfDay(): void {
        // 1 day/week, 20 days, employed only January (1 full month).
        // 20 * (1/5) * (1/12) = 0.333... -> rounded to 0.5
        $this->configure($this->makeSchedule(1, 20), '2026-01-01', '2026-01-31');
        $this->assertSame(0.5, $this->service->getVacationDaysForYear(1, 2026));
    }
}
