<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ClientService;
use App\Service\TimeEntryService;
use App\Service\WorkCategoryService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TimeEntriesController
{
    public function __construct(
        private readonly TimeEntryService $timeEntryService,
        private readonly ClientService $clientService,
        private readonly WorkCategoryService $workCategoryService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderHome($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $old = $this->timeEntryService->normalizeInput((array) $request->getParsedBody());
        $errors = $this->timeEntryService->validate($old);

        $hours = null;
        if ($errors === []) {
            try {
                $hours = $this->timeEntryService->calculateHours($old['start_time'], $old['end_time']);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return $this->renderHome($request, $response, $errors, $old, 422);
        }

        $this->timeEntryService->create($old, $hours);

        return $response->withHeader('Location', '/?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $entry = $this->timeEntryService->findById($id);
        if ($entry === null) {
            $response->getBody()->write('time entry not found');

            return $response->withStatus(404);
        }

        return Twig::fromRequest($request)->render($response, 'time_entry_edit.html.twig', [
            'title' => '稼働時間を編集',
            'entry' => [
                'id' => $entry['id'],
                'client_id' => (string) $entry['client_id'],
                'work_category_id' => (string) $entry['work_category_id'],
                'date' => $entry['date'],
                'start_time' => substr((string) $entry['start_time'], 0, 5),
                'end_time' => substr((string) $entry['end_time'], 0, 5),
                'comment' => (string) ($entry['comment'] ?? ''),
            ],
            'clients' => $this->clientService->listForSelect(),
            'workCategories' => $this->workCategoryService->listForSelect(),
            'timeOptions' => $this->timeEntryService->buildTimeOptions(),
            'errors' => [],
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        if (!$this->timeEntryService->has($id)) {
            $response->getBody()->write('time entry not found');

            return $response->withStatus(404);
        }

        $entry = $this->timeEntryService->normalizeInput((array) $request->getParsedBody());
        $entry['id'] = $id;
        $errors = $this->timeEntryService->validate($entry);

        $hours = null;
        if ($errors === []) {
            try {
                $hours = $this->timeEntryService->calculateHours($entry['start_time'], $entry['end_time']);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return Twig::fromRequest($request)->render($response->withStatus(422), 'time_entry_edit.html.twig', [
                'title' => '稼働時間を編集',
                'entry' => $entry,
                'clients' => $this->clientService->listForSelect(),
                'workCategories' => $this->workCategoryService->listForSelect(),
                'timeOptions' => $this->timeEntryService->buildTimeOptions(),
                'errors' => $errors,
            ]);
        }

        $this->timeEntryService->update($id, $entry, $hours);

        return $response->withHeader('Location', '/?updated=1')->withStatus(302);
    }

    public function delete(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $this->timeEntryService->delete($id);

        return $response->withHeader('Location', '/?deleted=1')->withStatus(302);
    }

    private function renderHome(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $query = $request->getQueryParams();
        $month = (string) ($query['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $clientFilterRaw = trim((string) ($query['client_id'] ?? ''));
        $clientFilter = ctype_digit($clientFilterRaw) && $clientFilterRaw !== ''
            ? (int) $clientFilterRaw
            : null;

        $timeEntries = $this->timeEntryService->listForPeriod($periodStart, $periodEnd, $clientFilter);
        $dailySummaries = $this->timeEntryService->summarizeDailyForPeriod($periodStart, $periodEnd, $clientFilter);
        $dailyTotalsByDate = [];
        $monthlyTotal = 0.0;
        foreach ($dailySummaries as $summary) {
            $monthlyTotal += (float) $summary['total_hours'];
            $dailyTotalsByDate[(string) $summary['date']] = (float) $summary['total_hours'];
        }

        return Twig::fromRequest($request)->render($response->withStatus($status), 'home.html.twig', [
            'title' => 'Kizami',
            'clients' => $this->clientService->listForSelect(),
            'workCategories' => $this->workCategoryService->listForSelect(),
            'timeEntries' => $timeEntries,
            'dailySummaries' => $dailySummaries,
            'dailyTotalsByDate' => $dailyTotalsByDate,
            'monthlyTotal' => round($monthlyTotal, 2),
            'timeOptions' => $this->timeEntryService->buildTimeOptions(),
            'errors' => $errors,
            'old' => $old ?? [
                'date' => date('Y-m-d'),
                'client_id' => '',
                'work_category_id' => '',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'comment' => '',
            ],
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
            'filterMonth' => $month,
            'filterClientId' => $clientFilterRaw,
        ]);
    }
}
