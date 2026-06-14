<?php

declare(strict_types=1);

namespace OCA\WorkTime\Tests\Unit\Service;

use DateTime;
use OCA\WorkTime\Db\OvertimePayout;
use OCA\WorkTime\Db\OvertimePayoutMapper;
use OCA\WorkTime\Service\AuditLogService;
use OCA\WorkTime\Service\OvertimePayoutService;
use PHPUnit\Framework\TestCase;

class OvertimePayoutServiceTest extends TestCase {

    private OvertimePayoutMapper $mapper;
    private AuditLogService $auditLogService;
    private OvertimePayoutService $service;

    protected function setUp(): void {
        $this->mapper = $this->createMock(OvertimePayoutMapper::class);
        $this->auditLogService = $this->createMock(AuditLogService::class);
        $this->service = new OvertimePayoutService($this->mapper, $this->auditLogService);
    }

    public function testCreatePersistsValuesAndAudits(): void {
        $this->mapper->method('insert')->willReturnArgument(0);
        $this->auditLogService->expects($this->once())->method('logCreate');

        $payout = $this->service->create(7, 2026, 5, 1200, 'Auszahlung Mai', 'admin');

        $this->assertSame(7, $payout->getEmployeeId());
        $this->assertSame(2026, $payout->getYear());
        $this->assertSame(5, $payout->getMonth());
        $this->assertSame(1200, $payout->getMinutes());
        $this->assertSame('Auszahlung Mai', $payout->getNote());
        $this->assertSame('admin', $payout->getCreatedBy());
        $this->assertFalse($payout->isCancelled());
    }

    public function testCancelMarksCancelledAndAudits(): void {
        $existing = new OvertimePayout();
        $existing->setId(42);
        $existing->setEmployeeId(7);
        $existing->setMinutes(1200);

        $this->mapper->method('find')->willReturn($existing);
        $this->mapper->method('update')->willReturnArgument(0);
        $this->auditLogService->expects($this->once())->method('logUpdate');

        $result = $this->service->cancel(42, 'admin');

        $this->assertTrue($result->isCancelled());
        $this->assertSame('admin', $result->getCancelledBy());
        $this->assertInstanceOf(DateTime::class, $result->getCancelledAt());
    }

    public function testCancelIsIdempotentForAlreadyCancelled(): void {
        $existing = new OvertimePayout();
        $existing->setId(42);
        $existing->setCancelledAt(new DateTime());
        $existing->setCancelledBy('someone');

        $this->mapper->method('find')->willReturn($existing);
        $this->mapper->expects($this->never())->method('update');
        $this->auditLogService->expects($this->never())->method('logUpdate');

        $result = $this->service->cancel(42, 'admin');

        $this->assertSame('someone', $result->getCancelledBy());
    }

    public function testSumPayoutMinutesDelegatesToMapper(): void {
        $this->mapper->expects($this->once())
            ->method('sumMinutes')
            ->with(7, 2026, 6)
            ->willReturn(2400);

        $this->assertSame(2400, $this->service->sumPayoutMinutes(7, 2026, 6));
    }
}
