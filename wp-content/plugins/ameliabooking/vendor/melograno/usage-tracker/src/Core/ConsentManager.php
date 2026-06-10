<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Core;

class ConsentManager
{
    /** @var string */
    private $optionName;

    public function __construct(string $optionName)
    {
        $this->optionName = $optionName;
    }

    public function isConfigured(): bool
    {
        return get_option($this->optionName, null) !== null;
    }

    public function isEnabled(): bool
    {
        if (!$this->isConfigured()) {
            return true;
        }

        return (bool) get_option($this->optionName, false);
    }

    public function enable(): void
    {
        update_option($this->optionName, 1, true);
    }

    public function disable(): void
    {
        update_option($this->optionName, 0, true);
    }

    public function getOptionName(): string
    {
        return $this->optionName;
    }

    public function delete(): void
    {
        delete_option($this->optionName);
    }
}
