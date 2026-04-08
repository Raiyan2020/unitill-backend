<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactUsRequest;
use App\Models\ContactUsMessage;

class ContactUsController extends Controller
{
    public function __invoke(ContactUsRequest $request)
    {
        $data = $request->validated();

        $message = ContactUsMessage::create([
            'user_id' => $request->user()->id,
            'contact_reason_id' => $data['contact_reason_id'],
            'message' => $data['message'],
        ]);

        $lang = $request->header('lang');
        $massage = $lang === 'ar' ? 'تم إرسال رسالتك بنجاح' : 'Your message was sent successfully';

        return sendResponse(['id' => $message->id], $massage);
    }
}
