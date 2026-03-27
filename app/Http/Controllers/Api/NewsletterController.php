<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    // ── PUBLIC ────────────────────────────────────────────────────────────

    /** POST /newsletter/subscribe */
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'name'  => 'nullable|string|max:100',
        ]);

        $sub = NewsletterSubscriber::firstOrNew(['email' => strtolower($request->email)]);

        if ($sub->isActive()) {
            return response()->json(['success' => true, 'message' => 'You are already subscribed!']);
        }

        // Re-subscribe if previously unsubscribed
        $sub->unsubscribed_at = null;
        $sub->name            = $request->name ?: $sub->name;
        $sub->confirm_token   = NewsletterSubscriber::generateToken();
        $sub->unsubscribe_token = $sub->unsubscribe_token ?: NewsletterSubscriber::generateToken();
        $sub->user_id         = User::where('email', $request->email)->value('id');
        $sub->source          = $request->input('source', 'website');
        $sub->save();

        // Send confirmation email
        Mail::to($sub->email)->queue(new \App\Mail\NewsletterConfirmMail($sub));

        return response()->json(['success' => true, 'message' => 'Please check your email to confirm your subscription.']);
    }

    /** GET /newsletter/confirm?token= */
    public function confirm(Request $request)
    {
        $sub = NewsletterSubscriber::where('confirm_token', $request->token)->first();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired confirmation link.'], 404);
        }
        $sub->update(['confirmed_at' => now(), 'confirm_token' => null]);
        return response()->json(['success' => true, 'message' => 'Subscription confirmed! Welcome to Pyonea updates.']);
    }

    /** GET /newsletter/unsubscribe?token= */
    public function unsubscribe(Request $request)
    {
        $sub = NewsletterSubscriber::where('unsubscribe_token', $request->token)->first();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'Invalid unsubscribe link.'], 404);
        }
        $sub->update(['unsubscribed_at' => now()]);
        return response()->json(['success' => true, 'message' => 'You have been unsubscribed. You will no longer receive newsletters from Pyonea.']);
    }

    /** PUT /newsletter/preferences — authenticated users update their prefs */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'pref_promotions'     => 'boolean',
            'pref_new_sellers'    => 'boolean',
            'pref_product_updates'=> 'boolean',
            'pref_platform_news'  => 'boolean',
        ]);
        $user = $request->user();
        $sub  = NewsletterSubscriber::where('email', $user->email)->first();
        if ($sub) $sub->update($validated);
        return response()->json(['success' => true, 'message' => 'Preferences updated.']);
    }

    // ── ADMIN ─────────────────────────────────────────────────────────────

    /** GET /admin/newsletter/subscribers */
    public function subscribers(Request $request)
    {
        $subs = NewsletterSubscriber::active()
            ->when($request->search, fn($q) => $q->where('email','like',"%{$request->search}%"))
            ->paginate($request->input('per_page', 20));

        $total = NewsletterSubscriber::active()->count();
        $unconfirmed = NewsletterSubscriber::whereNull('confirmed_at')->count();

        return response()->json([
            'success' => true,
            'data'    => $subs->items(),
            'meta'    => ['total'=>$total, 'unconfirmed'=>$unconfirmed, 'current_page'=>$subs->currentPage(), 'last_page'=>$subs->lastPage()],
        ]);
    }

    /** GET /admin/newsletter/campaigns */
    public function campaigns()
    {
        $campaigns = EmailCampaign::orderByDesc('created_at')->paginate(15);
        return response()->json(['success' => true, 'data' => $campaigns->items(), 'meta' => ['total'=>$campaigns->total(),'last_page'=>$campaigns->lastPage()]]);
    }

    /** POST /admin/newsletter/campaigns */
    public function createCampaign(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'subject'         => 'required|string|max:255',
            'body_html'       => 'required|string',
            'body_text'       => 'nullable|string',
            'audience'        => 'required|in:newsletter_subscribers,all_buyers,all_sellers,buyers_by_city,sellers_by_tier,custom_ids',
            'audience_filter' => 'nullable|array',
            'scheduled_at'    => 'nullable|date|after:now',
        ]);
        $campaign = EmailCampaign::create(array_merge($validated, ['created_by' => $request->user()->id]));
        return response()->json(['success' => true, 'data' => $campaign], 201);
    }

    /** PUT /admin/newsletter/campaigns/{id} */
    public function updateCampaign(Request $request, int $id)
    {
        $campaign = EmailCampaign::findOrFail($id);
        if (in_array($campaign->status, ['sending','sent'])) {
            return response()->json(['success'=>false,'message'=>'Cannot edit a campaign that is already sending or sent.'], 422);
        }
        $campaign->update($request->only(['name','subject','body_html','body_text','audience','audience_filter','scheduled_at']));
        return response()->json(['success' => true, 'data' => $campaign]);
    }

    /** POST /admin/newsletter/campaigns/{id}/send */
    public function sendCampaign(Request $request, int $id)
    {
        $campaign = EmailCampaign::findOrFail($id);
        if ($campaign->status === 'sent') {
            return response()->json(['success'=>false,'message'=>'This campaign has already been sent.'], 422);
        }

        $recipients = $this->buildRecipientList($campaign);
        if ($recipients->isEmpty()) {
            return response()->json(['success'=>false,'message'=>'No recipients found for this audience.'], 422);
        }

        $campaign->update(['status'=>'sending','recipients_count'=>$recipients->count()]);

        // Queue emails in chunks — avoids timeout on large lists
        $campaign->update(['sent_at' => now()]);
        $delivered = 0;
        foreach ($recipients->chunk(50) as $chunk) {
            foreach ($chunk as $recipient) {
                try {
                    Mail::to($recipient->email)->queue(new CampaignMail($campaign, $recipient->unsubscribe_token ?? null));
                    $delivered++;
                } catch (\Exception $e) {
                    Log::warning("Campaign mail failed to {$recipient->email}: " . $e->getMessage());
                }
            }
        }

        $campaign->update(['status'=>'sent','delivered_count'=>$delivered]);

        Log::info('Campaign sent', ['campaign_id'=>$id,'recipients'=>$recipients->count(),'delivered'=>$delivered]);

        return response()->json([
            'success'   => true,
            'message'   => "Campaign sent to {$delivered} recipients.",
            'delivered' => $delivered,
        ]);
    }

    /** GET /admin/newsletter/campaigns/{id}/preview — returns recipient count */
    public function previewCampaign(int $id)
    {
        $campaign   = EmailCampaign::findOrFail($id);
        $recipients = $this->buildRecipientList($campaign);
        return response()->json(['success'=>true,'recipient_count'=>$recipients->count(),'sample_emails'=>$recipients->take(5)->pluck('email')]);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function buildRecipientList(EmailCampaign $campaign)
    {
        $filter = $campaign->audience_filter ?? [];

        return match ($campaign->audience) {
            'newsletter_subscribers' =>
                NewsletterSubscriber::active()->get(['email','name','unsubscribe_token']),

            'all_buyers' =>
                User::where('type','buyer')->where('is_active',true)
                    ->when($filter['city'] ?? null, fn($q,$city) => $q->where('city',$city))
                    ->get(['email','name'])->map(fn($u) => (object)['email'=>$u->email,'name'=>$u->name,'unsubscribe_token'=>null]),

            'all_sellers' =>
                User::where('type','seller')->where('is_active',true)
                    ->get(['email','name'])->map(fn($u) => (object)['email'=>$u->email,'name'=>$u->name,'unsubscribe_token'=>null]),

            'buyers_by_city' =>
                User::where('type','buyer')->where('city', $filter['city'] ?? '')->where('is_active',true)
                    ->get(['email','name'])->map(fn($u) => (object)['email'=>$u->email,'name'=>$u->name,'unsubscribe_token'=>null]),

            'sellers_by_tier' =>
                User::where('type','seller')->where('is_active',true)
                    ->whereHas('sellerProfile', fn($q) => $q->where('seller_tier', $filter['tier'] ?? 'gold'))
                    ->get(['email','name'])->map(fn($u) => (object)['email'=>$u->email,'name'=>$u->name,'unsubscribe_token'=>null]),

            'custom_ids' =>
                User::whereIn('id', $filter['ids'] ?? [])->get(['email','name'])
                    ->map(fn($u) => (object)['email'=>$u->email,'name'=>$u->name,'unsubscribe_token'=>null]),

            default => collect(),
        };
    }
}