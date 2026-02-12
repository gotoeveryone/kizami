<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\WorkCategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkCategoryServiceTest extends TestCase
{
    private WorkCategoryService $service;

    protected function setUp(): void
    {
        $this->service = new WorkCategoryService($this->createMock(EntityManagerInterface::class));
    }

    #[Test]
    public function normalizeInputShouldTrimValues(): void
    {
        $normalized = $this->service->normalizeInput([
            'name' => '  設計作業  ',
            'sort_order' => ' 2 ',
        ]);

        self::assertSame('設計作業', $normalized['name']);
        self::assertSame('2', $normalized['sort_order']);
    }

    #[Test]
    public function validateShouldReturnErrorsForInvalidInput(): void
    {
        $errors = $this->service->validate([
            'name' => '',
            'sort_order' => 'x',
        ]);

        self::assertContains('作業分類名は必須です。', $errors);
        self::assertContains('表示順は整数で入力してください。', $errors);
    }

    #[Test]
    public function validateShouldReturnNoErrorForValidInput(): void
    {
        $errors = $this->service->validate([
            'name' => '開発作業',
            'sort_order' => '5',
        ]);

        self::assertSame([], $errors);
    }
}
