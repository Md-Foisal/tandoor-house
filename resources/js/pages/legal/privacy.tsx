import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, ShieldCheck, UserRound } from 'lucide-react';
import { SectionHeading } from '@/components/brand/section-heading';
import { Serve } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

type Section = {
    id: string;
    title: string;
    paragraphs: string[];
    items: string[];
};

type Props = {
    updated: string;
    sections: Section[];
    email: string;
    icoRegistered: boolean;
};

const ICO_COMPLAINTS = 'https://ico.org.uk/make-a-complaint/';

export default function Privacy({ updated, sections, email }: Props) {
    const t = useT();
    const { restaurant } = usePage().props;

    return (
        <>
            <Head title={t('legal.privacy.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('legal.privacy.meta.description', {
                        name: restaurant.name,
                    })}
                />
            </Head>

            <header className="container-page pt-10 md:pt-16">
                <Serve className="flex flex-col gap-3">
                    <SectionHeading
                        as="h1"
                        size="xl"
                        eyebrow={t('legal.privacy.eyebrow')}
                        title={t('legal.privacy.title')}
                        description={t('legal.privacy.intro')}
                    />
                    <p className="text-sm text-ink-muted">
                        {t('legal.updated', { date: updated })}
                    </p>
                </Serve>
            </header>

            <div className="container-page grid gap-10 py-10 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-16 lg:py-14">
                <nav
                    aria-label={t('legal.toc')}
                    className="lg:sticky lg:top-24 lg:self-start"
                >
                    <h2 className="text-label text-ink-muted">
                        {t('legal.toc')}
                    </h2>
                    <ol className="mt-3 flex flex-col text-sm">
                        {sections.map((section) => (
                            <li key={section.id}>
                                <a
                                    href={`#${section.id}`}
                                    className="inline-flex min-h-11 items-center underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ember"
                                >
                                    {section.title}
                                </a>
                            </li>
                        ))}
                    </ol>
                </nav>

                <div className="flex max-w-prose flex-col gap-10">
                    {sections.map((section) => (
                        <section
                            key={section.id}
                            id={section.id}
                            aria-labelledby={`${section.id}-title`}
                            className="scroll-mt-24"
                        >
                            <h2
                                id={`${section.id}-title`}
                                className="font-display text-2xl"
                            >
                                {section.title}
                            </h2>
                            {section.paragraphs.map((text) => (
                                <p key={text} className="mt-3 leading-relaxed">
                                    {text}
                                </p>
                            ))}
                            {section.items.length > 0 && (
                                <ul className="mt-3 flex list-disc flex-col gap-2 pl-5 leading-relaxed marker:text-ember">
                                    {section.items.map((text) => (
                                        <li key={text}>{text}</li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}

                    <div className="flex flex-col gap-3 border-t border-line pt-8 sm:flex-row sm:flex-wrap">
                        <Button asChild>
                            <a href={`mailto:${email}`}>
                                <Mail aria-hidden="true" />
                                {t('legal.privacy.actions.email')}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/settings/profile">
                                <UserRound aria-hidden="true" />
                                {t('legal.privacy.actions.account')}
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={ICO_COMPLAINTS}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ShieldCheck aria-hidden="true" />
                                {t('legal.privacy.actions.ico')}
                            </a>
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/cookies">
                                {t('legal.privacy.actions.cookies')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
