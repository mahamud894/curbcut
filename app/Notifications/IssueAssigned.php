<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Issue $issue, public ?User $assignedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $issue = $this->issue->loadMissing('site', 'page');

        return (new MailMessage)
            ->subject("[{$issue->site->name}] Accessibility issue assigned to you")
            ->greeting("Hi {$notifiable->name},")
            ->line(($this->assignedBy?->name ?? 'Someone')." assigned you an accessibility issue on {$issue->site->name}:")
            ->line("**{$issue->help}** ({$issue->impact})")
            ->line("Page: {$issue->page->url}")
            ->when($issue->due_on, fn ($mail) => $mail->line('Due: '.$issue->due_on->toFormattedDateString()))
            ->action('Open the issue', route('sites.issues.show', [$issue->site_id, $issue->id]));
    }
}
