<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'time_entries')]
#[ORM\HasLifecycleCallbacks]
class TimeEntry
{
    private const QUARTER_MINUTES = [0, 15, 30, 45];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: WorkCategory::class, inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(name: 'work_category_id', nullable: false)]
    private WorkCategory $workCategory;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $date;

    #[ORM\Column(name: 'start_time', type: 'time_immutable')]
    private DateTimeImmutable $startTime;

    #[ORM\Column(name: 'end_time', type: 'time_immutable')]
    private DateTimeImmutable $endTime;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $hours = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $created;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $modified;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->created = $now;
        $this->modified = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->modified = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function getWorkCategory(): WorkCategory
    {
        return $this->workCategory;
    }

    public function setWorkCategory(WorkCategory $workCategory): void
    {
        $this->workCategory = $workCategory;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(DateTimeImmutable $startTime): void
    {
        $this->assertQuarterTime($startTime, '開始時刻は15分刻みで指定してください。');
        $this->startTime = $startTime;
        $this->recalculateHoursIfPossible();
    }

    public function getEndTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(DateTimeImmutable $endTime): void
    {
        $this->assertQuarterTime($endTime, '終了時刻は15分刻みで指定してください。');
        $this->endTime = $endTime;
        $this->recalculateHoursIfPossible();
    }

    public function getHours(): string
    {
        return $this->hours;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    private function recalculateHoursIfPossible(): void
    {
        if (!isset($this->startTime, $this->endTime)) {
            return;
        }

        $this->hours = number_format($this->calculateHoursFromTimes(), 2, '.', '');
    }

    private function calculateHoursFromTimes(): float
    {
        $startMinutes = ((int) $this->startTime->format('H') * 60) + (int) $this->startTime->format('i');
        $endMinutes = ((int) $this->endTime->format('H') * 60) + (int) $this->endTime->format('i');

        if ($startMinutes === $endMinutes) {
            throw new InvalidArgumentException('開始時刻と終了時刻は同一にできません。');
        }

        if ($startMinutes > $endMinutes) {
            $endMinutes += 24 * 60;
        }

        $durationMinutes = $endMinutes - $startMinutes;
        if ($durationMinutes % 15 !== 0) {
            throw new InvalidArgumentException('工数は0.25時間刻みで入力してください。');
        }

        return $durationMinutes / 60;
    }

    private function assertQuarterTime(DateTimeImmutable $time, string $errorMessage): void
    {
        $minute = (int) $time->format('i');
        $second = (int) $time->format('s');
        if (!in_array($minute, self::QUARTER_MINUTES, true) || $second !== 0) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

}
