<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TimeEntryService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeEntryServiceTest extends TestCase
{
    private TimeEntryService $service;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new TimeEntryService($entityManager);
    }

    #[Test]
    public function calculateHoursShouldReturnNormalDuration(): void
    {
        $hours = $this->service->calculateHours('09:00', '10:30');

        self::assertSame(1.5, $hours);
    }

    #[Test]
    public function calculateHoursShouldSupportOvernight(): void
    {
        $hours = $this->service->calculateHours('22:00', '02:00');

        self::assertSame(4.0, $hours);
    }

    #[Test]
    public function calculateHoursShouldThrowWhenStartEqualsEnd(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateHours('09:00', '09:00');
    }

    #[Test]
    public function validateShouldReturnErrorsForInvalidQuarterTimes(): void
    {
        $errors = $this->service->validate([
            'date' => '2026-02-12',
            'client_id' => '1',
            'work_category_id' => '1',
            'start_time' => '09:10',
            'end_time' => '10:01',
            'comment' => '',
        ]);

        self::assertContains('開始時刻は15分刻みで指定してください。', $errors);
        self::assertContains('終了時刻は15分刻みで指定してください。', $errors);
    }
}
