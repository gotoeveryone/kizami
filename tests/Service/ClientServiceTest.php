<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ClientService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientServiceTest extends TestCase
{
    private ClientService $service;

    protected function setUp(): void
    {
        $this->service = new ClientService($this->createMock(EntityManagerInterface::class));
    }

    #[Test]
    public function normalizeInputShouldTrimValues(): void
    {
        $normalized = $this->service->normalizeInput([
            'name' => '  Acme  ',
            'sort_order' => ' 10 ',
        ]);

        self::assertSame('Acme', $normalized['name']);
        self::assertSame('10', $normalized['sort_order']);
    }

    #[Test]
    public function validateShouldReturnErrorsForInvalidInput(): void
    {
        $errors = $this->service->validate([
            'name' => '',
            'sort_order' => 'abc',
        ]);

        self::assertContains('クライアント名は必須です。', $errors);
        self::assertContains('表示順は整数で入力してください。', $errors);
    }

    #[Test]
    public function validateShouldReturnNoErrorForValidInput(): void
    {
        $errors = $this->service->validate([
            'name' => 'Acme',
            'sort_order' => '0',
        ]);

        self::assertSame([], $errors);
    }
}
