<?php

declare(strict_types=1);

namespace OCA\DevNull\Command;

use Psr\Log\LoggerInterface;

/**
 * Secure wrapper for executing shell commands.
 *
 * Security:
 * - Whitelist of allowed commands
 * - All arguments escaped via escapeshellarg()
 * - Verifies exec() is available before calling
 * - This is the ONLY class in the app that calls exec()
 */
class SecureCommandRunner
{
    private const ALLOWED_COMMANDS = [
        'lsblk',
        'udisksctl',
        'smartctl',
        'sudo',
        'php',
    ];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Check if shell execution is available on this system.
     */
    public function isAvailable(): bool
    {
        $disabled = explode(',', ini_get('disable_functions') ?: '');
        $disabled = array_map('trim', $disabled);
        return !in_array('exec', $disabled, true) && function_exists('exec');
    }

    /**
     * Execute a command with escaped arguments.
     *
     * @param string $command The command to run (must be whitelisted)
     * @param array<string> $args Arguments (will be escaped)
     * @return string Command output (stdout)
     * @throws \RuntimeException If command fails, is not allowed, or exec() is disabled
     */
    public function run(string $command, array $args = []): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'exec() está desabilitado neste servidor. Adicione exec à lista de funções permitidas no php.ini.'
            );
        }

        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new \RuntimeException("Comando não permitido: $command");
        }

        $escapedArgs = array_map('escapeshellarg', $args);
        $fullCommand = escapeshellcmd($command) . ' ' . implode(' ', $escapedArgs);

        $this->logger->debug('DevNull: exec', ['command' => $fullCommand]);

        $output = [];
        $returnCode = 0;
        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('DevNull: comando falhou', [
                'command' => $command,
                'returnCode' => $returnCode,
            ]);
            throw new \RuntimeException("Comando falhou (exit $returnCode): $command");
        }

        return implode("\n", $output);
    }
}
