<?php

declare(strict_types=1);

namespace Tests\Domain\Entity;

use App\Domain\Entity\TimeEntry;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeEntryTest extends TestCase
{
    #[Test]
    public function shouldAutoCalculateHoursOnQuarterTimes(): void
    {
        $entry = new TimeEntry();
        $entry->setStartTime(new DateTimeImmutable('09:00'));
        $entry->setEndTime(new DateTimeImmutable('10:30'));

        self::assertSame('1.50', $entry->getHours());
    }

    #[Test]
    public function shouldAutoCalculateOvernightHours(): void
    {
        $entry = new TimeEntry();
        $entry->setStartTime(new DateTimeImmutable('22:00'));
        $entry->setEndTime(new DateTimeImmutable('02:00'));

        self::assertSame('4.00', $entry->getHours());
    }

    #[Test]
    public function shouldRejectStartEqualsEnd(): void
    {
        $entry = new TimeEntry();
        $entry->setStartTime(new DateTimeImmutable('09:00'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('開始時刻と終了時刻は同一にできません。');

        $entry->setEndTime(new DateTimeImmutable('09:00'));
    }

    #[Test]
    public function shouldRejectNonQuarterStartOrEndTime(): void
    {
        $entry = new TimeEntry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('開始時刻は15分刻みで指定してください。');

        $entry->setStartTime(new DateTimeImmutable('09:10'));
    }

    #[Test]
    public function shouldRejectNonQuarterHours(): void
    {
        $entry = new TimeEntry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('工数は0.25時間刻みで入力してください。');

        $entry->setHours(1.1);
    }

    #[Test]
    public function shouldRejectManualHoursWhenDifferentFromAutoCalculatedHours(): void
    {
        $entry = new TimeEntry();
        $entry->setStartTime(new DateTimeImmutable('09:00'));
        $entry->setEndTime(new DateTimeImmutable('10:00'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('工数は開始時刻・終了時刻から自動算出されます。');

        $entry->setHours(1.25);
    }
}
