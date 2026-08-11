<?php

declare(strict_types=1);

namespace OCA\DevNull\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin settings section for DevNull.
 */
class AdminSection implements IIconSection
{
    public function __construct(
        private IURLGenerator $urlGenerator,
        private IL10N $l,
    ) {
    }

    public function getID(): string
    {
        return 'devnull';
    }

    public function getName(): string
    {
        return $this->l->t('DevNull');
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath('devnull', 'app-dark.svg');
    }
}
