<?php

declare(strict_types=1);

namespace app\common\database;

use app\common\config\ConfigRepository;
use PDO;
use PDOException;

final class DatabaseManager
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private ?PDO $pdo = null;

    private bool $connectionAttempted = false;

    private bool $initialConfigured = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function configure(array $config): void
    {
        if ($this->initialConfigured) {
            return;
        }
        $this->config = $config;
        $this->pdo = null;
        $this->connectionAttempted = false;
        $this->initialConfigured = true;
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['hostname']) && !empty($this->config['database']) && class_exists(PDO::class);
    }

    public function connection(): ?PDO
    {
        $this->ensureConfigured();

        if (!$this->isConfigured()) {
            return null;
        }

        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if ($this->connectionAttempted) {
            return null;
        }

        $this->connectionAttempted = true;

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                (string) $this->config['hostname'],
                (string) $this->config['hostport'],
                (string) $this->config['database'],
                (string) $this->config['charset']
            );

            $this->pdo = new PDO(
                $dsn,
                (string) $this->config['username'],
                (string) $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => (int) ($this->config['connect_timeout'] ?? 1),
                    PDO::ATTR_PERSISTENT => true,
                ]
            );
        } catch (PDOException) {
            return null;
        }

        return $this->pdo;
    }

    private function ensureConfigured(): void
    {
        if ($this->isConfigured()) {
            return;
        }

        $config = ConfigRepository::instance()->get('database.connections.mysql', []);
        if (is_array($config) && !empty($config['hostname']) && !empty($config['database'])) {
            $this->config = $config;
        }
    }
}
