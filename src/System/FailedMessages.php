<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * Number of messages in the failure transport of Symfony Messenger. Only available when a "failure_transport" is
 * configured (the transport is then injected, otherwise null). Reads only, nothing is retried or removed.
 */
class FailedMessages
{
    private const LIMIT = 1000;

    public function __construct(private readonly ?object $failureTransport = null)
    {
    }

    public function isConfigured(): bool
    {
        return null !== $this->failureTransport;
    }

    /**
     * Null when the transport cannot count its messages.
     */
    public function count(): ?int
    {
        if ($this->failureTransport instanceof MessageCountAwareInterface) {
            return $this->failureTransport->getMessageCount();
        }

        if ($this->failureTransport instanceof ListableReceiverInterface) {
            return \count(iterator_to_array($this->failureTransport->all(self::LIMIT), false));
        }

        return null;
    }
}
