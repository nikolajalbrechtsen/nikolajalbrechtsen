<?php

use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:190')]
    public string $subject = '';

    #[Validate('required|string|min:10|max:5000')]
    public string $message = '';

    /** Honeypot: real people leave this empty. */
    public string $website = '';

    public bool $sent = false;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function send(): void
    {
        $this->validate();

        if ($this->website !== '') {
            $this->sent = true; // silently drop spam bots

            return;
        }

        $key = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('message', 'Too many messages. Please try again later.');

            return;
        }
        RateLimiter::hit($key, 3600);

        $msg = ContactMessage::create($this->only(['name', 'email', 'subject', 'message']));

        if ($to = setting('contact_email')) {
            try {
                Mail::raw("New message from {$msg->name} <{$msg->email}>\n\n{$msg->message}\n\n".url('/admin/contact-messages'), function ($m) use ($to, $msg) {
                    $m->to($to)->replyTo($msg->email, $msg->name)->subject('New contact message: '.($msg->subject ?: $msg->name));
                });
            } catch (\Throwable $e) {
                report($e); // the message is saved in the admin panel even if mail fails
            }
        }

        $this->reset(['subject', 'message']);
        $this->sent = true;
    }
}; ?>

<div>
    @if ($sent)
        <div class="py-8 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-500/10 text-2xl text-emerald-500">✓</div>
            <h3 class="mt-4 text-xl font-semibold text-zinc-900 dark:text-white">Thanks — message sent!</h3>
            <p class="mt-2 text-zinc-500">I’ll get back to you as soon as possible.</p>
            <button type="button" wire:click="$set('sent', false)" class="mt-6 text-sm font-medium text-brand">Send another message</button>
        </div>
    @else
        <form wire:submit="send" class="grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" required />
                <flux:input wire:model="email" type="email" label="Email" required />
            </div>
            <flux:input wire:model="subject" label="Subject (optional)" />
            <flux:textarea wire:model="message" label="Message" rows="6" required />
            <div class="hidden" aria-hidden="true"><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">Send message</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </form>
    @endif
</div>
