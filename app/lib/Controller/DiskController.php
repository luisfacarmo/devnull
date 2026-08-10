<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Capability\DiskDetectorInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * API: Disk detection and listing.
 * Thin controller — delegates to DiskDetectorInterface capability.
 */
class DiskController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private DiskDetectorInterface $detector,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * List available (unmounted) disks.
     *
     * @return DataResponse
     */
    public function list(): DataResponse
    {
        $disks = $this->detector->listAvailable();

        return new DataResponse([
            'disks' => array_map(fn($d) => $d->toArray(), $disks),
        ]);
    }
}
