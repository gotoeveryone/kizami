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
    public function calculateHoursShouldThrowForInvalidQuarterTimes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('開始時刻は15分刻みで指定してください。');

        $this->service->calculateHours('09:10', '10:00');
    }

    #[Test]
    public function validateShouldReturnErrorsForRequiredFields(): void
    {
        $errors = $this->service->validate([
            'date' => '',
            'client_id' => '',
            'work_category_id' => '',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'comment' => '',
        ]);

        self::assertContains('日付は必須です。', $errors);
        self::assertContains('クライアントは必須です。', $errors);
        self::assertContains('作業分類は必須です。', $errors);
    }

    #[Test]
    public function normalizeInputShouldTrimValues(): void
    {
        $normalized = $this->service->normalizeInput([
            'date' => ' 2026-02-12 ',
            'client_id' => ' 1 ',
            'work_category_id' => ' 2 ',
            'start_time' => ' 09:00 ',
            'end_time' => ' 10:00 ',
            'comment' => ' note ',
        ]);

        self::assertSame('2026-02-12', $normalized['date']);
        self::assertSame('1', $normalized['client_id']);
        self::assertSame('2', $normalized['work_category_id']);
        self::assertSame('09:00', $normalized['start_time']);
        self::assertSame('10:00', $normalized['end_time']);
        self::assertSame('note', $normalized['comment']);
    }
}
