<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Database\Seeder;

class LegalAffairSeeder extends Seeder
{
    public function run(): void
    {
        $languages = Language::query()->active()->get(['id', 'code'])->keyBy('code');

        if ($languages->isEmpty()) {
            return;
        }

        $policies = [
            [
                'key' => 'community_guidelines',
                'sort_order' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Community Guidelines',
                        'subtitle' => 'Expected behavior within the UniTill student community.',
                        'description' => json_encode([
                            'Respect: Treat every student with kindness and respect. Harassment, hate speech, or bullying will lead to a permanent ban.',
                            'Honesty: Be truthful about item conditions. Do not use stock photos; always provide real pictures of the actual item.',
                            'Fair Pricing: While we don\'t control prices, we encourage fair, student-friendly pricing to help the community.',
                            'No Spam: Do not post duplicate ads or send unsolicited marketing messages to other users.',
                            'Communication: Keep all business-related conversations professional and relevant to the listing.',
                            'Safety First: Report any suspicious behavior or users asking for off-platform payments immediately.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'ar' => [
                        'title' => 'إرشادات المجتمع',
                        'subtitle' => 'السلوك المتوقع داخل مجتمع طلاب UniTill.',
                        'description' => json_encode([
                            'الاحترام: عامل كل طالب بلطف واحترام. المضايقة أو خطاب الكراهية أو التنمر يؤدي إلى حظر دائم.',
                            'الصدق: كن صادقاً بشأن حالة المنتج. لا تستخدم صوراً جاهزة؛ قدّم دائماً صوراً حقيقية للمنتج الفعلي.',
                            'تسعير عادل: رغم أننا لا نتحكم في الأسعار، نشجع تسعيراً عادلاً ومناسباً للطلاب.',
                            'لا للرسائل المزعجة: لا تنشر إعلانات مكررة ولا ترسل رسائل تسويقية غير مرغوب فيها.',
                            'التواصل: اجعل المحادثات المتعلقة بالبيع مهنية ومرتبطة بالإعلان فقط.',
                            'السلامة أولاً: أبلغ فوراً عن أي سلوك مشبوه أو طلب دفع خارج المنصة.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
            [
                'key' => 'terms_of_service',
                'sort_order' => 2,
                'translations' => [
                    'en' => [
                        'title' => 'Terms of Service',
                        'subtitle' => 'Rules for using the UniTill marketplace platform.',
                        'description' => json_encode([
                            'Eligibility: You must be a verified student at a UK university to list items. Non-students can browse but may have restricted access.',
                            'Prohibited Items: We strictly prohibit the sale of alcohol, tobacco, weapons, illegal substances, and hazardous materials.',
                            'Fees: Posting fees are non-refundable once an ad goes live. We do not take a commission on student-to-student transactions.',
                            'Safety: Meet buyers in well-lit, public campus areas. We recommend bringing a friend for high-value transactions.',
                            'Accuracy: All listings must accurately represent the condition of the item. Misleading ads will be removed.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'ar' => [
                        'title' => 'شروط الخدمة',
                        'subtitle' => 'قواعد استخدام منصة UniTill.',
                        'description' => json_encode([
                            'الأهلية: يجب أن تكون طالباً موثقاً في جامعة بريطانية لنشر الإعلانات. غير الطلاب يمكنهم التصفح مع قيود.',
                            'منتجات محظورة: نحظر بيع الكحول والتبغ والأسلحة والمواد غير القانونية والخطرة.',
                            'الرسوم: رسوم النشر غير قابلة للاسترداد بعد نشر الإعلان. لا نأخذ عمولة على المعاملات بين الطلاب.',
                            'السلامة: قابل المشترين في أماكن عامة ومضاءة داخل الحرم. ننصح بإحضار صديق للمعاملات عالية القيمة.',
                            'الدقة: يجب أن تعكس الإعلانات حالة المنتج بدقة. الإعلانات المضللة تُزال.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
            [
                'key' => 'privacy_policy',
                'sort_order' => 3,
                'translations' => [
                    'en' => [
                        'title' => 'Privacy Policy',
                        'subtitle' => 'How we collect and protect your student identity data.',
                        'description' => json_encode([
                            'Data Collection: We collect your name, university email, and phone number for verification purposes.',
                            'Student Verification: We use your .ac.uk email to ensure a safe, student-only environment.',
                            'Data Sharing: We never sell your personal data to third-party advertisers.',
                            'Retention: Your data is kept while your account is active and for 12 months following deletion to comply with legal obligations.',
                            'Security: All data is encrypted using industry-standard 256-bit AES encryption.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'ar' => [
                        'title' => 'سياسة الخصوصية',
                        'subtitle' => 'كيف نجمع ونحمي بيانات هويتك الطلابية.',
                        'description' => json_encode([
                            'جمع البيانات: نجمع اسمك وبريدك الجامعي ورقم هاتفك لأغراض التحقق.',
                            'التحقق الطلابي: نستخدم بريد .ac.uk لضمان بيئة آمنة للطلاب فقط.',
                            'مشاركة البيانات: لا نبيع بياناتك الشخصية لمعلنين خارجيين.',
                            'الاحتفاظ: نحتفظ ببياناتك طالما حسابك نشط ولمدة 12 شهراً بعد الحذف للامتثال القانوني.',
                            'الأمان: جميع البيانات مشفرة بتشفير AES 256-bit.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
            [
                'key' => 'refund_policy',
                'sort_order' => 4,
                'translations' => [
                    'en' => [
                        'title' => 'Refund Policy',
                        'subtitle' => 'Guidelines for ad posting fee refunds.',
                        'description' => json_encode([
                            'Ad Visibility: Once an ad is live on the marketplace, the posting fee is considered consumed and non-refundable.',
                            'Technical Issues: If a technical error prevents your ad from appearing, you are eligible for a full refund or a free re-post credit.',
                            'Sold Items: Marking an item as sold does not trigger a partial refund for the remaining ad duration.',
                            'Removal: Ads removed for violating our community guidelines are not eligible for refunds.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'ar' => [
                        'title' => 'سياسة الاسترداد',
                        'subtitle' => 'إرشادات استرداد رسوم نشر الإعلانات.',
                        'description' => json_encode([
                            'ظهور الإعلان: بعد نشر الإعلان، تُعتبر رسوم النشر مستهلكة وغير قابلة للاسترداد.',
                            'مشاكل تقنية: إذا منع خطأ تقني ظهور إعلانك، يحق لك استرداد كامل أو إعادة نشر مجانية.',
                            'المنتجات المباعة: تعليم المنتج كمباع لا يمنح استرداداً جزئياً للمدة المتبقية.',
                            'الإزالة: الإعلانات المزالة لمخالفة الإرشادات غير مؤهلة للاسترداد.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
            [
                'key' => 'cookie_policy',
                'sort_order' => 5,
                'translations' => [
                    'en' => [
                        'title' => 'Cookie Policy',
                        'subtitle' => 'Information about local storage and preferences.',
                        'description' => json_encode([
                            'Purpose: We use local storage (cookies) to keep you logged in and remember your language and search preferences.',
                            'Analytical Data: We collect anonymous usage data to improve the app\'s performance and fix bugs.',
                            'Third Parties: We do not use third-party tracking cookies for targeted advertising.',
                            'Management: You can clear your data at any time via your browser settings, which will log you out of UniTill.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'ar' => [
                        'title' => 'سياسة ملفات تعريف الارتباط',
                        'subtitle' => 'معلومات عن التخزين المحلي والتفضيلات.',
                        'description' => json_encode([
                            'الغرض: نستخدم التخزين المحلي للبقاء مسجلاً وتذكر اللغة وتفضيلات البحث.',
                            'بيانات تحليلية: نجمع بيانات استخدام مجهولة لتحسين الأداء وإصلاح الأخطاء.',
                            'أطراف ثالثة: لا نستخدم ملفات تتبع خارجية للإعلانات المستهدفة.',
                            'الإدارة: يمكنك مسح بياناتك من إعدادات المتصفح، مما يسجّل خروجك من UniTill.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ];

        foreach ($policies as $policy) {
            $row = LegalAffair::query()->updateOrCreate(
                ['key' => $policy['key']],
                [
                    'section' => 'policies',
                    'sort_order' => $policy['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($policy['translations'] as $code => $translation) {
                $language = $languages->get($code);
                if (! $language) {
                    continue;
                }

                $row->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    [
                        'title' => $translation['title'],
                        'subtitle' => $translation['subtitle'],
                        'description' => $translation['description'],
                    ]
                );
            }
        }
    }
}
