<?php

namespace App\Notifications;

use App\Models\SubscriptionPlan;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductLimitWarning extends Notification
{
    public function __construct(
        public int $currentCount,
        public SubscriptionPlan $plan
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $upgradeUrl = rtrim(config('app.frontend_url'), '/') . '/seller/dashboard?tab=subscription';
        $remaining = max(0, $this->plan->product_limit - $this->currentCount);

        return (new MailMessage)
            ->subject('သင့်ဆိုင်ကြီးထွားလာနေပါပြီ - Plan မြှင့်ရန် အချိန်ရောက်ပါပြီ')
            ->greeting("မင်္ဂလာပါ {$notifiable->name},")
            ->line("Pyonea ပေါ်မှာ သင့်ဆိုင်က ကုန်ပစ္စည်း {$this->currentCount} ခုအထိ တင်ထားပြီးပါပြီ။ လက်ရှိ {$this->plan->name} plan ရဲ့ ကန့်သတ်ချက်က {$this->plan->product_limit} ခု ဖြစ်တဲ့အတွက် နောက်ထပ် {$remaining} ခုသာ ထည့်နိုင်တော့မှာပါ။")
            ->line('ဒါက ပြဿနာမဟုတ်ပါဘူး။ သင့်ဆိုင်က တိုးတက်လာနေတယ်ဆိုတဲ့ သက်သေပါ။ ကုန်ပစ္စည်းများများ တင်နိုင်လေ၊ ဝယ်သူတွေ သင့်ဆိုင်ကို ရှာတွေ့နိုင်ခြေ ပိုများလေ ဖြစ်ပါတယ်။')
            ->line('ကုန်ပစ္စည်းအသစ်တွေ ထပ်တင်ချင်တဲ့အချိန်မှာ ကန့်သတ်ချက်ကြောင့် ရပ်တန့်မနေစေဖို့ အခုကတည်းက နောက်ထပ် plan ကို မြှင့်ထားပါ။ ရောင်းအားအခွင့်အလမ်း မလွတ်သွားအောင် ကြိုတင်ပြင်ဆင်ထားတာက အကောင်းဆုံးပါ။')
            ->action('Plan မြှင့်ပြီး ဆိုင်ကို ဆက်ချဲ့မည်', $upgradeUrl)
            ->line('Pyonea မှာ သင့်လုပ်ငန်းကြီးထွားလာတာကို ကျွန်ုပ်တို့ ဝမ်းမြောက်ပါတယ်။');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'product_limit_warning',
            'plan_name' => $this->plan->name,
            'plan_slug' => $this->plan->slug,
            'current_count' => $this->currentCount,
            'plan_limit' => $this->plan->product_limit,
            'remaining' => max(0, $this->plan->product_limit - $this->currentCount),
            'url' => '/seller/dashboard?tab=subscription',
            'message' => "Your {$this->plan->name} plan is almost full: {$this->currentCount}/{$this->plan->product_limit} products.",
        ];
    }
}
