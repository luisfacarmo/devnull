<?php

declare(strict_types=1);

namespace OCA\DevNull\Command;

use Psr\Log\LoggerInterface;

/**
 * Secure wrapper for executing shell commands.
 *
 * Security measures:
 * - Whitelist of allowed commands
 * - All arguments escaped via escapeshellarg()
 * - Command name validated via escapeshellcmd()
 * - Execution logged for audit
 *
 * This is the ONLY class in the app that calls exec().
 */
class SecureCommandRunner
{
    private const ALLOWED_COMMANDS = [
        'lsblk',
        'udisksctl',
        'smartctl',
        'sudo',
    ];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Execute a command with escaped arguments.
     *
     * @param string $command The command to run (must be whitelisted)
     * @param array<string> $args Arguments (will be escaped)
     * @return string Command output (stdout)
     * @throws \RuntimeException If command fails or is not allowed
     */
    public function run(string $command, array $args = []): string
    {
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new \RuntimeException("Command not allowed: $command");
        }

        $escapedArgs = array_map('escapeshellarg', $args);
        $fullCommand = escapeshellcmd($command) . ' ' . implode(' ', $escapedArgs);

        $this->logger->debug('DevNull: exec', ['command' => $fullCommand]);

        $output = [];
        $returnCode = 0;
        exec($fullCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('DevNull: command failed', [
                'command' => $command,
                'returnCode' => $returnCode,
            ]);
            throw new \RuntimeException("Command failed (exit $returnCode): $command");
        }

        return implode("\n", $output);
    }
}
