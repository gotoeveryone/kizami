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
        $body = (array) $request->getParsedBody();
        $continueRegistration = (string) ($body['continue_registration'] ?? '') === '1';
        $old = $this->timeEntryService->normalizeInput($body);
        $errors = $this->timeEntryService->validate($old);

        if ($errors !== []) {
            return $this->renderHome($request, $response, $errors, $old, 422);
        }

        try {
            $this->timeEntryService->create($old);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();

            return $this->renderHome($request, $response, $errors, $old, 422);
        }

        if ($continueRegistration) {
            $query = http_build_query([
                'saved' => '1',
                'continue_registration' => '1',
                'continue_date' => $old['date'],
                'continue_client_id' => $old['client_id'],
                'continue_work_category_id' => $old['work_category_id'],
                'continue_start_time' => $old['start_time'],
                'continue_end_time' => $old['end_time'],
            ]);

            return $response->withHeader('Location', '/time-entries?' . $query)->withStatus(302);
        }

        return $response->withHeader('Location', '/time-entries?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $entry = $this->timeEntryService->findById($id);
        if ($entry === null) {
            $response->getBody()->write('time entry not found');

            return $response->withStatus(404);
        }
        if (!$this->clientService->isVisible((int) $entry['client_id'])) {
            return $this->renderHome(
                $request,
                $response,
                ['非表示クライアントなので編集できません。'],
                null,
                422
            );
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

        try {
            $this->timeEntryService->update($id, $entry);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();

            return Twig::fromRequest($request)->render($response->withStatus(422), 'time_entry_edit.html.twig', [
                'title' => '稼働時間を編集',
                'entry' => $entry,
                'clients' => $this->clientService->listForSelect(),
                'workCategories' => $this->workCategoryService->listForSelect(),
                'timeOptions' => $this->timeEntryService->buildTimeOptions(),
                'errors' => $errors,
            ]);
        }

        return $response->withHeader('Location', '/time-entries?updated=1')->withStatus(302);
    }

    public function delete(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $this->timeEntryService->delete($id);

        return $response->withHeader('Location', '/time-entries?deleted=1')->withStatus(302);
    }

    private function renderHome(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $query = $request->getQueryParams();
        $defaultTo = date('Y-m-d');
        $defaultFrom = date('Y-m-d', strtotime('-14 days'));
        $continueRegistration = (string) ($query['continue_registration'] ?? '') === '1';

        $periodStart = trim((string) ($query['date_from'] ?? $defaultFrom));
        $periodEnd = trim((string) ($query['date_to'] ?? $defaultTo));

        if (!$this->isValidDate($periodStart)) {
            $periodStart = $defaultFrom;
        }
        if (!$this->isValidDate($periodEnd)) {
            $periodEnd = $defaultTo;
        }
        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

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

        $form = $old ?? [
            'date' => date('Y-m-d'),
            'client_id' => '',
            'work_category_id' => '',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'comment' => '',
        ];
        if ($old === null && $continueRegistration) {
            $form['date'] = trim((string) ($query['continue_date'] ?? $form['date']));
            $form['client_id'] = trim((string) ($query['continue_client_id'] ?? $form['client_id']));
            $form['work_category_id'] = trim((string) ($query['continue_work_category_id'] ?? $form['work_category_id']));
            $form['start_time'] = trim((string) ($query['continue_start_time'] ?? $form['start_time']));
            $form['end_time'] = trim((string) ($query['continue_end_time'] ?? $form['end_time']));
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
            'old' => $form,
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
            'continueRegistration' => $continueRegistration,
            'filterDateFrom' => $periodStart,
            'filterDateTo' => $periodEnd,
            'filterClientId' => $clientFilterRaw,
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
}
