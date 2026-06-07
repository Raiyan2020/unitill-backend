<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrustedSellerApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en') === 'ar' ? 'ar' : 'en';

        return [
            'id' => $this->id,
            'seller_type' => $this->seller_type,
            'seller_type_label' => $this->sellerTypeLabel($lang),
            'identity_confirmed_by_others' => (bool) $this->identity_confirmed_by_others,
            'operations_city' => $this->operations_city,
            'primary_contact_name' => $this->primary_contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->nameForLanguageCode($lang),
            'offers_summary' => $this->offers_summary,
            'estimated_listings_count' => $this->estimatedListingsCount(),
            'estimated_listings_label' => $this->estimatedListingsLabel($lang),
            'preferred_student_contact_method' => $this->preferred_student_contact_method,
            'preferred_contact_label' => $this->contactMethodLabel($lang),
            'status' => $this->status,
            'status_label' => $this->statusLabel($lang),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function estimatedListingsCount(): string
    {
        return $this->estimated_ads_volume === 'multiple' ? '10_plus' : '1_10';
    }

    protected function sellerTypeLabel(string $lang): string
    {
        $ar = $lang === 'ar';

        return match ($this->seller_type) {
            'business' => $ar ? 'عمل تجاري' : 'Business',
            'service_provider' => $ar ? 'مقدم خدمة فردي' : 'Individual service provider',
            'organization' => $ar ? 'منظمة أو مؤسسة' : 'Organization or institution',
            default => (string) $this->seller_type,
        };
    }

    protected function estimatedListingsLabel(string $lang): string
    {
        return $this->estimatedListingsCount() === '10_plus'
            ? ($lang === 'ar' ? '10+' : '10+')
            : ($lang === 'ar' ? '1-10' : '1-10');
    }

    protected function contactMethodLabel(string $lang): string
    {
        $ar = $lang === 'ar';

        return match ($this->preferred_student_contact_method) {
            'email' => $ar ? 'البريد الإلكتروني' : 'Email',
            'phone' => $ar ? 'الهاتف' : 'Phone',
            'website' => $ar ? 'رابط الموقع' : 'Website link',
            default => (string) $this->preferred_student_contact_method,
        };
    }

    protected function statusLabel(string $lang): string
    {
        $ar = $lang === 'ar';

        return match ($this->status) {
            'pending' => $ar ? 'قيد المراجعة' : 'Under review',
            'approved' => $ar ? 'موافق عليه' : 'Approved',
            'rejected' => $ar ? 'مرفوض' : 'Rejected',
            'draft' => $ar ? 'مسودة' : 'Draft',
            default => (string) $this->status,
        };
    }
}
