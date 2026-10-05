<?php

namespace App\Enums;

enum ConversationState: string
{
    case New = 'new';
    case AiHandling = 'ai_handling';
    case HumanHandling = 'human_handling';
    case WaitingForCustomer = 'waiting_for_customer';
    case WaitingForBusiness = 'waiting_for_business';
    case Escalated = 'escalated';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::AiHandling => 'AI Handling',
            self::HumanHandling => 'Human Handling',
            self::WaitingForCustomer => 'Waiting for Customer',
            self::WaitingForBusiness => 'Waiting for Business',
            self::Escalated => 'Escalated',
            self::Resolved => 'Resolved',
        };
    }

    public function isHandledByAi(): bool
    {
        return $this === self::AiHandling;
    }

    public function isHandledByHuman(): bool
    {
        return in_array($this, [self::HumanHandling, self::Escalated]);
    }
}
