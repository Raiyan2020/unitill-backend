<?php

namespace Database\Seeders;

use App\Models\ContactReason;
use App\Models\ContactReasonTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;

class ContactReasonSeeder extends Seeder
{
    public function run(): void
    {
        $languages = Language::all()->keyBy('code');
        if ($languages->isEmpty()) {
            return;
        }

        $definitions = [
            ['sort_order' => 1, 'names' => [
                'ar' => 'استفسار عام',
                'en' => 'General inquiry',
                'fr' => 'Demande générale',
                'ur' => 'عمومی استفسار',
                'hi' => 'सामान्य पूछताछ',
                'tr' => 'Genel soru',
            ]],
            ['sort_order' => 2, 'names' => [
                'ar' => 'شكوى',
                'en' => 'Complaint',
                'fr' => 'Réclamation',
                'ur' => 'شکایت',
                'hi' => 'शिकायत',
                'tr' => 'Şikayet',
            ]],
            ['sort_order' => 3, 'names' => [
                'ar' => 'اقتراح',
                'en' => 'Suggestion',
                'fr' => 'Suggestion',
                'ur' => 'تجویز',
                'hi' => 'सुझाव',
                'tr' => 'Öneri',
            ]],
            ['sort_order' => 4, 'names' => [
                'ar' => 'دعم فني',
                'en' => 'Technical support',
                'fr' => 'Support technique',
                'ur' => 'تکنیکی معاونت',
                'hi' => 'तकनीकी सहायता',
                'tr' => 'Teknik destek',
            ]],
        ];

        foreach ($definitions as $def) {
            $reason = ContactReason::create([
                'sort_order' => $def['sort_order'],
                'is_active' => true,
            ]);
            foreach ($def['names'] as $code => $name) {
                $lang = $languages->get($code);
                if ($lang) {
                    ContactReasonTranslation::create([
                        'contact_reason_id' => $reason->id,
                        'language_id' => $lang->id,
                        'name' => $name,
                    ]);
                }
            }
        }
    }
}
