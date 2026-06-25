<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class DashboardRepository
{
    private ?string $queryStartDate = null;

    private ?string $queryEndDate = null;

    private function visitorKeySql(string $column = 'session_code'): string
    {
        return "CASE
            WHEN {$column} LIKE 'web-%-%' THEN SUBSTRING_INDEX({$column}, '-', 2)
            ELSE {$column}
        END";
    }

    public function trafficSummary(string $range = '7d'): array
    {
        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) {
            return ['uv' => 0, 'pv' => 0, 'bounce_rate' => 0];
        }

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);

        $statement = $pdo->prepare(
            "SELECT
                COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv,
                COUNT(*) AS pv,
                0 AS bounce_rate
             FROM visitor_events
             WHERE {$dateSql}"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        $row = $statement->fetch();

        return is_array($row) ? $row : ['uv' => 0, 'pv' => 0, 'bounce_rate' => 0];
    }

    public function trafficSeries(string $range = '7d'): array
    {
        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) return [];

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);

        $statement = $pdo->prepare(
            "SELECT
                DATE(visited_at) AS stat_date,
                COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv,
                COUNT(*) AS pv
             FROM visitor_events
             WHERE {$dateSql}
             GROUP BY DATE(visited_at)
             ORDER BY stat_date ASC"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function trafficCountrySummary(string $range = '7d'): array
    {
        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) return [];

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);

        $statement = $pdo->prepare(
            "SELECT
                NULLIF(TRIM(language_code), '') AS language_code,
                COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv,
                COUNT(*) AS pv
             FROM visitor_events
             WHERE {$dateSql} AND NULLIF(TRIM(language_code), '') IS NOT NULL
             GROUP BY NULLIF(TRIM(language_code), '')
             ORDER BY uv DESC, pv DESC
             LIMIT 10"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function trafficTopPages(string $range = '7d'): array
    {
        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) return [];

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);

        $statement = $pdo->prepare(
            "SELECT
                page AS landing_page,
                title,
                COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv,
                COUNT(*) AS pv
             FROM visitor_events
             WHERE {$dateSql}
             GROUP BY page, title
             ORDER BY uv DESC, pv DESC
             LIMIT 10"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function contentUvSummary(string $range = '7d'): array
    {
        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) {
            return [
                'product_uv' => 0,
                'solution_uv' => 0,
                'news_uv' => 0,
                'case_uv' => 0,
            ];
        }

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);

        $statement = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN content_type = 'product' THEN uv ELSE 0 END) AS product_uv,
                SUM(CASE WHEN content_type = 'solution' THEN uv ELSE 0 END) AS solution_uv,
                SUM(CASE WHEN content_type = 'news' THEN uv ELSE 0 END) AS news_uv,
                SUM(CASE WHEN content_type = 'case' THEN uv ELSE 0 END) AS case_uv
             FROM (
                SELECT
                    CASE
                        WHEN page REGEXP '/products/[^/?#]+(\\\\.html)?/?$' THEN 'product'
                        WHEN page REGEXP '/solutions/[^/?#]+(\\\\.html)?/?$' THEN 'solution'
                        WHEN page REGEXP '/news/[^/?#]+(\\\\.html)?/?$' THEN 'news'
                        WHEN page REGEXP '/cases/[^/?#]+(\\\\.html)?/?$' THEN 'case'
                        ELSE ''
                    END AS content_type,
                    COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv
                FROM visitor_events
                WHERE {$dateSql}
                GROUP BY page
             ) AS content_uv
             WHERE content_type <> ''"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [
            'product_uv' => 0,
            'solution_uv' => 0,
            'news_uv' => 0,
            'case_uv' => 0,
        ];
    }

    public function contentTopPagesByType(string $type, string $range = '7d', int $limit = 5): array
    {
        $patterns = [
            'product' => '/products/[^/?#]+(\\\\.html)?/?$',
            'solution' => '/solutions/[^/?#]+(\\\\.html)?/?$',
            'news' => '/news/[^/?#]+(\\\\.html)?/?$',
            'case' => '/cases/[^/?#]+(\\\\.html)?/?$',
        ];

        $pattern = $patterns[$type] ?? null;
        if ($pattern === null) {
            return [];
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof \PDO)) {
            return [];
        }

        [$dateSql, $dateParams] = $this->dateCondition('visited_at', false, $days);
        $safeLimit = max(1, min(20, $limit));
        $statement = $pdo->prepare(
            "SELECT
                page AS landing_page,
                title,
                COUNT(DISTINCT {$this->visitorKeySql('session_code')}) AS uv,
                COUNT(*) AS pv
             FROM visitor_events
             WHERE {$dateSql} AND page REGEXP :content_pattern
             GROUP BY page, title
             ORDER BY uv DESC, pv DESC
             LIMIT {$safeLimit}"
        );
        foreach ($dateParams as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->bindValue(':content_pattern', $pattern);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function aiSummary(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $conversations = $this->filterRuntimeRowsByDate($this->loadRuntimeConversations(), 'created_at', $this->normalizeRangeDays($range));
            $totalSessions = count($conversations);
            $validSessions = 0;
            $createdInquiries = 0;

            foreach ($conversations as $conversation) {
                if ((int) ($conversation['is_valid_conversation'] ?? 0) > 0) {
                    $validSessions += 1;
                }
                if ((int) ($conversation['inquiry_id'] ?? 0) > 0) {
                    $createdInquiries += 1;
                }
            }

            return [
                'total_sessions' => $totalSessions,
                'valid_sessions' => $validSessions,
                'created_inquiries' => $createdInquiries,
                'lead_capture_rate' => $totalSessions > 0 ? round(($createdInquiries / $totalSessions) * 100, 1) : 0.0,
            ];
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT COALESCE(SUM(total_sessions), 0) AS total_sessions,
                        COALESCE(SUM(valid_sessions), 0) AS valid_sessions,
                        COALESCE(SUM(created_inquiries), 0) AS created_inquiries,
                        COALESCE(SUM(lead_capture_rate * total_sessions) / NULLIF(SUM(total_sessions), 0), 0) AS lead_capture_rate
                 FROM ai_conversation_daily_stats
                 WHERE ' . $dateSql . ''
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $row = $statement->fetch();

            return is_array($row) ? $row : [];
        }

        return [];
    }

    public function aiSeries(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeConversations(), 'created_at', $this->normalizeRangeDays($range)) as $conversation) {
                $date = substr((string) ($conversation['created_at'] ?? ''), 0, 10);
                if ($date === '') {
                    continue;
                }

                if (!isset($grouped[$date])) {
                    $grouped[$date] = [
                        'stat_date' => $date,
                        'total_sessions' => 0,
                        'valid_sessions' => 0,
                        'created_inquiries' => 0,
                    ];
                }

                $grouped[$date]['total_sessions'] += 1;
                if ((int) ($conversation['is_valid_conversation'] ?? 0) > 0) {
                    $grouped[$date]['valid_sessions'] += 1;
                }
                if ((int) ($conversation['inquiry_id'] ?? 0) > 0) {
                    $grouped[$date]['created_inquiries'] += 1;
                }
            }

            ksort($grouped);

            return array_values($grouped);
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT DATE_FORMAT(stat_date, "%Y-%m-%d") AS stat_date,
                        COALESCE(SUM(total_sessions), 0) AS total_sessions,
                        COALESCE(SUM(valid_sessions), 0) AS valid_sessions,
                        COALESCE(SUM(created_inquiries), 0) AS created_inquiries
                 FROM ai_conversation_daily_stats
                 WHERE ' . $dateSql . '
                 GROUP BY stat_date
                 ORDER BY stat_date ASC'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return [];
    }

    public function aiCountrySummary(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeConversations(), 'created_at', $this->normalizeRangeDays($range)) as $conversation) {
                $countryCode = strtoupper(trim((string) ($conversation['country_code'] ?? '')));
                if ($countryCode === '') {
                    continue;
                }

                if (!isset($grouped[$countryCode])) {
                    $grouped[$countryCode] = [
                        'country_code' => $countryCode,
                        'total_sessions' => 0,
                        'created_inquiries' => 0,
                    ];
                }

                $grouped[$countryCode]['total_sessions'] += 1;
                if ((int) ($conversation['inquiry_id'] ?? 0) > 0) {
                    $grouped[$countryCode]['created_inquiries'] += 1;
                }
            }

            $rows = array_values($grouped);
            usort($rows, fn (array $left, array $right): int => ((int) ($right['total_sessions'] ?? 0)) <=> ((int) ($left['total_sessions'] ?? 0)));

            return array_slice($rows, 0, 5);
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT country_code, COALESCE(SUM(total_sessions), 0) AS total_sessions, COALESCE(SUM(created_inquiries), 0) AS created_inquiries
                 FROM ai_conversation_daily_stats
                 WHERE ' . $dateSql . '
                 GROUP BY country_code
                 ORDER BY total_sessions DESC
                 LIMIT 5'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return [];
    }

    public function aiTopicSummary(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeMessagesByDate($this->loadRuntimeConversations(), $this->normalizeRangeDays($range)) as $message) {
                $intentCode = trim((string) ($message['intent_code'] ?? ''));
                if ($intentCode === '') {
                    continue;
                }

                $grouped[$intentCode] = ($grouped[$intentCode] ?? 0) + 1;
            }

            $rows = array_map(static fn (string $intentCode, int $count): array => [
                'intent_code' => $intentCode,
                'total_count' => $count,
            ], array_keys($grouped), array_values($grouped));
            usort($rows, fn (array $left, array $right): int => ((int) ($right['total_count'] ?? 0)) <=> ((int) ($left['total_count'] ?? 0)));

            return array_slice($rows, 0, 5);
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('created_at', true, $days);
            $statement = $pdo->prepare(
                'SELECT intent_code, COUNT(*) AS total_count
                 FROM chat_messages
                 WHERE ' . $dateSql . ' AND intent_code IS NOT NULL AND intent_code <> ""
                 GROUP BY intent_code
                 ORDER BY total_count DESC
                 LIMIT 5'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return [];
    }

    public function inquirySummary(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeInquiries(), 'created_at', $this->normalizeRangeDays($range)) as $inquiry) {
                $status = trim((string) ($inquiry['status'] ?? ''));
                if ($status === '') {
                    continue;
                }

                $grouped[$status] = ($grouped[$status] ?? 0) + 1;
            }

            return array_map(static fn (string $status, int $count): array => [
                'status' => $status,
                'total_count' => $count,
            ], array_keys($grouped), array_values($grouped));
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT status, SUM(total_count) AS total_count
                 FROM inquiry_daily_stats
                 WHERE ' . $dateSql . '
                 GROUP BY status'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }

            [$dateSql2, $dateParams2] = $this->dateCondition('created_at', true, $days);
            $fallbackStatement = $pdo->prepare(
                'SELECT status, COUNT(*) AS total_count
                 FROM inquiries
                 WHERE ' . $dateSql2 . '
                 GROUP BY status'
            );
            foreach ($dateParams2 as $k => $v) {
                $fallbackStatement->bindValue($k, $v);
            }
            $fallbackStatement->execute();
            $fallbackRows = $fallbackStatement->fetchAll();

            return is_array($fallbackRows) ? $fallbackRows : [];
        }

        return [];
    }

    public function inquirySeries(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeInquiries(), 'created_at', $this->normalizeRangeDays($range)) as $inquiry) {
                $date = substr((string) ($inquiry['created_at'] ?? ''), 0, 10);
                $status = trim((string) ($inquiry['status'] ?? ''));
                if ($date === '' || $status === '') {
                    continue;
                }

                $key = $date . '|' . $status;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'stat_date' => $date,
                        'status' => $status,
                        'total_count' => 0,
                    ];
                }
                $grouped[$key]['total_count'] += 1;
            }

            $rows = array_values($grouped);
            usort($rows, function (array $left, array $right): int {
                $dateCompare = strcmp((string) ($left['stat_date'] ?? ''), (string) ($right['stat_date'] ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return strcmp((string) ($left['status'] ?? ''), (string) ($right['status'] ?? ''));
            });

            return $rows;
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT DATE_FORMAT(stat_date, "%Y-%m-%d") AS stat_date, status, SUM(total_count) AS total_count
                 FROM inquiry_daily_stats
                 WHERE ' . $dateSql . '
                 GROUP BY stat_date, status
                 ORDER BY stat_date ASC, status ASC'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }

            [$dateSql2, $dateParams2] = $this->dateCondition('created_at', true, $days);
            $fallbackStatement = $pdo->prepare(
                'SELECT DATE_FORMAT(created_at, "%Y-%m-%d") AS stat_date, status, COUNT(*) AS total_count
                 FROM inquiries
                 WHERE ' . $dateSql2 . '
                 GROUP BY DATE_FORMAT(created_at, "%Y-%m-%d"), status
                 ORDER BY stat_date ASC, status ASC'
            );
            foreach ($dateParams2 as $k => $v) {
                $fallbackStatement->bindValue($k, $v);
            }
            $fallbackStatement->execute();
            $fallbackRows = $fallbackStatement->fetchAll();

            return is_array($fallbackRows) ? $fallbackRows : [];
        }

        return [];
    }

    public function inquiryCountrySummary(string $range = '7d'): array
    {
        if ($this->preferRuntimeStorage()) {
            $grouped = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeInquiries(), 'created_at', $this->normalizeRangeDays($range)) as $inquiry) {
                $countryCode = strtoupper(trim((string) ($inquiry['country_code'] ?? '')));
                if ($countryCode === '') {
                    continue;
                }

                $grouped[$countryCode] = ($grouped[$countryCode] ?? 0) + 1;
            }

            $rows = array_map(static fn (string $countryCode, int $count): array => [
                'country_code' => $countryCode,
                'total_count' => $count,
            ], array_keys($grouped), array_values($grouped));
            usort($rows, fn (array $left, array $right): int => ((int) ($right['total_count'] ?? 0)) <=> ((int) ($left['total_count'] ?? 0)));

            return array_slice($rows, 0, 5);
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT country_code, SUM(total_count) AS total_count
                 FROM inquiry_daily_stats
                 WHERE ' . $dateSql . '
                 GROUP BY country_code
                 ORDER BY total_count DESC
                 LIMIT 5'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $rows = $statement->fetchAll();
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }

            [$dateSql2, $dateParams2] = $this->dateCondition('created_at', true, $days);
            $fallbackStatement = $pdo->prepare(
                'SELECT country_code, COUNT(*) AS total_count
                 FROM inquiries
                 WHERE ' . $dateSql2 . '
                 GROUP BY country_code
                 ORDER BY total_count DESC
                 LIMIT 5'
            );
            foreach ($dateParams2 as $k => $v) {
                $fallbackStatement->bindValue($k, $v);
            }
            $fallbackStatement->execute();
            $fallbackRows = $fallbackStatement->fetchAll();

            return is_array($fallbackRows) ? $fallbackRows : [];
        }

        return [];
    }

    public function inquiryAvgFirstResponseMinutes(string $range = '7d'): float
    {
        if ($this->preferRuntimeStorage()) {
            $durations = [];
            foreach ($this->filterRuntimeRowsByDate($this->loadRuntimeInquiries(), 'created_at', $this->normalizeRangeDays($range)) as $inquiry) {
                $createdAt = strtotime((string) ($inquiry['created_at'] ?? ''));
                $firstResponseAt = strtotime((string) ($inquiry['first_response_at'] ?? ''));
                if ($createdAt === false || $firstResponseAt === false) {
                    continue;
                }

                $durations[] = max(0, (int) floor(($firstResponseAt - $createdAt) / 60));
            }

            if ($durations === []) {
                return 0.0;
            }

            return array_sum($durations) / count($durations);
        }

        $days = $this->normalizeRangeDays($range);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            [$dateSql, $dateParams] = $this->dateCondition('stat_date', false, $days);
            $statement = $pdo->prepare(
                'SELECT AVG(avg_first_response_minutes) AS avg_first_response_minutes
                 FROM inquiry_daily_stats
                 WHERE ' . $dateSql . ' AND avg_first_response_minutes IS NOT NULL'
            );
            foreach ($dateParams as $k => $v) {
                $statement->bindValue($k, $v);
            }
            $statement->execute();
            $value = $statement->fetch()['avg_first_response_minutes'] ?? null;
            if ($value !== null) {
                return (float) $value;
            }

            [$dateSql2, $dateParams2] = $this->dateCondition('created_at', true, $days);
            $fallbackStatement = $pdo->prepare(
                'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_first_response_minutes
                 FROM inquiries
                 WHERE ' . $dateSql2 . ' AND first_response_at IS NOT NULL'
            );
            foreach ($dateParams2 as $k => $v) {
                $fallbackStatement->bindValue($k, $v);
            }
            $fallbackStatement->execute();
            $fallbackValue = $fallbackStatement->fetch()['avg_first_response_minutes'] ?? 0;

            return (float) $fallbackValue;
        }

        return 0.0;
    }

    public function setCustomDateRange(?string $startDate, ?string $endDate): void
    {
        $this->queryStartDate = $startDate;
        $this->queryEndDate = $endDate;
    }

    /**
     * @return array{0:string,1:array<string,mixed>} [sql, params]
     */
    private function dateCondition(string $dateField, bool $isDateTime, int $days): array
    {
        if ($this->queryStartDate !== null && $this->queryEndDate !== null) {
            $sql = sprintf('%s >= :qsd AND %s <= :qed', $dateField, $dateField);
            return [$sql, ['qsd' => $this->queryStartDate, 'qed' => $this->queryEndDate . ' 23:59:59']];
        }

        if ($isDateTime) {
            return [$dateField . ' >= DATE_SUB(NOW(), INTERVAL :days DAY)', ['days' => $days]];
        }

        return [$dateField . ' >= DATE_SUB(CURDATE(), INTERVAL :days DAY)', ['days' => $days - 1]];
    }

    public function normalizeRangeDays(string $range): int
    {
        return match (trim(strtolower($range))) {
            '30d' => 30,
            '14d' => 14,
            '1d' => 1,
            default => 7,
        };
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([
            dirname(__DIR__, 2) . '/runtime/storage/conversations.json',
            dirname(__DIR__, 2) . '/runtime/storage/inquiries.json',
            dirname(__DIR__, 2) . '/runtime/storage/visitor_events.json',
        ]);
    }

    private function hasTableColumn(\PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $statement = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $statement->execute(['column' => $column]);

        return $cache[$key] = (bool) $statement->fetch();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeConversations(): array
    {
        return $this->loadRuntimeList('conversations.json');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeInquiries(): array
    {
        return $this->loadRuntimeList('inquiries.json');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeList(string $fileName): array
    {
        $path = dirname(__DIR__, 2) . '/runtime/storage/' . $fileName;
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterRuntimeRowsByDate(array $rows, string $field, int $days): array
    {
        return array_values(array_filter($rows, function (array $row) use ($field, $days): bool {
            return $this->runtimeDateInRange((string) ($row[$field] ?? ''), $days);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $conversations
     * @return array<int, array<string, mixed>>
     */
    private function filterRuntimeMessagesByDate(array $conversations, int $days): array
    {
        $messages = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation['messages'] ?? [] as $message) {
                if (!is_array($message)) {
                    continue;
                }
                if (!$this->runtimeDateInRange((string) ($message['created_at'] ?? ''), $days)) {
                    continue;
                }

                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function runtimeDateInRange(string $value, int $days): bool
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        if ($this->queryStartDate !== null && $this->queryEndDate !== null) {
            $start = strtotime($this->queryStartDate . ' 00:00:00');
            $end = strtotime($this->queryEndDate . ' 23:59:59');

            return $start !== false && $end !== false && $timestamp >= $start && $timestamp <= $end;
        }

        $start = strtotime('-' . max(1, $days - 1) . ' days 00:00:00');
        $end = strtotime('today 23:59:59');

        return $start !== false && $end !== false && $timestamp >= $start && $timestamp <= $end;
    }
}
