<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApiReportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ApiReportController
{
    public function __construct(
        private readonly ApiReportService $apiReportService,
    ) {
    }

    public function summarize(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $dateFrom = (string) ($query['date_from'] ?? '');
        $dateTo = (string) ($query['date_to'] ?? '');

        if (!$this->isValidDate($dateFrom) || !$this->isValidDate($dateTo)) {
            return $this->json($response->withStatus(422), [
                'error' => 'date_from と date_to は YYYY-MM-DD 形式で指定してください。',
            ]);
        }

        if ($dateFrom > $dateTo) {
            return $this->json($response->withStatus(422), [
                'error' => 'date_from は date_to 以下で指定してください。',
            ]);
        }

        $data = $this->apiReportService->summarizeHoursByClient($dateFrom, $dateTo);
        $totalHours = 0.0;
        foreach ($data as $row) {
            $totalHours += (float) $row['total_hours'];
        }

        return $this->json($response, [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => array_map(static fn (array $row): array => [
                'client_id' => (int) $row['client_id'],
                'client_name' => (string) $row['client_name'],
                'hours' => (float) $row['total_hours'],
            ], $data),
            'total_hours' => round($totalHours, 2),
        ]);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function json(Response $response, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
