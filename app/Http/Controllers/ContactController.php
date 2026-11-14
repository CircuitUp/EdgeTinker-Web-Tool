<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use App\Support\PageContent;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    /** Messages one visitor (IP address) may send per hour. */
    private const MAX_PER_HOUR = 5;

    public function show(Request $request, PageContent $pages): View
    {
        $subject = $request->query('subject');

        return view('contact', [
            'content' => $pages->get('contact'),
            'seo' => $pages->seo('contact'),
            // "Enquire" buttons pass the product or service as ?subject=…
            'subject' => is_string($subject) ? mb_substr(trim($subject), 0, 150) : '',
        ]);
    }

    public function store(Request $request, PageContent $pages, Settings $settings): RedirectResponse
    {
        // Honeypot: a hidden field people never see. Bots that fill it get a normal-looking
        // "thank you" so they do not learn to avoid it, but nothing is stored or sent.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('contact_sent', true);
        }

        $key = 'contact-form|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            return back()->withInput()->withErrors([
                'message' => 'You have sent several messages in a short time. Please wait a while before sending another.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], ['email' => 'email address']);

        RateLimiter::hit($key, 3600);

        // Stored first, so the message is never lost even if the email cannot be sent.
        $message = ContactMessage::create($data + ['ip_address' => $request->ip()]);

        if ($recipient = self::recipient($settings)) {
            Notification::route('mail', $recipient)->notify(new ContactMessageReceived($message));
        }

        return redirect()->route('contact')->with('contact_sent', true);
    }

    /** Where contact messages are emailed: the address set in Site settings, else CONTACT_RECIPIENT in .env. */
    public static function recipient(Settings $settings): ?string
    {
        foreach ([$settings->get('contact.recipient'), config('edgetinker.contact_recipient'),
            $settings->isPlaceholder('contact.email') ? null : $settings->get('contact.email')] as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }
}
