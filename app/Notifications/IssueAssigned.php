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

        // Laravel sends this in the recipient's preferred language (User::preferredLocale()).
        return (new MailMessage)
            ->subject(tr('[{site}] Accessibility issue assigned to you', ['site' => $issue->site->name]))
            ->greeting(tr('Hi {name},', ['name' => $notifiable->name]))
            ->line(tr('{person} assigned you an accessibility issue on {site}:', ['person' => $this->assignedBy?->name ?? tr('Someone'), 'site' => $issue->site->name]))
            ->line("**{$issue->help}** (".tr($issue->impact ?? 'minor').')')
            ->line(tr('Page: {url}', ['url' => $issue->page->url]))
            ->when($issue->due_on, fn ($mail) => $mail->line(tr('Due: {date}', ['date' => $issue->due_on->locale(app()->getLocale())->isoFormat('LL')])))
            ->action(tr('Open the issue'), route('sites.issues.show', [$issue->site_id, $issue->id]));
    }
}
