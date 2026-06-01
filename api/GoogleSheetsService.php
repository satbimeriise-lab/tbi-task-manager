<?php
// ============================================================
//  Google Sheets API Service — all DB operations go here
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

class GoogleSheetsService
{
    private Google\Service\Sheets $service;
    private string $spreadsheetId;

    public function __construct()
    {
        $client = new Google\Client();
        $client->setApplicationName(APP_NAME);
        $client->setScopes([Google\Service\Sheets::SPREADSHEETS]);
        $client->setAuthConfig(CREDENTIALS_PATH);

        $this->service       = new Google\Service\Sheets($client);
        $this->spreadsheetId = SPREADSHEET_ID;
    }

    // ── Raw sheet rows (2-D array, first row = headers) ─────────
    public function getRawData(string $sheet): array
    {
        try {
            $resp = $this->service->spreadsheets_values->get(
                $this->spreadsheetId, $sheet
            );
            return $resp->getValues() ?? [];
        } catch (Exception $e) {
            error_log("Sheets getRawData($sheet): " . $e->getMessage());
            return [];
        }
    }

    // ── Associative array (headers → values) ────────────────────
    public function getAll(string $sheet): array
    {
        $rows = $this->getRawData($sheet);
        if (empty($rows)) return [];

        $headers = array_shift($rows);
        $result  = [];

        foreach ($rows as $row) {
            if (empty(array_filter($row))) continue; // skip blank rows
            $record = [];
            foreach ($headers as $i => $h) {
                $record[$h] = $row[$i] ?? '';
            }
            $result[] = $record;
        }
        return $result;
    }

    // ── Find one record by field value ───────────────────────────
    public function findOne(string $sheet, string $field, string $value): ?array
    {
        $all = $this->getAll($sheet);
        foreach ($all as $rec) {
            if (isset($rec[$field]) && $rec[$field] === $value) {
                return $rec;
            }
        }
        return null;
    }

    // ── Find all records matching a field value ──────────────────
    public function findMany(string $sheet, string $field, string $value): array
    {
        return array_values(array_filter(
            $this->getAll($sheet),
            fn($r) => ($r[$field] ?? '') === $value
        ));
    }

    // ── Append a new row ─────────────────────────────────────────
    public function appendRow(string $sheet, array $values): bool
    {
        try {
            $body   = new Google\Service\Sheets\ValueRange(['values' => [$values]]);
            $params = ['valueInputOption' => 'USER_ENTERED'];
            $this->service->spreadsheets_values->append(
                $this->spreadsheetId, $sheet, $body, $params
            );
            return true;
        } catch (Exception $e) {
            error_log("Sheets appendRow($sheet): " . $e->getMessage());
            return false;
        }
    }

    // ── Update a row by 1-based sheet row number ─────────────────
    public function updateRowByIndex(string $sheet, int $rowIndex, array $values): bool
    {
        try {
            $range  = "{$sheet}!{$rowIndex}:{$rowIndex}";
            $body   = new Google\Service\Sheets\ValueRange(['values' => [$values]]);
            $params = ['valueInputOption' => 'USER_ENTERED'];
            $this->service->spreadsheets_values->update(
                $this->spreadsheetId, $range, $body, $params
            );
            return true;
        } catch (Exception $e) {
            error_log("Sheets updateRow($sheet, $rowIndex): " . $e->getMessage());
            return false;
        }
    }

    // ── Update record by ID field ─────────────────────────────────
    public function updateById(string $sheet, string $idField, string $id, array $changes): bool
    {
        $rows    = $this->getRawData($sheet);
        if (empty($rows)) return false;

        $headers  = $rows[0];
        $idColIdx = array_search($idField, $headers);
        if ($idColIdx === false) return false;

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            if (($row[$idColIdx] ?? '') !== $id) continue;

            // Merge changes into existing row
            $newRow = $row;
            foreach ($changes as $field => $val) {
                $col = array_search($field, $headers);
                if ($col !== false) $newRow[$col] = $val;
            }
            // Pad to header width
            while (count($newRow) < count($headers)) $newRow[] = '';

            return $this->updateRowByIndex($sheet, $i + 1, $newRow);
        }
        return false;
    }

    // ── Delete (clear) a row by ID ────────────────────────────────
    public function deleteById(string $sheet, string $idField, string $id): bool
    {
        $rows    = $this->getRawData($sheet);
        if (empty($rows)) return false;

        $headers  = $rows[0];
        $idColIdx = array_search($idField, $headers);
        if ($idColIdx === false) return false;

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            if (($row[$idColIdx] ?? '') !== $id) continue;

            $range = "{$sheet}!{$i}:{$i}" ; // note: $i is already 1-based after +1 for header
            // Actually i starts at 1 for header row, so data row index = i+1
            $dataRowIndex = $i + 1;
            $range = "{$sheet}!{$dataRowIndex}:{$dataRowIndex}";
            try {
                $this->service->spreadsheets_values->clear(
                    $this->spreadsheetId, $range,
                    new Google\Service\Sheets\ClearValuesRequest()
                );
                return true;
            } catch (Exception $e) {
                error_log("Sheets deleteById: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    // ── Auto-generate a unique ID ─────────────────────────────────
    public static function newId(string $prefix): string
    {
        return strtoupper($prefix) . '_' . date('Ymd') . '_' . strtoupper(substr(uniqid('', true), -6));
    }

    // ── Count rows matching a filter ──────────────────────────────
    public function count(string $sheet, ?string $field = null, ?string $value = null): int
    {
        $all = $this->getAll($sheet);
        if ($field === null) return count($all);
        return count(array_filter($all, fn($r) => ($r[$field] ?? '') === $value));
    }

    // ── Update pending day counts for overdue tasks ───────────────
    public function refreshPendingDays(): void
    {
        $rows    = $this->getRawData(SHEET_TASKS);
        if (empty($rows)) return;

        $headers  = $rows[0];
        $statusIdx   = array_search('Status',       $headers);
        $deadlineIdx = array_search('Deadline',     $headers);
        $pendingIdx  = array_search('Days_Pending', $headers);
        $today       = new DateTime();

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            $status   = $row[$statusIdx]   ?? '';
            $deadline = $row[$deadlineIdx] ?? '';

            if (in_array($status, ['Pending', 'In Progress']) && $deadline) {
                try {
                    $dl   = new DateTime($deadline);
                    $diff = $today > $dl ? (int)$today->diff($dl)->days : 0;
                    if ($diff > 0) {
                        $newRow = $row;
                        while (count($newRow) < count($headers)) $newRow[] = '';
                        $newRow[$pendingIdx] = $diff;
                        $this->updateRowByIndex(SHEET_TASKS, $i + 1, $newRow);
                    }
                } catch (Exception) {}
            }
        }
    }
}
