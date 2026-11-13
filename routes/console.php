<?php

use App\Actions\StartScan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('curbcut:schedule-scans', function (StartScan $start) {
    $queued = 0;
    Site::whereIn('scan_frequency', ['daily', 'weekly'])->each(function (Site $site) use ($start, &$queued) {
        if ($site->isDueForScheduledScan() && $start($site, 'scheduled')) {
            $queued++;
        }
    });
    $this->info("Queued {$queued} scheduled scan(s).");
})->purpose('Queue scans for sites whose daily or weekly scan is due');

Artisan::command('curbcut:user {email} {--name=} {--password=}', function () {
    $email = strtolower($this->argument('email'));
    $name = $this->option('name') ?: $this->ask('Name', ucfirst(strstr($email, '@', true)));
    $password = $this->option('password') ?: $this->secret('Password (at least 8 characters)');

    $validator = Validator::make(compact('email', 'name', 'password'), [
        'email' => ['required', 'email', 'unique:users,email'],
        'name' => ['required', 'string', 'max:255'],
        'password' => ['required', 'string', 'min:8'],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::create(compact('email', 'name', 'password'));
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->workspace();
    $this->info("Created {$user->email}. They can sign in now.");
})->purpose('Create a user account (useful when sign-up is turned off)');

Artisan::command('curbcut:scan {site : Site ID}', function (StartScan $start) {
    $site = Site::findOrFail($this->argument('site'));
    if (! $start($site, 'manual')) {
        $this->warn("A scan of {$site->name} is already queued or running.");

        return 1;
    }
    $this->info("Queued a scan of {$site->name}. A queue worker will pick it up.");
})->purpose('Queue a scan for one site');

Schedule::command('curbcut:schedule-scans')->hourly()->withoutOverlapping();
