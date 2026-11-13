<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'page_id' => fn (array $a) => Page::create(['site_id' => $a['site_id'], 'url' => 'https://example.test/', 'url_hash' => sha1('https://example.test/'.uniqid())])->id,
            'fingerprint' => sha1(uniqid('', true)),
            'rule_id' => 'image-alt',
            'impact' => 'critical',
            'wcag' => ['wcag2a', 'wcag111'],
            'help' => 'Images must have alternative text',
            'target' => 'img.hero',
            'status' => 'open',
        ];
    }
}
