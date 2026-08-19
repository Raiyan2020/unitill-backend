<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegalAffairSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate existing data and translations safely
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('legal_affair_translations')->truncate();
        LegalAffair::query()->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $languages = Language::query()->active()->get(['id', 'code'])->keyBy('code');

        if ($languages->isEmpty()) {
            return;
        }

        $allRecords = [
            // --- File 1: General Policies ---
            [
                ['key' => 'general_policies', 'section' => 'policies', 'sort_order' => 1],
                [
                    'en' => [
                        'title' => 'General Policies', 
                        'subtitle' => 'Community guidelines, terms of service, privacy, refund, and cookie policies.', 
                        'description' => json_encode([
                            'Community Guidelines: Respect, honesty, fair pricing, and safety first within the UniTill community.',
                            'Terms of Service: Eligibility requirements, prohibited items, and platform transaction rules.',
                            'Privacy Policy: Collection, protection, and encryption of student identity data.',
                            'Refund Policy: Guidelines for ad posting fee refunds and technical error compensations.',
                            'Cookie Policy: Use of local storage, preferences, and anonymous analytical data.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'ar' => [
                        'title' => 'السياسات العامة', 
                        'subtitle' => 'إرشادات المجتمع، شروط الخدمة، الخصوصية، الاسترداد، وسياسة ملفات الارتباط.', 
                        'description' => json_encode([
                            'إرشادات المجتمع: الاحترام والصدق والتسعير العادل والسلامة أولاً داخل مجتمع UniTill.',
                            'شروط الخدمة: متطلبات الأهلية، المواد المحظورة، وقواعد المعاملات على المنصة.',
                            'سياسة الخصوصية: جمع وحماية وتشفير بيانات هويتك الطلابية.',
                            'سياسة الاسترداد: إرشادات استرداد رسوم نشر الإعلانات وتعويضات الأخطاء التقنية.',
                            'سياسة ملفات تعريف الارتباط: استخدام التخزين المحلي والتفضيلات والبيانات التحليلية.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'fr' => [
                        'title' => 'Politiques générales', 
                        'subtitle' => 'Directives communautaires, conditions d\'utilisation, confidentialité et cookies.', 
                        'description' => json_encode([
                            'Directives communautaires : Respect, honnêteté et sécurité au sein de la communauté.',
                            'Conditions d\'utilisation : Conditions d\'éligibilité et règles de transaction.',
                            'Politique de confidentialité : Collecte et protection des données d\'identité étudiante.',
                            'Politique de remboursement : Lignes directrices pour le remboursement des frais.',
                            'Politique relative aux cookies : Utilisation du stockage local et des préférences.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'es' => [
                        'title' => 'Políticas generales', 
                        'subtitle' => 'Directrices comunitarias, términos de servicio, privacidad y cookies.', 
                        'description' => json_encode([
                            'Directrices de la comunidad: Respeto, honestidad y seguridad dentro de la comunidad.',
                            'Términos de servicio: Requisitos de elegibilidad y reglas de transacción.',
                            'Política de privacidad: Recopilación y protección de datos de identidad estudiantil.',
                            'Política de reembolso: Pautas para el reembolso de tarifas de publicación.',
                            'Política de cookies: Uso de almacenamiento local y preferencias de búsqueda.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'de' => [
                        'title' => 'Allgemeine Richtlinien', 
                        'subtitle' => 'Community-Richtlinien, Nutzungsbedingungen, Datenschutz und Cookies.', 
                        'description' => json_encode([
                            'Community-Richtlinien: Respekt, Ehrlichkeit und Sicherheit in der Gemeinschaft.',
                            'Nutzungsbedingungen: Berechtigungsanforderungen und Transaktionsregeln.',
                            'Datenschutzrichtlinie: Erhebung und Schutz studentischer Identitätsdaten.',
                            'Rückerstattungsrichtlinie: Richtlinien für die Rückerstattung von Gebühren.',
                            'Cookie-Richtlinie: Verwendung von lokalem Speicher und Präferenzen.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                ],
            ],

            // --- File 2: Conduct & Intellectual Property ---
            [
                ['key' => 'conduct_and_ip', 'section' => 'policies', 'sort_order' => 2],
                [
                    'en' => [
                        'title' => 'Conduct & Intellectual Property', 
                        'subtitle' => 'Student code of conduct, IP guidelines, safety, and liability.', 
                        'description' => json_encode([
                            'Student Code of Conduct: Standards of behavior and academic integrity.',
                            'Intellectual Property Policy: Copyrights, trademarks, and user content ownership.',
                            'Safety & Trust Guidelines: Best practices for safe student transactions.',
                            'Disclaimer of Liability: Limitations of legal responsibility for platform transactions.',
                            'Account Suspension Policy: Conditions for disabling or banning profiles.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'ar' => [
                        'title' => 'السلوك والملكية الفكرية', 
                        'subtitle' => 'مدونة سلوك الطالب، سياسة الملكية الفكرية، الأمان، وإخلاء المسؤولية.', 
                        'description' => json_encode([
                            'مدونة سلوك الطالب: معايير السلوك والنزاهة الأكاديمية.',
                            'سياسة الملكية الفكرية: حقوق النشر، العلامات التجارية، وملكية محتوى المستخدم.',
                            'إرشادات الأمان والثقة: أفضل الممارسات للمعاملات الطلابية الآمنة.',
                            'إخلاء المسؤولية القانونية: حدود المسؤولية القانونية لمعاملات المنصة.',
                            'سياسة تعليق الحسابات: الشروط والإجراءات الخاصة بتعطيل أو حظر الملفات.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'fr' => [
                        'title' => 'Conduite et propriété intellectuelle', 
                        'subtitle' => 'Code de conduite, propriété intellectuelle, sécurité et responsabilité.', 
                        'description' => json_encode([
                            'Code de conduite des étudiants : Normes de comportement et intégrité.',
                            'Politique de propriété intellectuelle : Droits d\'auteur et contenu utilisateur.',
                            'Directrices de sécurité : Meilleures pratiques pour des transactions sécurisées.',
                            'Exclusion de responsabilité : Limites de la responsabilité légale.',
                            'Politique de suspension : Conditions de désactivation des profils.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'es' => [
                        'title' => 'Conducta y propiedad intelectual', 
                        'subtitle' => 'Código de conducta, propiedad intelectual, seguridad y descargo.', 
                        'description' => json_encode([
                            'Código de conducta estudiantil: Estándares de comportamiento e integridad.',
                            'Política de propiedad intelectual: Derechos de autor y contenido de usuario.',
                            'Directrices de seguridad y confianza: Mejores prácticas para transacciones.',
                            'Descargo de responsabilidad: Limitaciones de responsabilidad legal.',
                            'Política de suspensión: Condiciones para deshabilitar perfiles.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'de' => [
                        'title' => 'Verhalten und geistiges Eigentum', 
                        'subtitle' => 'Verhaltenskodex, geistiges Eigentum, Sicherheit und Haftung.', 
                        'description' => json_encode([
                            'Student Verhaltenskodex: Verhaltensstandards und akademische Integrität.',
                            'Richtlinie zum geistigen Eigentum: Urheberrecht und Benutzerinhalte.',
                            'Sicherheits- und Vertrauensrichtlinien: Best Practices für Transaktionen.',
                            'Haftungsausschluss: Einschränkungen der rechtlichen Verantwortung.',
                            'Kontosperrungsrichtlinie: Bedingungen zur Deaktivierung von Profilen.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                ],
            ],

            // --- File 3: Marketplace & Trading Standards ---
            [
                ['key' => 'marketplace_and_trading', 'section' => 'policies', 'sort_order' => 3],
                [
                    'en' => [
                        'title' => 'Marketplace & Trading Standards', 
                        'subtitle' => 'Core rules for trading, escrow, seller verification, and promotions.', 
                        'description' => json_encode([
                            'Marketplace Trading Standards: Core rules for buying and selling within campus.',
                            'Escrow & Payment Terms: Financial protocols for handling online transactions.',
                            'Seller Verification Policy: Requirements for validating merchant profiles.',
                            'Content Moderation Guidelines: Protocols for reviewing reports and violations.',
                            'Promotional Ads Policy: Rules for boosting listings and sponsored placements.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'ar' => [
                        'title' => 'معايير التداول في السوق', 
                        'subtitle' => 'قواعد التداول، شروط الدفع، التحقق من البائع، والإشراف والترويج.', 
                        'description' => json_encode([
                            'معايير التداول في السوق: القواعد الأساسية للبيع والشراء داخل الحرم الجامعي.',
                            'شروط الدفع والضمان: البروتوكولات المالية للمعاملات عبر الإنترنت.',
                            'سياسة التحقق من البائع: متطلبات التحقق من ملفات التجار والبائعين.',
                            'إرشادات الإشراف على المحتوى: البروتوكولات المستخدمة لمراجعة البلاغات والمخالفات.',
                            'سياسة الإعلانات الترويجية: قواعد ترويج الإعلانات والمواضع الممولة.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'fr' => [
                        'title' => 'Normes de commerce du marché', 
                        'subtitle' => 'Règles de négoce, séquestre, vérification et modération.', 
                        'description' => json_encode([
                            'Normes de commerce : Règles fondamentales pour l\'achat et la vente.',
                            'Conditions de paiement : Protocoles financiers pour les transactions.',
                            'Politique de vérification : Exigences pour valider les profils marchands.',
                            'Lignes directrices de modération : Protocoles d\'examen des rapports.',
                            'Politique des annonces : Règles pour booster les annonces et sponsorisations.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'es' => [
                        'title' => 'Estándares comerciales del mercado', 
                        'subtitle' => 'Reglas de comercio, depósito, verificación y moderación.', 
                        'description' => json_encode([
                            'Estándares comerciales: Reglas principales para la compraventa.',
                            'Términos de pago y depósito: Protocolos financieros para transacciones.',
                            'Política de verificación: Requisitos para validar perfiles de comerciantes.',
                            'Directrices de moderación: Protocolos para revisar reportes.',
                            'Política de anuncios promocionales: Reglas para destacar anuncios.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'de' => [
                        'title' => 'Handelsstandards für den Marktplatz', 
                        'subtitle' => 'Handelsregeln, Treuhand, Verifizierung und Moderation.', 
                        'description' => json_encode([
                            'Handelsstandards: Grundregeln für den Kauf und Verkauf auf dem Campus.',
                            'Treuhand- und Zahlungsbedingungen: Finanzprotokolle für Online-Transaktionen.',
                            'Verkäuferverifizierungsrichtlinie: Anforderungen zur Validierung von Händlern.',
                            'Inhaltsmoderationsrichtlinien: Protokolle zur Überprüfung von Meldungen.',
                            'Werbeanzeigenrichtlinie: Regeln für das Pushen von Angeboten.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                ],
            ],

            // --- File 4: Data Governance & Compliance ---
            [
                ['key' => 'data_governance_and_compliance', 'section' => 'policies', 'sort_order' => 4],
                [
                    'en' => [
                        'title' => 'Data Governance & Compliance', 
                        'subtitle' => 'Data retention, APIs, anti-harassment, DMCA, and account deletion.', 
                        'description' => json_encode([
                            'Data Retention Policy: Lifespan of user records, chat logs, and audits.',
                            'Third-Party API Policy: Guidelines for external integrations and SDKs.',
                            'Anti-Harassment Policy: Zero-tolerance rules regarding offensive messaging.',
                            'Copyright & DMCA Policy: Procedures for IP takedown notifications.',
                            'Account Deletion Policy: Instructions and data removal guidelines.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'ar' => [
                        'title' => 'حوكمة البيانات والامتثال', 
                        'subtitle' => 'الاحتفاظ بالبيانات، واجهات البرمجة، مكافحة المضايقة، DMCA، والحذف.', 
                        'description' => json_encode([
                            'سياسة الاحتفاظ بالبيانات: المدة الزمنية لسجلات المستخدمين والمحادثات.',
                            'سياسة واجهات برمجة التطبيقات: إرشادات التكامل الخارجي وأدوات الخرائط.',
                            'سياسة مكافحة المضايقة: قواعد عدم التسامح مع الرسائل المسيئة والترهيب.',
                            'سياسة حقوق النشر و DMCA: إجراءات تقديم إشعارات إزالة الملكية الفكرية.',
                            'سياسة حذف الحسابات: التعليمات وإرشادات إزالة البيانات لإغلاق الملف.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'fr' => [
                        'title' => 'Gouvernance des données et conformité', 
                        'subtitle' => 'Conservation des données, API, anti-harcèlement et DMCA.', 
                        'description' => json_encode([
                            'Politique de conservation : Durée de conservation des dossiers.',
                            'Politique des API tierces : Lignes directrices concernant les intégrations.',
                            'Politique anti-harcèlement : Règles de tolérance zéro.',
                            'Politique de copyright et DMCA : Procédures de notification de retrait.',
                            'Politique de suppression de compte : Instructions de suppression.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'es' => [
                        'title' => 'Gobernanza de datos y cumplimiento', 
                        'subtitle' => 'Retención de datos, API, acoso, DMCA y eliminación de cuentas.', 
                        'description' => json_encode([
                            'Política de retención de datos: Duración de registros de usuario y chat.',
                            'Política de API de terceros: Directrices sobre integraciones externas.',
                            'Política contra el acoso: Reglas de tolerancia cero sobre mensajes.',
                            'Política de derechos de autor y DMCA: Procedimientos de retirada.',
                            'Política de eliminación de cuentas: Instrucciones y directrices.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'de' => [
                        'title' => 'Daten-Governance und Compliance', 
                        'subtitle' => 'Datenspeicherung, APIs, Anti-Harassment, DMCA und Konto-Löschung.', 
                        'description' => json_encode([
                            'Aufbewahrungsrichtlinie für Daten: Lebensdauer von Datensätzen.',
                            'Drittanbieter-API-Richtlinie: Richtlinien zu externen Integrationen.',
                            'Anti-Belästigungs-Richtlinie: Null-Toleranz-Regeln bei Nachrichten.',
                            'Urheberrechts- und DMCA-Richtlinie: Verfahren zur Einreichung.',
                            'Konto-Löschungsrichtlinie: Anweisungen und Datenentfernung.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                ],
            ],

            // --- File 5: Operations, Security & Miscellaneous ---
            [
                ['key' => 'operations_and_security', 'section' => 'policies', 'sort_order' => 5],
                [
                    'en' => [
                        'title' => 'Operations, Security & Miscellaneous', 
                        'subtitle' => 'System maintenance, geo-fencing, disputes, age restrictions, and eco-initiatives.', 
                        'description' => json_encode([
                            'System Maintenance Policy: Scheduled server downtime and software upgrades.',
                            'Geo-Fencing Policy: Geographic restrictions and campus localization.',
                            'Dispute Resolution Policy: Framework for mediating buyer-seller conflicts.',
                            'Age Restriction Policy: Minimum age requirements and verification.',
                            'Eco-Friendly Initiative Policy: Guidelines promoting sustainable recycling.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'ar' => [
                        'title' => 'العمليات والأمان ومتفرقات', 
                        'subtitle' => 'صيانة النظام، السياج الجغرافي، المنازعات، قيود العمر، والمبادرات البيئية.', 
                        'description' => json_encode([
                            'سياسة صيانة النظام: البروتوكولات المتعلقة بوقت توقف الخادم وترقيات البرمجيات.',
                            'سياسة السياج الجغرافي: القيود الجغرافية ومتطلبات توطين الحرم الجامعي.',
                            'سياسة تسوية النزاعات: إطار خطوة بخطوة للوساطة بين المشترين والبائعين.',
                            'سياسة تقييد العمر: متطلبات الحد الأدنى للسن والتحقق من الهوية.',
                            'سياسة مبادرة الحفاظ على البيئة: الإرشادات لتعزيز إعادة التدوير المستدام.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'fr' => [
                        'title' => 'Opérations, sécurité et divers', 
                        'subtitle' => 'Maintenance, géorepérage, litiges, restrictions d\'âge et écologie.', 
                        'description' => json_encode([
                            'Politique de maintenance : Temps d\'arrêt programmés et mises à jour.',
                            'Politique de géorepérage : Restrictions géographiques et campus.',
                            'Politique de résolution des litiges : Cadre de médiation des conflits.',
                            'Politique de restriction d\'âge : Exigences d\'âge minimum et vérification.',
                            'Politique d\'initiative écologique : Promotion du recyclage durable.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'es' => [
                        'title' => 'Operaciones, seguridad y varios', 
                        'subtitle' => 'Mantenimiento, geocercas, disputas, edad e iniciativas ecológicas.', 
                        'description' => json_encode([
                            'Política de mantenimiento del sistema: Tiempo de inactividad programado.',
                            'Política de geocercas: Restricciones geográficas y de localización.',
                            'Política de resolución de disputas: Marco para mediar conflictos.',
                            'Política de restricción de edad: Requisitos de edad mínima.',
                            'Política de iniciativa ecológica: Directrices de reciclaje sostenible.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                    'de' => [
                        'title' => 'Betrieb, Sicherheit und Sonstiges', 
                        'subtitle' => 'Wartung, Geo-Fencing, Streitigkeiten, Alter und Umweltinitiativen.', 
                        'description' => json_encode([
                            'Systemwartungsrichtlinie: Geplante Serverausfallzeiten und Updates.',
                            'Geo-Fencing-Richtlinie: Geografische Beschränkungen und Campus.',
                            'Richtlinie zur Streitbeilegung: Framework zur Konfliktvermittlung.',
                            'Altersbeschränkungsrichtlinie: Mindestalter und Identitätsprüfung.',
                            'Umweltinitiativen-Richtlinie: Förderung nachhaltigen Recyclings.'
                        ], JSON_UNESCAPED_UNICODE)
                    ],
                ],
            ],
        ];

        foreach ($allRecords as $recordData) {
            [$attributes, $translationsMap] = $recordData;

            $row = LegalAffair::query()->updateOrCreate(
                ['key' => $attributes['key']],
                [
                    'section' => $attributes['section'],
                    'sort_order' => $attributes['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($translationsMap as $code => $translation) {
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