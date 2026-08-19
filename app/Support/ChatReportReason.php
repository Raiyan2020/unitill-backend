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

    public const CHILD_SEXUAL_ABUSE_OR_EXPLOITATION = 'child_sexual_abuse_or_exploitation';

    public const IMMEDIATE_DANGER = 'immediate_danger';

    public const TERRORISM_RELATED = 'terrorism_related';

    public const SERIOUS_VIOLENCE = 'serious_violence';

    public const CREDIBLE_THREAT = 'credible_threat';

    public const OTHER = 'other';

    public static function legacyAllowed(): array
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

    public static function seriousSafety(): array
    {
        return [
            self::CHILD_SEXUAL_ABUSE_OR_EXPLOITATION,
            self::IMMEDIATE_DANGER,
            self::TERRORISM_RELATED,
            self::SERIOUS_VIOLENCE,
            self::CREDIBLE_THREAT,
        ];
    }

    public static function allowed(): array
    {
        return array_merge(self::legacyAllowed(), self::seriousSafety());
    }

    public static function options(string $lang = 'en', bool $includeSeriousSafety = true): array
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
            self::CHILD_SEXUAL_ABUSE_OR_EXPLOITATION => [
                'en' => 'Child sexual abuse or exploitation',
                'ar' => 'اعتداء أو استغلال جنسي للأطفال',
                'fr' => 'Abus ou exploitation sexuelle d’enfants',
                'es' => 'Abuso o explotación sexual infantil',
                'zh' => '儿童性虐待或剥削',
            ],
            self::IMMEDIATE_DANGER => [
                'en' => 'Immediate danger',
                'ar' => 'خطر فوري',
                'fr' => 'Danger immédiat',
                'es' => 'Peligro inmediato',
                'zh' => '紧迫危险',
            ],
            self::TERRORISM_RELATED => [
                'en' => 'Terrorism-related content',
                'ar' => 'محتوى متعلق بالإرهاب',
                'fr' => 'Contenu lié au terrorisme',
                'es' => 'Contenido relacionado con terrorismo',
                'zh' => '涉恐内容',
            ],
            self::SERIOUS_VIOLENCE => [
                'en' => 'Serious violence',
                'ar' => 'عنف خطير',
                'fr' => 'Violence grave',
                'es' => 'Violencia grave',
                'zh' => '严重暴力',
            ],
            self::CREDIBLE_THREAT => [
                'en' => 'Credible threat',
                'ar' => 'تهديد موثوق',
                'fr' => 'Menace crédible',
                'es' => 'Amenaza creíble',
                'zh' => '可信威胁',
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
        $allowed = $includeSeriousSafety ? self::allowed() : self::legacyAllowed();
        foreach ($allowed as $value) {
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
