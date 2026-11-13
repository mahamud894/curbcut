import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import { LanguageSelect } from '@/components/curbcut/language-select';
import HeadingSmall from '@/components/heading-small';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t, tk } from '@/lib/i18n';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: tk('Appearance settings'),
        href: '/settings/appearance',
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Appearance settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title={t('Appearance settings')} description={t("Update your account's appearance settings")} />
                    <AppearanceTabs />
                </div>
                <div className="space-y-4">
                    <HeadingSmall title={t('Language')} />
                    <LanguageSelect />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
