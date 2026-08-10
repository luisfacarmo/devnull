<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Status Reporting
 *
 * Responsible for delivering status updates to the frontend.
 * Implementations may use polling endpoint, SSE, or WebSocket.
 *
 * Domain boundary: ONLY transports status data. Never modifies state.
 */
interface StatusTransportInterface
{
    /**
     * Get current status for a user's operations.
     *
     * @param string $userId
     * @return array<string, mixed> Status payload
     */
    public function getStatus(string $userId): array;

    /**
     * Get the transport type identifier (for frontend negotiation).
     *
     * @return string e.g., "polling", "sse"
     */
    public function getTransportType(): string;
}
