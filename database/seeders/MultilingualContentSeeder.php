<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\ContactReason;
use App\Models\ContactReasonTranslation;
use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Adds the fr / es / zh translations to content that already exists.
 *
 * Production safety rule for this whole file: it only ever INSERTS missing
 * translation rows and fills columns that are still NULL. Nothing existing is
 * updated or deleted, so admin edits to English/Arabic names, options or policy
 * text survive every run. Rows are matched by their English text (categories,
 * contact reasons), by slug (attributes) or by key (legal affairs).
 */
class MultilingualContentSeeder extends Seeder
{
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        $this->seedCategories($langs);
        $this->seedAttributes($langs);
        $this->seedContactReasons($langs);
        $this->seedLegalAffairs($langs);
    }

    // ---------------------------------------------------------------- categories

    private function seedCategories(Collection $langs): void
    {
        foreach (self::CATEGORY_NAMES as $english => $names) {
            $categoryIds = CategoryTranslation::query()
                ->where('name', $english)
                ->pluck('category_id')
                ->all();

            foreach ($categoryIds as $categoryId) {
                foreach ($names as $code => $name) {
                    $lang = $langs->get($code);
                    if (! $lang) {
                        continue;
                    }

                    CategoryTranslation::firstOrCreate(
                        ['category_id' => $categoryId, 'language_id' => $lang->id],
                        ['name' => $name]
                    );
                }
            }
        }
    }

    // ---------------------------------------------------------------- attributes

    private function seedAttributes(Collection $langs): void
    {
        $definitions = CategoryAttributeDefinition::query()->with('translations')->get();

        foreach ($definitions as $definition) {
            $labels = $this->labelsFor($definition);
            $values = collect($definition->options ?? [])
                ->map(fn ($option) => is_array($option) ? (string) ($option['value'] ?? '') : (string) $option)
                ->filter()
                ->values()
                ->all();

            foreach (['en', 'ar', 'fr', 'es', 'zh'] as $code) {
                $lang = $langs->get($code);
                if (! $lang) {
                    continue;
                }

                $optionMap = $this->optionMap($values, $code);
                $existing = $definition->translations->firstWhere('language_id', $lang->id);

                if (! $existing) {
                    // A brand new language row: only worth creating if we have a
                    // label for it, otherwise the fallback chain covers it.
                    if (! isset($labels[$code])) {
                        continue;
                    }

                    CategoryAttributeDefinitionTranslation::create([
                        'category_attribute_definition_id' => $definition->id,
                        'language_id' => $lang->id,
                        'label' => $labels[$code],
                        'options' => $optionMap,
                    ]);

                    continue;
                }

                // Existing row: fill the new options column only if still unset.
                if ($existing->options === null && $optionMap !== null) {
                    $existing->update(['options' => $optionMap]);
                }
            }
        }
    }

    /** @return array<string, string> */
    private function labelsFor(CategoryAttributeDefinition $definition): array
    {
        $category = Category::find($definition->category_id);
        $englishName = $category
            ? CategoryTranslation::query()
                ->where('category_id', $category->id)
                ->whereHas('language', fn ($q) => $q->where('code', 'en'))
                ->value('name')
            : null;

        return self::ATTRIBUTE_LABELS[$englishName.'.'.$definition->slug]
            ?? self::ATTRIBUTE_LABELS[$definition->slug]
            ?? [];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>|null
     */
    private function optionMap(array $values, string $code): ?array
    {
        if ($values === []) {
            return null;
        }

        $map = [];
        foreach ($values as $value) {
            $label = $code === 'en' ? $value : (self::OPTION_LABELS[$value][$code] ?? null);
            if ($label !== null) {
                $map[$value] = $label;
            }
        }

        return $map ?: null;
    }

    // ----------------------------------------------------------- contact reasons

    private function seedContactReasons(Collection $langs): void
    {
        foreach (self::CONTACT_REASONS as $english => $names) {
            $reasonIds = ContactReasonTranslation::query()
                ->where('name', $english)
                ->pluck('contact_reason_id')
                ->all();

            foreach ($reasonIds as $reasonId) {
                if (! ContactReason::whereKey($reasonId)->exists()) {
                    continue;
                }

                foreach ($names as $code => $name) {
                    $lang = $langs->get($code);
                    if (! $lang) {
                        continue;
                    }

                    ContactReasonTranslation::firstOrCreate(
                        ['contact_reason_id' => $reasonId, 'language_id' => $lang->id],
                        ['name' => $name]
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------ legal affairs

    private function seedLegalAffairs(Collection $langs): void
    {
        foreach (self::LEGAL_AFFAIRS as $key => $translations) {
            $affair = LegalAffair::query()->where('key', $key)->first();
            if (! $affair) {
                continue;
            }

            foreach ($translations as $code => $translation) {
                $lang = $langs->get($code);
                if (! $lang) {
                    continue;
                }

                $affair->translations()->firstOrCreate(
                    ['language_id' => $lang->id],
                    [
                        'title' => $translation['title'],
                        'subtitle' => $translation['subtitle'],
                        'description' => json_encode($translation['points'], JSON_UNESCAPED_UNICODE),
                    ]
                );
            }
        }
    }

    // ------------------------------------------------------------------- content

    /** English category name => [locale => name]. */
    private const CATEGORY_NAMES = [
        'Accommodation' => ['fr' => 'Logement', 'es' => 'Alojamiento', 'zh' => '住宿'],
        'Rooms for rent' => ['fr' => 'Chambres à louer', 'es' => 'Habitaciones en alquiler', 'zh' => '出租房间'],
        'Shared flats' => ['fr' => 'Colocations', 'es' => 'Pisos compartidos', 'zh' => '合租公寓'],
        'Student accommodation' => ['fr' => 'Logement étudiant', 'es' => 'Residencia de estudiantes', 'zh' => '学生公寓'],
        'Short-term lets' => ['fr' => 'Locations courte durée', 'es' => 'Alquileres de corta duración', 'zh' => '短期租赁'],
        'Long-term lets' => ['fr' => 'Locations longue durée', 'es' => 'Alquileres de larga duración', 'zh' => '长期租赁'],
        'Cars' => ['fr' => 'Voitures', 'es' => 'Coches', 'zh' => '汽车'],
        'Parts' => ['fr' => 'Pièces détachées', 'es' => 'Repuestos', 'zh' => '零配件'],
        'Furniture & Home' => ['fr' => 'Meubles et maison', 'es' => 'Muebles y hogar', 'zh' => '家具与家居'],
        'Beds & mattresses' => ['fr' => 'Lits et matelas', 'es' => 'Camas y colchones', 'zh' => '床与床垫'],
        'Desks & chairs' => ['fr' => 'Bureaux et chaises', 'es' => 'Escritorios y sillas', 'zh' => '书桌与椅子'],
        'Sofas & storage' => ['fr' => 'Canapés et rangement', 'es' => 'Sofás y almacenaje', 'zh' => '沙发与收纳'],
        'Kitchen items' => ['fr' => 'Articles de cuisine', 'es' => 'Artículos de cocina', 'zh' => '厨房用品'],
        'Electronics' => ['fr' => 'Électronique', 'es' => 'Electrónica', 'zh' => '电子产品'],
        'Laptops' => ['fr' => 'Ordinateurs portables', 'es' => 'Portátiles', 'zh' => '笔记本电脑'],
        'Mobile phones' => ['fr' => 'Téléphones mobiles', 'es' => 'Teléfonos móviles', 'zh' => '手机'],
        'Tablets' => ['fr' => 'Tablettes', 'es' => 'Tabletas', 'zh' => '平板电脑'],
        'Accessories' => ['fr' => 'Accessoires', 'es' => 'Accesorios', 'zh' => '配件'],
        'Appliances' => ['fr' => 'Électroménager', 'es' => 'Electrodomésticos', 'zh' => '家用电器'],
        'Fridges' => ['fr' => 'Réfrigérateurs', 'es' => 'Neveras', 'zh' => '冰箱'],
        'Washing machines' => ['fr' => 'Lave-linge', 'es' => 'Lavadoras', 'zh' => '洗衣机'],
        'Microwaves' => ['fr' => 'Micro-ondes', 'es' => 'Microondas', 'zh' => '微波炉'],
        'Heaters & fans' => ['fr' => 'Chauffages et ventilateurs', 'es' => 'Calefactores y ventiladores', 'zh' => '取暖器与风扇'],
        'Bikes' => ['fr' => 'Vélos', 'es' => 'Bicicletas', 'zh' => '自行车'],
        'Bicycles' => ['fr' => 'Vélos', 'es' => 'Bicicletas', 'zh' => '自行车'],
        'Electric bikes' => ['fr' => 'Vélos électriques', 'es' => 'Bicicletas eléctricas', 'zh' => '电动自行车'],
        'Motorbikes' => ['fr' => 'Motos', 'es' => 'Motos', 'zh' => '摩托车'],
        'Scooters' => ['fr' => 'Scooters', 'es' => 'Scooters', 'zh' => '踏板车'],
        'Books & Study Materials' => ['fr' => 'Livres et fournitures d\'études', 'es' => 'Libros y material de estudio', 'zh' => '书籍与学习资料'],
        'Textbooks' => ['fr' => 'Manuels scolaires', 'es' => 'Libros de texto', 'zh' => '教材'],
        'Notes & revision materials' => ['fr' => 'Notes et fiches de révision', 'es' => 'Apuntes y material de repaso', 'zh' => '笔记与复习资料'],
        'Stationery' => ['fr' => 'Papeterie', 'es' => 'Papelería', 'zh' => '文具'],
        'Fashion & Personal Items' => ['fr' => 'Mode et effets personnels', 'es' => 'Moda y objetos personales', 'zh' => '时尚与个人物品'],
        'Clothing' => ['fr' => 'Vêtements', 'es' => 'Ropa', 'zh' => '服装'],
        'Shoes' => ['fr' => 'Chaussures', 'es' => 'Zapatos', 'zh' => '鞋履'],
        'Bags & accessories' => ['fr' => 'Sacs et accessoires', 'es' => 'Bolsos y accesorios', 'zh' => '包袋与配饰'],
        'Services' => ['fr' => 'Services', 'es' => 'Servicios', 'zh' => '服务'],
        'Tutoring' => ['fr' => 'Cours particuliers', 'es' => 'Clases particulares', 'zh' => '辅导'],
        'Moving help' => ['fr' => 'Aide au déménagement', 'es' => 'Ayuda con mudanzas', 'zh' => '搬家帮助'],
        'Cleaning' => ['fr' => 'Ménage', 'es' => 'Limpieza', 'zh' => '清洁'],
        'IT / tech help' => ['fr' => 'Aide informatique', 'es' => 'Ayuda informática', 'zh' => '电脑技术支持'],
        'Freelance / student services' => ['fr' => 'Services freelance / étudiants', 'es' => 'Servicios freelance / estudiantiles', 'zh' => '自由职业 / 学生服务'],
        'Others' => ['fr' => 'Autres', 'es' => 'Otros', 'zh' => '其他'],
    ];

    /**
     * Attribute label per locale. Keyed by slug, or by "English category.slug"
     * where the same slug is labelled differently per category.
     */
    private const ATTRIBUTE_LABELS = [
        'condition' => ['fr' => 'État', 'es' => 'Estado', 'zh' => '成色'],
        'collection_delivery' => ['fr' => 'Retrait ou livraison', 'es' => 'Recogida o entrega', 'zh' => '自取或配送'],
        'contract_type' => ['fr' => 'Type de contrat', 'es' => 'Tipo de contrato', 'zh' => '合同类型'],
        'property_type' => ['fr' => 'Type de bien', 'es' => 'Tipo de propiedad', 'zh' => '房产类型'],
        'bedrooms' => ['fr' => 'Chambres', 'es' => 'Dormitorios', 'zh' => '卧室数'],
        'bathrooms' => ['fr' => 'Salles de bain', 'es' => 'Baños', 'zh' => '卫生间数'],
        'payment_term' => ['fr' => 'Périodicité de paiement', 'es' => 'Periodo de pago', 'zh' => '付款周期'],
        'furnishing' => ['fr' => 'Ameublement', 'es' => 'Amueblado', 'zh' => '家具情况'],
        'bills_included' => ['fr' => 'Charges comprises', 'es' => 'Facturas incluidas', 'zh' => '含账单'],
        'availability_from' => ['fr' => 'Disponible à partir du', 'es' => 'Disponible desde', 'zh' => '可入住日期'],
        'features' => ['fr' => 'Équipements', 'es' => 'Características', 'zh' => '设施'],
        'listing_type' => ['fr' => 'Type d\'annonce', 'es' => 'Tipo de anuncio', 'zh' => '广告类型'],
        'make' => ['fr' => 'Marque', 'es' => 'Marca', 'zh' => '品牌'],
        'model' => ['fr' => 'Modèle', 'es' => 'Modelo', 'zh' => '型号'],
        'year' => ['fr' => 'Année', 'es' => 'Año', 'zh' => '年份'],
        'mileage' => ['fr' => 'Kilométrage', 'es' => 'Kilometraje', 'zh' => '里程'],
        'fuel_type' => ['fr' => 'Carburant', 'es' => 'Combustible', 'zh' => '燃料类型'],
        'transmission' => ['fr' => 'Boîte de vitesses', 'es' => 'Transmisión', 'zh' => '变速箱'],
        'body_type' => ['fr' => 'Type de carrosserie', 'es' => 'Tipo de carrocería', 'zh' => '车身类型'],
        'engine_size' => ['fr' => 'Cylindrée', 'es' => 'Cilindrada', 'zh' => '排量'],
        'seats' => ['fr' => 'Places', 'es' => 'Plazas', 'zh' => '座位数'],
        'colour' => ['fr' => 'Couleur', 'es' => 'Color', 'zh' => '颜色'],
        'brand' => ['fr' => 'Marque', 'es' => 'Marca', 'zh' => '品牌'],
        'storage' => ['fr' => 'Stockage', 'es' => 'Almacenamiento', 'zh' => '存储容量'],
        'warranty' => ['fr' => 'Garantie', 'es' => 'Garantía', 'zh' => '保修'],
        'appliance_type' => ['fr' => 'Type d\'appareil', 'es' => 'Tipo de electrodoméstico', 'zh' => '电器类型'],
        'bike_type' => ['fr' => 'Type', 'es' => 'Tipo', 'zh' => '类型'],
        'electric' => ['fr' => 'Électrique', 'es' => 'Eléctrica', 'zh' => '电动'],
        'book_type' => ['fr' => 'Type', 'es' => 'Tipo', 'zh' => '类型'],
        'subject' => ['fr' => 'Matière ou cours', 'es' => 'Asignatura o curso', 'zh' => '科目或课程'],
        'format' => ['fr' => 'Format', 'es' => 'Formato', 'zh' => '格式'],
        'gender' => ['fr' => 'Genre', 'es' => 'Género', 'zh' => '性别'],
        'size' => ['fr' => 'Taille', 'es' => 'Talla', 'zh' => '尺码'],
        'service_type' => ['fr' => 'Type de service', 'es' => 'Tipo de servicio', 'zh' => '服务类型'],
        'delivery_mode' => ['fr' => 'Mode de prestation', 'es' => 'Modalidad', 'zh' => '提供方式'],
        'price_type' => ['fr' => 'Type de tarif', 'es' => 'Tipo de precio', 'zh' => '价格类型'],
        'availability' => ['fr' => 'Disponibilité', 'es' => 'Disponibilidad', 'zh' => '可用时间'],
        'item_type' => ['fr' => 'Type d\'article', 'es' => 'Tipo de artículo', 'zh' => '物品类型'],
        'Fashion & Personal Items.item_type' => ['fr' => 'Type', 'es' => 'Tipo', 'zh' => '类型'],
    ];

    /**
     * English option value => [locale => label], keyed once and shared across
     * categories. A value missing here keeps its English text, which is correct
     * for brand names, sizes and numbers.
     */
    private const OPTION_LABELS = [
        'Other' => ['ar' => 'أخرى', 'fr' => 'Autre', 'es' => 'Otro', 'zh' => '其他'],
        'Others' => ['ar' => 'أخرى', 'fr' => 'Autres', 'es' => 'Otros', 'zh' => '其他'],
        'Both' => ['ar' => 'كلاهما', 'fr' => 'Les deux', 'es' => 'Ambos', 'zh' => '两者皆可'],

        'New' => ['ar' => 'جديد', 'fr' => 'Neuf', 'es' => 'Nuevo', 'zh' => '全新'],
        'Like new' => ['ar' => 'شبه جديد', 'fr' => 'Comme neuf', 'es' => 'Como nuevo', 'zh' => '几乎全新'],
        'Refurbished' => ['ar' => 'مُجدَّد', 'fr' => 'Reconditionné', 'es' => 'Reacondicionado', 'zh' => '翻新'],
        'Used' => ['ar' => 'مستعمل', 'fr' => 'Occasion', 'es' => 'Usado', 'zh' => '二手'],
        'Excellent' => ['ar' => 'ممتازة', 'fr' => 'Excellent', 'es' => 'Excelente', 'zh' => '极好'],
        'Very good' => ['ar' => 'جيدة جداً', 'fr' => 'Très bon', 'es' => 'Muy bueno', 'zh' => '很好'],
        'Good' => ['ar' => 'جيدة', 'fr' => 'Bon', 'es' => 'Bueno', 'zh' => '良好'],

        'Collection' => ['ar' => 'استلام', 'fr' => 'Retrait', 'es' => 'Recogida', 'zh' => '自取'],
        'Delivery' => ['ar' => 'توصيل', 'fr' => 'Livraison', 'es' => 'Entrega', 'zh' => '配送'],

        'Short-term' => ['ar' => 'قصير المدى', 'fr' => 'Courte durée', 'es' => 'Corta duración', 'zh' => '短期'],
        'Long-term' => ['ar' => 'طويل المدى', 'fr' => 'Longue durée', 'es' => 'Larga duración', 'zh' => '长期'],
        'Flat' => ['ar' => 'شقة', 'fr' => 'Appartement', 'es' => 'Piso', 'zh' => '公寓'],
        'House' => ['ar' => 'منزل', 'fr' => 'Maison', 'es' => 'Casa', 'zh' => '独栋房屋'],
        'Studio' => ['ar' => 'استوديو', 'fr' => 'Studio', 'es' => 'Estudio', 'zh' => '单间公寓'],
        'Student accommodation' => ['ar' => 'سكن طلابي', 'fr' => 'Logement étudiant', 'es' => 'Residencia de estudiantes', 'zh' => '学生公寓'],
        'Shared room' => ['ar' => 'غرفة مشتركة', 'fr' => 'Chambre partagée', 'es' => 'Habitación compartida', 'zh' => '合住房间'],
        'Per week' => ['ar' => 'أسبوعياً', 'fr' => 'Par semaine', 'es' => 'Por semana', 'zh' => '每周'],
        'Per month' => ['ar' => 'شهرياً', 'fr' => 'Par mois', 'es' => 'Por mes', 'zh' => '每月'],
        'Furnished' => ['ar' => 'مفروش', 'fr' => 'Meublé', 'es' => 'Amueblado', 'zh' => '带家具'],
        'Unfurnished' => ['ar' => 'غير مفروش', 'fr' => 'Non meublé', 'es' => 'Sin amueblar', 'zh' => '无家具'],
        'Part-furnished' => ['ar' => 'مفروش جزئياً', 'fr' => 'Partiellement meublé', 'es' => 'Parcialmente amueblado', 'zh' => '部分家具'],
        'Parking' => ['ar' => 'موقف سيارات', 'fr' => 'Parking', 'es' => 'Aparcamiento', 'zh' => '停车位'],
        'Garden' => ['ar' => 'حديقة', 'fr' => 'Jardin', 'es' => 'Jardín', 'zh' => '花园'],
        'Balcony' => ['ar' => 'شرفة', 'fr' => 'Balcon', 'es' => 'Balcón', 'zh' => '阳台'],

        'Car' => ['ar' => 'سيارة', 'fr' => 'Voiture', 'es' => 'Coche', 'zh' => '汽车'],
        'Parts' => ['ar' => 'قطع غيار', 'fr' => 'Pièces détachées', 'es' => 'Repuestos', 'zh' => '零配件'],
        'Petrol' => ['ar' => 'بنزين', 'fr' => 'Essence', 'es' => 'Gasolina', 'zh' => '汽油'],
        'Diesel' => ['ar' => 'ديزل', 'fr' => 'Diesel', 'es' => 'Diésel', 'zh' => '柴油'],
        'Hybrid' => ['ar' => 'هجينة', 'fr' => 'Hybride', 'es' => 'Híbrido', 'zh' => '混合动力'],
        'Electric' => ['ar' => 'كهربائية', 'fr' => 'Électrique', 'es' => 'Eléctrico', 'zh' => '电动'],
        'Manual' => ['ar' => 'يدوي', 'fr' => 'Manuelle', 'es' => 'Manual', 'zh' => '手动'],
        'Automatic' => ['ar' => 'أوتوماتيك', 'fr' => 'Automatique', 'es' => 'Automático', 'zh' => '自动'],
        'Hatchback' => ['ar' => 'هاتشباك', 'fr' => 'Citadine', 'es' => 'Utilitario', 'zh' => '两厢车'],
        'Saloon' => ['ar' => 'سيدان', 'fr' => 'Berline', 'es' => 'Sedán', 'zh' => '三厢轿车'],
        'SUV' => ['ar' => 'دفع رباعي', 'fr' => 'SUV', 'es' => 'SUV', 'zh' => 'SUV'],
        'Estate' => ['ar' => 'ستيشن', 'fr' => 'Break', 'es' => 'Familiar', 'zh' => '旅行车'],
        'Coupe' => ['ar' => 'كوبيه', 'fr' => 'Coupé', 'es' => 'Cupé', 'zh' => '轿跑'],
        'Convertible' => ['ar' => 'مكشوفة', 'fr' => 'Cabriolet', 'es' => 'Descapotable', 'zh' => '敞篷车'],
        'Pickup' => ['ar' => 'بيك أب', 'fr' => 'Pick-up', 'es' => 'Pick-up', 'zh' => '皮卡'],
        'Black' => ['ar' => 'أسود', 'fr' => 'Noir', 'es' => 'Negro', 'zh' => '黑色'],
        'White' => ['ar' => 'أبيض', 'fr' => 'Blanc', 'es' => 'Blanco', 'zh' => '白色'],
        'Silver' => ['ar' => 'فضي', 'fr' => 'Argent', 'es' => 'Plata', 'zh' => '银色'],
        'Grey' => ['ar' => 'رمادي', 'fr' => 'Gris', 'es' => 'Gris', 'zh' => '灰色'],
        'Blue' => ['ar' => 'أزرق', 'fr' => 'Bleu', 'es' => 'Azul', 'zh' => '蓝色'],
        'Red' => ['ar' => 'أحمر', 'fr' => 'Rouge', 'es' => 'Rojo', 'zh' => '红色'],
        'Green' => ['ar' => 'أخضر', 'fr' => 'Vert', 'es' => 'Verde', 'zh' => '绿色'],

        'Beds & mattresses' => ['ar' => 'أسِرّة ومراتب', 'fr' => 'Lits et matelas', 'es' => 'Camas y colchones', 'zh' => '床与床垫'],
        'Desks & chairs' => ['ar' => 'مكاتب وكراسي', 'fr' => 'Bureaux et chaises', 'es' => 'Escritorios y sillas', 'zh' => '书桌与椅子'],
        'Sofas & storage' => ['ar' => 'كنب وتخزين', 'fr' => 'Canapés et rangement', 'es' => 'Sofás y almacenaje', 'zh' => '沙发与收纳'],
        'Kitchen items' => ['ar' => 'أدوات المطبخ', 'fr' => 'Articles de cuisine', 'es' => 'Artículos de cocina', 'zh' => '厨房用品'],

        'Laptop' => ['ar' => 'لابتوب', 'fr' => 'Ordinateur portable', 'es' => 'Portátil', 'zh' => '笔记本电脑'],
        'Mobile phone' => ['ar' => 'هاتف محمول', 'fr' => 'Téléphone mobile', 'es' => 'Teléfono móvil', 'zh' => '手机'],
        'Tablet' => ['ar' => 'تابلت', 'fr' => 'Tablette', 'es' => 'Tableta', 'zh' => '平板电脑'],
        'Accessories' => ['ar' => 'إكسسوارات', 'fr' => 'Accessoires', 'es' => 'Accesorios', 'zh' => '配件'],

        'Fridge' => ['ar' => 'ثلاجة', 'fr' => 'Réfrigérateur', 'es' => 'Nevera', 'zh' => '冰箱'],
        'Washing machine' => ['ar' => 'غسالة', 'fr' => 'Lave-linge', 'es' => 'Lavadora', 'zh' => '洗衣机'],
        'Microwave' => ['ar' => 'ميكروويف', 'fr' => 'Micro-ondes', 'es' => 'Microondas', 'zh' => '微波炉'],
        'Heaters & fans' => ['ar' => 'دفايات ومراوح', 'fr' => 'Chauffages et ventilateurs', 'es' => 'Calefactores y ventiladores', 'zh' => '取暖器与风扇'],

        'Bicycle' => ['ar' => 'دراجة هوائية', 'fr' => 'Vélo', 'es' => 'Bicicleta', 'zh' => '自行车'],
        'Electric bike' => ['ar' => 'دراجة كهربائية', 'fr' => 'Vélo électrique', 'es' => 'Bicicleta eléctrica', 'zh' => '电动自行车'],
        'Motorbike' => ['ar' => 'دراجة نارية', 'fr' => 'Moto', 'es' => 'Moto', 'zh' => '摩托车'],
        'Scooter' => ['ar' => 'سكوتر', 'fr' => 'Scooter', 'es' => 'Scooter', 'zh' => '踏板车'],

        'Textbook' => ['ar' => 'كتاب دراسي', 'fr' => 'Manuel scolaire', 'es' => 'Libro de texto', 'zh' => '教材'],
        'Notes & revision materials' => ['ar' => 'ملخصات ومراجعات', 'fr' => 'Notes et fiches de révision', 'es' => 'Apuntes y material de repaso', 'zh' => '笔记与复习资料'],
        'Stationery' => ['ar' => 'أدوات مكتبية', 'fr' => 'Papeterie', 'es' => 'Papelería', 'zh' => '文具'],
        'Physical' => ['ar' => 'نسخة ورقية', 'fr' => 'Papier', 'es' => 'Físico', 'zh' => '纸质'],
        'Digital notes' => ['ar' => 'ملاحظات رقمية', 'fr' => 'Notes numériques', 'es' => 'Apuntes digitales', 'zh' => '电子笔记'],

        'Clothing' => ['ar' => 'ملابس', 'fr' => 'Vêtements', 'es' => 'Ropa', 'zh' => '服装'],
        'Shoes' => ['ar' => 'أحذية', 'fr' => 'Chaussures', 'es' => 'Zapatos', 'zh' => '鞋履'],
        'Bags & accessories' => ['ar' => 'حقائب وإكسسوارات', 'fr' => 'Sacs et accessoires', 'es' => 'Bolsos y accesorios', 'zh' => '包袋与配饰'],
        'Men' => ['ar' => 'رجال', 'fr' => 'Homme', 'es' => 'Hombre', 'zh' => '男士'],
        'Women' => ['ar' => 'نساء', 'fr' => 'Femme', 'es' => 'Mujer', 'zh' => '女士'],
        'Unisex' => ['ar' => 'للجنسين', 'fr' => 'Unisexe', 'es' => 'Unisex', 'zh' => '中性'],

        'Tutoring' => ['ar' => 'دروس خصوصية', 'fr' => 'Cours particuliers', 'es' => 'Clases particulares', 'zh' => '辅导'],
        'Moving help' => ['ar' => 'مساعدة في النقل', 'fr' => 'Aide au déménagement', 'es' => 'Ayuda con mudanzas', 'zh' => '搬家帮助'],
        'Cleaning' => ['ar' => 'تنظيف', 'fr' => 'Ménage', 'es' => 'Limpieza', 'zh' => '清洁'],
        'IT / tech help' => ['ar' => 'دعم تقني', 'fr' => 'Aide informatique', 'es' => 'Ayuda informática', 'zh' => '电脑技术支持'],
        'Freelance' => ['ar' => 'عمل حر', 'fr' => 'Freelance', 'es' => 'Freelance', 'zh' => '自由职业'],
        'In-person' => ['ar' => 'حضورياً', 'fr' => 'En personne', 'es' => 'Presencial', 'zh' => '线下'],
        'Online' => ['ar' => 'عن بُعد', 'fr' => 'En ligne', 'es' => 'En línea', 'zh' => '线上'],
        'Fixed' => ['ar' => 'ثابت', 'fr' => 'Forfait', 'es' => 'Fijo', 'zh' => '固定价'],
        'Hourly' => ['ar' => 'بالساعة', 'fr' => 'Horaire', 'es' => 'Por hora', 'zh' => '按小时'],
        'Weekdays' => ['ar' => 'أيام الأسبوع', 'fr' => 'En semaine', 'es' => 'Entre semana', 'zh' => '工作日'],
        'Weekends' => ['ar' => 'عطلة نهاية الأسبوع', 'fr' => 'Week-ends', 'es' => 'Fines de semana', 'zh' => '周末'],
        'Evenings' => ['ar' => 'المساء', 'fr' => 'Soirées', 'es' => 'Tardes', 'zh' => '晚间'],
    ];

    /** legal_affairs.key => [locale => [title, subtitle, points]]. */
    private const LEGAL_AFFAIRS = [
        'community_guidelines' => [
            'fr' => [
                'title' => 'Règles de la communauté',
                'subtitle' => 'Le comportement attendu au sein de la communauté étudiante UniTill.',
                'points' => [
                    'Respect : traitez chaque étudiant avec bienveillance et respect. Le harcèlement, les propos haineux ou l\'intimidation entraînent un bannissement définitif.',
                    'Honnêteté : soyez sincère sur l\'état des articles. N\'utilisez pas de photos génériques ; publiez toujours de vraies photos de l\'article.',
                    'Prix équitables : nous ne contrôlons pas les prix, mais nous encourageons des tarifs justes et adaptés aux étudiants.',
                    'Pas de spam : ne publiez pas d\'annonces en double et n\'envoyez pas de messages promotionnels non sollicités.',
                    'Communication : gardez les échanges professionnels et liés à l\'annonce concernée.',
                    'La sécurité d\'abord : signalez immédiatement tout comportement suspect ou toute demande de paiement hors plateforme.',
                ],
            ],
            'es' => [
                'title' => 'Normas de la comunidad',
                'subtitle' => 'Comportamiento esperado dentro de la comunidad estudiantil de UniTill.',
                'points' => [
                    'Respeto: trata a cada estudiante con amabilidad y respeto. El acoso, los discursos de odio o el acoso escolar conllevan un bloqueo permanente.',
                    'Honestidad: sé sincero sobre el estado de los artículos. No uses fotos de catálogo; publica siempre imágenes reales del artículo.',
                    'Precios justos: aunque no controlamos los precios, animamos a fijar precios justos y asequibles para estudiantes.',
                    'Sin spam: no publiques anuncios duplicados ni envíes mensajes publicitarios no solicitados.',
                    'Comunicación: mantén las conversaciones profesionales y relacionadas con el anuncio.',
                    'Seguridad primero: informa de inmediato de cualquier conducta sospechosa o de quien pida pagos fuera de la plataforma.',
                ],
            ],
            'zh' => [
                'title' => '社区准则',
                'subtitle' => 'UniTill 学生社区中应遵守的行为规范。',
                'points' => [
                    '尊重：请友善并尊重地对待每一位学生。骚扰、仇恨言论或欺凌行为将被永久封禁。',
                    '诚信：如实描述物品成色。请勿使用网络图片，务必上传实物照片。',
                    '合理定价：我们不干预价格，但鼓励设定公平、适合学生的价格。',
                    '禁止垃圾信息：请勿重复发布广告，也不要向其他用户发送未经请求的营销消息。',
                    '沟通：请保持交流专业，并且与该商品相关。',
                    '安全第一：如发现可疑行为或有人要求在平台外付款，请立即举报。',
                ],
            ],
        ],
        'terms_of_service' => [
            'fr' => [
                'title' => 'Conditions d\'utilisation',
                'subtitle' => 'Règles d\'utilisation de la plateforme UniTill.',
                'points' => [
                    'Éligibilité : vous devez être un étudiant vérifié d\'une université britannique pour publier des annonces. Les non-étudiants peuvent consulter les annonces avec un accès restreint.',
                    'Articles interdits : la vente d\'alcool, de tabac, d\'armes, de substances illégales et de matières dangereuses est strictement interdite.',
                    'Frais : les frais de publication ne sont pas remboursables une fois l\'annonce en ligne. Nous ne prélevons aucune commission sur les transactions entre étudiants.',
                    'Sécurité : rencontrez les acheteurs dans des lieux publics et éclairés du campus. Faites-vous accompagner pour les transactions de valeur.',
                    'Exactitude : chaque annonce doit décrire fidèlement l\'état de l\'article. Les annonces trompeuses sont supprimées.',
                ],
            ],
            'es' => [
                'title' => 'Términos del servicio',
                'subtitle' => 'Reglas para usar la plataforma UniTill.',
                'points' => [
                    'Elegibilidad: debes ser un estudiante verificado de una universidad del Reino Unido para publicar anuncios. Quienes no sean estudiantes pueden navegar con acceso restringido.',
                    'Artículos prohibidos: está estrictamente prohibida la venta de alcohol, tabaco, armas, sustancias ilegales y materiales peligrosos.',
                    'Tarifas: las tarifas de publicación no son reembolsables una vez que el anuncio está publicado. No cobramos comisión por las transacciones entre estudiantes.',
                    'Seguridad: queda con los compradores en zonas públicas y bien iluminadas del campus. Recomendamos ir acompañado en transacciones de alto valor.',
                    'Exactitud: todos los anuncios deben reflejar con precisión el estado del artículo. Los anuncios engañosos se eliminarán.',
                ],
            ],
            'zh' => [
                'title' => '服务条款',
                'subtitle' => '使用 UniTill 平台的规则。',
                'points' => [
                    '资格：必须是经过认证的英国高校在读学生才能发布商品。非学生可以浏览，但访问权限有限。',
                    '禁售物品：严禁销售酒精、烟草、武器、违禁品及危险物品。',
                    '费用：广告一经上线，发布费用不予退还。学生之间的交易我们不收取佣金。',
                    '安全：请在校园内光线充足的公共场所与买家见面。大额交易建议结伴前往。',
                    '准确性：所有广告都必须如实描述物品成色，误导性广告将被下架。',
                ],
            ],
        ],
        'privacy_policy' => [
            'fr' => [
                'title' => 'Politique de confidentialité',
                'subtitle' => 'Comment nous collectons et protégeons vos données d\'identité étudiante.',
                'points' => [
                    'Collecte des données : nous collectons votre nom, votre e-mail universitaire et votre numéro de téléphone à des fins de vérification.',
                    'Vérification étudiante : votre adresse .ac.uk nous permet de garantir un environnement sûr, réservé aux étudiants.',
                    'Partage des données : nous ne vendons jamais vos données personnelles à des annonceurs tiers.',
                    'Conservation : vos données sont conservées tant que votre compte est actif, puis 12 mois après sa suppression pour respecter nos obligations légales.',
                    'Sécurité : toutes les données sont chiffrées avec un chiffrement AES 256 bits, standard du secteur.',
                ],
            ],
            'es' => [
                'title' => 'Política de privacidad',
                'subtitle' => 'Cómo recopilamos y protegemos los datos de tu identidad estudiantil.',
                'points' => [
                    'Recopilación de datos: recopilamos tu nombre, correo universitario y número de teléfono con fines de verificación.',
                    'Verificación estudiantil: usamos tu correo .ac.uk para garantizar un entorno seguro y exclusivo para estudiantes.',
                    'Compartir datos: nunca vendemos tus datos personales a anunciantes externos.',
                    'Conservación: conservamos tus datos mientras tu cuenta esté activa y durante 12 meses tras su eliminación para cumplir obligaciones legales.',
                    'Seguridad: todos los datos se cifran con cifrado AES de 256 bits, estándar del sector.',
                ],
            ],
            'zh' => [
                'title' => '隐私政策',
                'subtitle' => '我们如何收集并保护你的学生身份数据。',
                'points' => [
                    '数据收集：我们收集你的姓名、学校邮箱和手机号码，用于身份验证。',
                    '学生认证：我们通过 .ac.uk 邮箱确保平台是仅限学生的安全环境。',
                    '数据共享：我们绝不会将你的个人数据出售给第三方广告商。',
                    '数据保留：账号有效期间我们会保留你的数据，注销后再保留 12 个月以符合法律义务。',
                    '安全：所有数据均采用行业标准的 256 位 AES 加密。',
                ],
            ],
        ],
        'refund_policy' => [
            'fr' => [
                'title' => 'Politique de remboursement',
                'subtitle' => 'Règles de remboursement des frais de publication.',
                'points' => [
                    'Annonce en ligne : dès qu\'une annonce est publiée, les frais de publication sont considérés comme consommés et ne sont pas remboursables.',
                    'Problèmes techniques : si une erreur technique empêche l\'affichage de votre annonce, vous avez droit à un remboursement intégral ou à une republication gratuite.',
                    'Articles vendus : marquer un article comme vendu ne donne pas droit à un remboursement partiel pour la durée restante.',
                    'Retrait : les annonces retirées pour non-respect des règles de la communauté ne sont pas remboursables.',
                ],
            ],
            'es' => [
                'title' => 'Política de reembolso',
                'subtitle' => 'Directrices para el reembolso de las tarifas de publicación.',
                'points' => [
                    'Anuncio publicado: una vez que el anuncio está en línea, la tarifa de publicación se considera consumida y no reembolsable.',
                    'Problemas técnicos: si un error técnico impide que tu anuncio aparezca, tienes derecho a un reembolso completo o a una republicación gratuita.',
                    'Artículos vendidos: marcar un artículo como vendido no genera un reembolso parcial por el tiempo restante.',
                    'Retirada: los anuncios retirados por incumplir las normas de la comunidad no son reembolsables.',
                ],
            ],
            'zh' => [
                'title' => '退款政策',
                'subtitle' => '广告发布费用的退款规则。',
                'points' => [
                    '广告上线：广告一经在平台上线，发布费用即视为已消耗，不予退还。',
                    '技术故障：若因技术错误导致广告无法展示，你可获得全额退款或一次免费重发。',
                    '已售物品：将物品标记为已售不会按剩余展示时长退还部分费用。',
                    '下架：因违反社区准则而被下架的广告不予退款。',
                ],
            ],
        ],
        'cookie_policy' => [
            'fr' => [
                'title' => 'Politique relative aux cookies',
                'subtitle' => 'Informations sur le stockage local et vos préférences.',
                'points' => [
                    'Objectif : nous utilisons le stockage local (cookies) pour vous garder connecté et mémoriser votre langue et vos préférences de recherche.',
                    'Données analytiques : nous collectons des données d\'usage anonymes afin d\'améliorer les performances et corriger les bugs.',
                    'Tiers : nous n\'utilisons pas de cookies de suivi tiers à des fins de publicité ciblée.',
                    'Gestion : vous pouvez effacer vos données à tout moment depuis les réglages de votre navigateur, ce qui vous déconnectera d\'UniTill.',
                ],
            ],
            'es' => [
                'title' => 'Política de cookies',
                'subtitle' => 'Información sobre el almacenamiento local y tus preferencias.',
                'points' => [
                    'Finalidad: usamos el almacenamiento local (cookies) para mantener tu sesión iniciada y recordar tu idioma y preferencias de búsqueda.',
                    'Datos analíticos: recopilamos datos de uso anónimos para mejorar el rendimiento y corregir errores.',
                    'Terceros: no usamos cookies de seguimiento de terceros para publicidad dirigida.',
                    'Gestión: puedes borrar tus datos en cualquier momento desde los ajustes del navegador, lo que cerrará tu sesión en UniTill.',
                ],
            ],
            'zh' => [
                'title' => 'Cookie 政策',
                'subtitle' => '关于本地存储与偏好设置的信息。',
                'points' => [
                    '用途：我们使用本地存储（Cookie）保持你的登录状态，并记住你的语言与搜索偏好。',
                    '分析数据：我们收集匿名使用数据，用于改进性能和修复缺陷。',
                    '第三方：我们不使用第三方跟踪 Cookie 进行定向广告。',
                    '管理：你可以随时通过浏览器设置清除数据，这会使你退出 UniTill 的登录状态。',
                ],
            ],
        ],
    ];

    /** English contact reason name => [locale => name]. */
    private const CONTACT_REASONS = [
        'General inquiry' => ['fr' => 'Demande générale', 'es' => 'Consulta general', 'zh' => '一般咨询'],
        'Complaint' => ['fr' => 'Réclamation', 'es' => 'Reclamación', 'zh' => '投诉'],
        'Suggestion' => ['fr' => 'Suggestion', 'es' => 'Sugerencia', 'zh' => '建议'],
        'Technical support' => ['fr' => 'Support technique', 'es' => 'Soporte técnico', 'zh' => '技术支持'],
    ];
}
