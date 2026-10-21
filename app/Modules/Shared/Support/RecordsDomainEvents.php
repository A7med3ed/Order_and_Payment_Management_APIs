<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

trait RecordsDomainEvents
{
    /** @var list<object> */
    private array $events = [];

    protected function record(object $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}
