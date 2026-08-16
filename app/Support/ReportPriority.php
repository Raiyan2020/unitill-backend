<?php

namespace App\Support;

class ReportPriority
{
    public const NORMAL = 'normal';

    public const URGENT = 'urgent';

    public const CRITICAL = 'critical';

    public static function fromReason(?string $reason): string
    {
        return match ($reason) {
            ChatReportReason::THREATS_OR_VIOLENCE,
            ChatReportReason::CHILD_SEXUAL_ABUSE_OR_EXPLOITATION,
            ChatReportReason::IMMEDIATE_DANGER,
            ChatReportReason::TERRORISM_RELATED,
            ChatReportReason::SERIOUS_VIOLENCE,
            ChatReportReason::CREDIBLE_THREAT => self::CRITICAL,
            ChatReportReason::HARASSMENT_OR_ABUSE,
            ChatReportReason::SPAM_OR_SCAM,
            ChatReportReason::PAYMENT_OUTSIDE_APP,
            AdReportReason::SCAM_OR_FRAUDULENT,
            AdReportReason::PROHIBITED_ITEM => self::URGENT,
            default => self::NORMAL,
        };
    }

    public static function allowed(): array
    {
        return [self::NORMAL, self::URGENT, self::CRITICAL];
    }
}
