<?php

namespace App\Support;

class ChatReportReason
{
    public const HARASSMENT_OR_ABUSE = 'harassment_or_abuse';
    public const SPAM_OR_SCAM = 'spam_or_scam';
    public const INAPPROPRIATE_CONTENT = 'inappropriate_content';
    public const THREATS_OR_VIOLENCE = 'threats_or_violence';
    public const IMPERSONATION = 'impersonation';
    public const PAYMENT_OUTSIDE_APP = 'payment_outside_app';
    public const OTHER = 'other';

    public static function allowed(): array
    {
        return [
            self::HARASSMENT_OR_ABUSE,
            self::SPAM_OR_SCAM,
            self::INAPPROPRIATE_CONTENT,
            self::THREATS_OR_VIOLENCE,
            self::IMPERSONATION,
            self::PAYMENT_OUTSIDE_APP,
            self::OTHER,
        ];
    }

    public static function options(string $lang = 'en'): array
    {
        $translations = [
            self::HARASSMENT_OR_ABUSE => [
                'en' => 'Harassment or abuse',
                'ar' => 'تحرّش أو إساءة',
                'fr' => 'Harcèlement ou abus',
                'es' => 'Acoso o abuso',
                'zh' => '骚扰或辱骂',
            ],
            self::SPAM_OR_SCAM => [
                'en' => 'Spam or scam',
                'ar' => 'رسائل مزعجة أو احتيال',
                'fr' => 'Spam ou arnaque',
                'es' => 'Spam o estafa',
                'zh' => '垃圾信息或诈骗',
            ],
            self::INAPPROPRIATE_CONTENT => [
                'en' => 'Inappropriate content',
                'ar' => 'محتوى غير لائق',
                'fr' => 'Contenu inapproprié',
                'es' => 'Contenido inapropiado',
                'zh' => '不当内容',
            ],
            self::THREATS_OR_VIOLENCE => [
                'en' => 'Threats or violence',
                'ar' => 'تهديد أو عنف',
                'fr' => 'Menaces ou violence',
                'es' => 'Amenazas o violencia',
                'zh' => '威胁或暴力',
            ],
            self::IMPERSONATION => [
                'en' => 'Impersonation',
                'ar' => 'انتحال شخصية',
                'fr' => 'Usurpation d’identité',
                'es' => 'Suplantación de identidad',
                'zh' => '冒充他人',
            ],
            self::PAYMENT_OUTSIDE_APP => [
                'en' => 'Asking to pay outside the app',
                'ar' => 'طلب الدفع خارج التطبيق',
                'fr' => 'Demande de paiement hors application',
                'es' => 'Solicitar pago fuera de la aplicación',
                'zh' => '要求在应用外付款',
            ],
            self::OTHER => [
                'en' => 'Other',
                'ar' => 'سبب آخر',
                'fr' => 'Autre',
                'es' => 'Otro',
                'zh' => '其他',
            ],
        ];

        $results = [];
        foreach (self::allowed() as $value) {
            $results[] = [
                'value' => $value,
                'label' => $translations[$value][$lang] ?? $translations[$value]['en'],
            ];
        }

        return $results;
    }

    public static function label(?string $value, string $lang = 'en'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (self::options($lang) as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}