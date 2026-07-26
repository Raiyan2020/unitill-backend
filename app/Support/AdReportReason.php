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
        $translations = [
            self::SCAM_OR_FRAUDULENT => [
                'en' => 'Scam or fraudulent',
                'ar' => 'احتيال أو نصب',
                'fr' => 'Arnaque ou frauduleux',
                'es' => 'Estafa o fraude',
                'zh' => '诈骗或欺诈',
            ],
            self::INAPPROPRIATE_CONTENT => [
                'en' => 'Inappropriate content',
                'ar' => 'محتوى غير لائق',
                'fr' => 'Contenu inapproprié',
                'es' => 'Contenido inapropiado',
                'zh' => '不当内容',
            ],
            self::ITEM_DOESNT_MATCH => [
                'en' => "Item doesn't match description",
                'ar' => 'المنتج لا يطابق الوصف',
                'fr' => "L'article ne correspond pas à la description",
                'es' => 'El artículo no coincide con la descripción',
                'zh' => '商品与描述不符',
            ],
            self::PROHIBITED_ITEM => [
                'en' => 'Prohibited or restricted item',
                'ar' => 'منتج محظور أو مقيّد',
                'fr' => 'Article interdit ou restreint',
                'es' => 'Artículo prohibido o restringido',
                'zh' => '违禁或受限商品',
            ],
            self::WRONG_CATEGORY => [
                'en' => 'Wrong category',
                'ar' => 'قسم خاطئ',
                'fr' => 'Mauvaise catégorie',
                'es' => 'Categoría incorrecta',
                'zh' => '分类错误',
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