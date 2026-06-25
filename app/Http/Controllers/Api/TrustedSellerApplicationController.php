<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrustedSellerApplicationRequest;
use App\Http\Resources\TrustedSellerApplicationResource;
use App\Models\TrustedSellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrustedSellerApplicationController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';

        $application = TrustedSellerApplication::query()
            ->where('user_id', $user->id)
            ->with('category.translations')
            ->latest('id')
            ->first();

        return sendResponse([
            'is_trusted_seller' => (bool) $user->is_trusted_seller,
            'trusted_seller_verified_at' => $user->trusted_seller_verified_at?->toIso8601String(),
            'monthly_fee' => [
                'amount' => (float) (setting('trusted_seller_monthly_fee') ?? 10),
                'currency' => 'GBP',
                'formatted' => '£'.number_format((float) (setting('trusted_seller_monthly_fee') ?? 10), 0),
                'label' => $lang
                    ? 'رسوم برنامج البائع الموثوق £10 شهرياً للأعمال ومقدمي الخدمات الخارجيين باستثناء منظمات الحرم'
                    : 'UNI TILL TRUSTED SELLER PROGRAM FEE IS £10 PER MONTH FOR BUSINESS AND EXTERNAL INDIVIDUAL PROVIDERS EXCEPT CAMPUS ORGANISATIONS.',
            ],
            'form_options' => $this->formOptions($lang),
            'application' => $application
                ? new TrustedSellerApplicationResource($application)
                : null,
        ]);
    }

    public function store(StoreTrustedSellerApplicationRequest $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';

        $hasPending = TrustedSellerApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'draft'])
            ->exists();

        if ($hasPending) {
            return sendError(
                $lang ? 'لديك طلب قيد المراجعة بالفعل' : 'You already have a pending application',
                [],
                422
            );
        }

        if ($user->is_trusted_seller) {
            return sendError(
                $lang ? 'حسابك موثق بالفعل' : 'Your account is already verified',
                [],
                422
            );
        }

        $application = TrustedSellerApplication::create([
            'user_id' => $user->id,
            'seller_type' => $request->input('entity_type'),
            'identity_confirmed_by_others' => (bool) $request->boolean('identity_confirmed_by_others'),
            'is_non_student_confirmed' => $request->boolean('is_non_student_confirmed'),
            'operations_city' => $request->input('operations_city'),
            'primary_contact_name' => $request->input('primary_contact_name'),
            'contact_email' => $request->input('contact_email'),
            'contact_phone' => $request->input('contact_phone'),
            'category_id' => $request->input('category_id'),
            'offers_summary' => $request->input('offers_summary'),
            'estimated_ads_volume' => $request->estimatedAdsVolume(),
            // Column is NOT NULL with no default; fall back to email when the
            // simplified mobile form does not provide a preference.
            'preferred_student_contact_method' => $request->input('preferred_student_contact_method') ?? 'email',
            'ack_review_discretion' => true,
            'ack_unitill_manages_directory' => true,
            'ack_no_app_access' => true,
            'ack_terms_accepted' => true,
            'status' => 'pending',
        ]);

        $application->load('category.translations');

        return sendResponse(
            new TrustedSellerApplicationResource($application),
            $lang ? 'تم إرسال طلبك للمراجعة' : 'Application submitted for review'
        );
    }

    protected function formOptions(bool $lang): array
    {
        $ar = $lang;

        return [
            'seller_types' => [
                ['value' => 'business', 'label' => $ar ? 'عمل تجاري' : 'Business'],
                ['value' => 'service_provider', 'label' => $ar ? 'مقدم خدمة فردي' : 'Individual service provider'],
                ['value' => 'organization', 'label' => $ar ? 'منظمة أو مؤسسة' : 'Organization or institution'],
            ],
            'estimated_listings_counts' => [
                ['value' => '1_10', 'label' => '1-10'],
                ['value' => '10_plus', 'label' => '10+'],
            ],
            'preferred_contact_methods' => [
                ['value' => 'email', 'label' => $ar ? 'البريد الإلكتروني' : 'Email'],
                ['value' => 'phone', 'label' => $ar ? 'الهاتف' : 'Phone'],
                ['value' => 'website', 'label' => $ar ? 'رابط الموقع' : 'Website link'],
            ],
            'declarations' => [
                [
                    'key' => 'confirm_not_student',
                    'label' => $ar
                        ? 'أؤكد أنني لست طالباً'
                        : 'I confirm that I am not a student',
                ],
                [
                    'key' => 'ack_review',
                    'label' => $ar
                        ? 'أقر بأن UniTill ستراجع طلبي وقد تطلب إثباتات إضافية'
                        : 'I acknowledge that UniTill will review my request and may request further proof',
                ],
                [
                    'key' => 'ack_removal_rights',
                    'label' => $ar
                        ? 'أقر بأن UniTill تحتفظ بحق إزالة حالة البائع الموثوق'
                        : 'I acknowledge that UniTill reserves the right to remove seller status',
                ],
                [
                    'key' => 'ack_terms',
                    'label' => $ar
                        ? 'أوافق على سياسة الخصوصية وشروط الخدمة'
                        : 'I accept the Privacy Policy & Terms of Service',
                ],
            ],
        ];
    }
}
