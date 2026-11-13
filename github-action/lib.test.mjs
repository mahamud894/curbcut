// Run with: node --test github-action/lib.test.mjs
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { failsAt, renderSummary } from './lib.mjs';

test('failsAt respects the threshold', () => {
    assert.equal(failsAt({ critical: 0, serious: 1 }, 'critical'), false);
    assert.equal(failsAt({ critical: 0, serious: 1 }, 'serious'), true);
    assert.equal(failsAt({ minor: 4 }, 'moderate'), false);
    assert.equal(failsAt({ minor: 4 }, 'minor'), true);
    assert.equal(failsAt({ critical: 9 }, 'none'), false);
});

test('the summary lists new issues with their WCAG criteria', () => {
    const md = renderSummary(
        {
            base_url: 'https://pr-1.example.com',
            pages: { requested: 2, scanned: 2, failed: 0 },
            issues: { new: 1, existing: 3, fixed: 1, new_by_impact: { critical: 1, serious: 0, moderate: 0, minor: 0 } },
            results: {
                pages: [],
                issues: [{ status: 'new', impact: 'critical', help: 'Buttons must have discernible text', help_url: 'https://x', path: '/', target: 'button.menu', wcag: ['wcag2a', 'wcag412', 'wcag1410'] }],
                fixed: [{ help: 'Images must have alternate text', path: '/about' }],
            },
        },
        'serious',
        true,
    );
    assert.match(md, /❌ Curbcut accessibility check/);
    assert.match(md, /\| critical \| \[Buttons must have discernible text\]\(https:\/\/x\) \| `\/` \| `button.menu` \| 4\.1\.2, 1\.4\.10 \|/);
    assert.match(md, /1 known issue is fixed/);
});
