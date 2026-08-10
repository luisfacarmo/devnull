<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Capability\StatusTransportInterface;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * API: Status and progress reporting.
 * Delegates to StatusTransportInterface capability.
 */
class StatusController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private StatusTransportInterface $statusTransport,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get current status for the requesting user.
     *
     * @return DataResponse
     */
    public function index(): DataResponse
    {
        return new DataResponse([
            'transport' => $this->statusTransport->getTransportType(),
            'status' => $this->statusTransport->getStatus($this->userId),
        ]);
    }
}
