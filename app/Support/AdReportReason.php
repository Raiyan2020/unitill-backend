<?php

namespace App\Support;

class AdReportReason
{
    public const SCAM_OR_FRAUDULENT = 'scam_or_fraudulent';

    public const INAPPROPRIATE_CONTENT = 'inappropriate_content';

    public const ITEM_DOESNT_MATCH = 'item_doesnt_match_description';

    public const PROHIBITED_ITEM = 'prohibited_or_restricted_item';

    public const WRONG_CATEGORY = 'wrong_category';

    public const OTHER = 'other';

    public static function allowed(): array
    {
        return [
            self::SCAM_OR_FRAUDULENT,
            self::INAPPROPRIATE_CONTENT,
            self::ITEM_DOESNT_MATCH,
            self::PROHIBITED_ITEM,
            self::WRONG_CATEGORY,
            self::OTHER,
        ];
    }

    public static function options(string $lang = 'en'): array
    {
        $ar = $lang === 'ar';

        return [
            [
                'value' => self::SCAM_OR_FRAUDULENT,
                'label' => $ar ? 'احتيال أو نصب' : 'Scam or fraudulent',
            ],
            [
                'value' => self::INAPPROPRIATE_CONTENT,
                'label' => $ar ? 'محتوى غير لائق' : 'Inappropriate content',
            ],
            [
                'value' => self::ITEM_DOESNT_MATCH,
                'label' => $ar ? 'المنتج لا يطابق الوصف' : "Item doesn't match description",
            ],
            [
                'value' => self::PROHIBITED_ITEM,
                'label' => $ar ? 'منتج محظور أو مقيّد' : 'Prohibited or restricted item',
            ],
            [
                'value' => self::WRONG_CATEGORY,
                'label' => $ar ? 'قسم خاطئ' : 'Wrong category',
            ],
            [
                'value' => self::OTHER,
                'label' => $ar ? 'سبب آخر' : 'Other',
            ],
        ];
    }
}
