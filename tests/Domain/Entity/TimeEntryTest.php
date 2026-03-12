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
    public function shouldCalculateHoursOnQuarterTimes(): void
    {
        $entry = new TimeEntry();
        $entry->setTimeRange(new DateTimeImmutable('09:00'), new DateTimeImmutable('10:30'));

        self::assertSame('1.50', $entry->getHours());
    }

    #[Test]
    public function shouldCalculateOvernightHours(): void
    {
        $entry = new TimeEntry();
        $entry->setTimeRange(new DateTimeImmutable('22:00'), new DateTimeImmutable('02:00'));

        self::assertSame('4.00', $entry->getHours());
    }

    #[Test]
    public function shouldAllowShiftingTimeForward(): void
    {
        $entry = new TimeEntry();
        $entry->setTimeRange(new DateTimeImmutable('14:00'), new DateTimeImmutable('14:30'));

        // 後ろ倒し: 変更前の終了時間 == 変更後の開始時間になるケース
        $entry->setTimeRange(new DateTimeImmutable('14:30'), new DateTimeImmutable('15:00'));

        self::assertSame('0.50', $entry->getHours());
    }

    #[Test]
    public function shouldAllowShiftingTimeBackward(): void
    {
        $entry = new TimeEntry();
        $entry->setTimeRange(new DateTimeImmutable('14:30'), new DateTimeImmutable('15:00'));

        // 前倒し: 変更前の開始時間 == 変更後の終了時間になるケース
        $entry->setTimeRange(new DateTimeImmutable('14:00'), new DateTimeImmutable('14:30'));

        self::assertSame('0.50', $entry->getHours());
    }

    #[Test]
    public function shouldRejectStartEqualsEnd(): void
    {
        $entry = new TimeEntry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('開始時刻と終了時刻は同一にできません。');

        $entry->setTimeRange(new DateTimeImmutable('09:00'), new DateTimeImmutable('09:00'));
    }

    #[Test]
    public function shouldRejectNonQuarterStartTime(): void
    {
        $entry = new TimeEntry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('開始時刻は15分刻みで指定してください。');

        $entry->setTimeRange(new DateTimeImmutable('09:10'), new DateTimeImmutable('10:00'));
    }

    #[Test]
    public function shouldRejectNonQuarterEndTime(): void
    {
        $entry = new TimeEntry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('終了時刻は15分刻みで指定してください。');

        $entry->setTimeRange(new DateTimeImmutable('09:00'), new DateTimeImmutable('10:10'));
    }
}
