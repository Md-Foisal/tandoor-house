import { Head } from '@inertiajs/react';
import { RotateCcw, ShoppingBag } from 'lucide-react';
import {
    AnimatePresence,
    LayoutGroup,
    motion,
    useAnimate,
    useReducedMotion,
} from 'motion/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ArchFrame } from '@/components/brand/arch-frame';
import { LogoMark } from '@/components/brand/logo';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { SectionHeading } from '@/components/brand/section-heading';
import { TicketSteps } from '@/components/brand/ticket-steps';
import { DishCard, DishCardSkeleton } from '@/components/menu/dish-card';
import {
    CountUp,
    Course,
    DoorReveal,
    MarkerDrop,
    PhotoPrint,
    Plate,
    Serve,
    Settle,
    SteamWisp,
} from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { spring, uiTransition } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { MenuItem } from '@/types';

function Demo({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description: string;
    children: (key: number) => ReactNode;
    className?: string;
}) {
    const t = useT();
    const [run, setRun] = useState(0);

    return (
        <section className="flex flex-col gap-5 border-t border-line pt-8">
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-1">
                    <h2 className="text-title font-semibold">{title}</h2>
                    <p className="max-w-[60ch] text-sm text-ink-muted">
                        {description}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setRun((value) => value + 1)}
                >
                    <RotateCcw aria-hidden="true" />
                    {t('styleguide.replay')}
                </Button>
            </div>
            <div key={run} className={className}>
                {children(run)}
            </div>
        </section>
    );
}

function ClearedPlates({ items }: { items: MenuItem[] }) {
    const [vegOnly, setVegOnly] = useState(false);
    const shown = vegOnly ? items.filter((item) => item.is_vegetarian) : items;

    return (
        <div className="flex flex-col gap-4">
            <Button
                variant={vegOnly ? 'default' : 'outline'}
                size="sm"
                className="w-fit"
                onClick={() => setVegOnly(!vegOnly)}
                aria-pressed={vegOnly}
            >
                Vegetarian only
            </Button>
            <LayoutGroup>
                <motion.ul
                    layout
                    className="grid grid-cols-2 gap-3 sm:grid-cols-3"
                >
                    <AnimatePresence mode="popLayout">
                        {shown.map((item) => (
                            <motion.li
                                key={item.id}
                                layout
                                initial={{ opacity: 0, scale: 0.96 }}
                                animate={{ opacity: 1, scale: 1 }}
                                exit={{
                                    opacity: 0,
                                    scale: 0.96,
                                    transition: { duration: 0.15 },
                                }}
                                transition={uiTransition}
                                className="rounded-md border border-line bg-surface p-3 text-sm font-semibold"
                            >
                                {item.name}
                            </motion.li>
                        ))}
                    </AnimatePresence>
                </motion.ul>
            </LayoutGroup>
        </div>
    );
}

function ToTheCart() {
    const reduced = useReducedMotion();
    const [scope, animate] = useAnimate();
    const [count, setCount] = useState(1);

    const add = async () => {
        setCount((value) => value + 1);

        if (reduced) {
            return;
        }

        await animate(
            '[data-plate]',
            {
                opacity: [0, 1, 1, 0],
                x: [0, 90, 170, 200],
                y: [0, -50, -30, 0],
                scale: [1, 0.9, 0.7, 0.4],
            },
            { duration: 0.55, ease: 'easeOut' },
        );
        await animate(
            '[data-badge]',
            { scale: [1, 1.15, 1] },
            { duration: 0.25 },
        );
    };

    return (
        <div ref={scope} className="relative flex items-center gap-10">
            <Button onClick={add}>Add to basket</Button>
            <span
                data-plate
                aria-hidden="true"
                className="pointer-events-none absolute left-12 grid size-8 place-items-center rounded-full border-2 border-line-strong bg-surface opacity-0"
            >
                <span className="size-4 rounded-full bg-ember/70" />
            </span>
            <span className="relative grid size-11 place-items-center rounded-full bg-clay">
                <ShoppingBag className="size-5" aria-hidden="true" />
                <span
                    data-badge
                    className="absolute -top-1 -right-1 grid size-5 place-items-center rounded-full bg-ember text-xs font-bold text-ember-ink tabular-nums"
                >
                    {count}
                </span>
            </span>
        </div>
    );
}

function SharedPhoto({ item }: { item: MenuItem }) {
    const [open, setOpen] = useState(false);

    return (
        <LayoutGroup>
            {!open ? (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="w-44 text-left outline-hidden focus-visible:outline-2 focus-visible:outline-ember"
                >
                    <motion.div layoutId="sg-shared" transition={spring.plate}>
                        <ArchFrame>
                            <Picture
                                mediaKey={item.image}
                                alt=""
                                sizes="176px"
                            />
                        </ArchFrame>
                    </motion.div>
                    <p className="mt-2 font-semibold">{item.name}</p>
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="block w-full outline-hidden focus-visible:outline-2 focus-visible:outline-ember"
                >
                    <motion.div layoutId="sg-shared" transition={spring.plate}>
                        <ArchFrame
                            ratio="landscape"
                            className="w-full max-w-xl"
                        >
                            <Picture
                                mediaKey={item.image}
                                alt=""
                                sizes="576px"
                            />
                        </ArchFrame>
                    </motion.div>
                    <p className="mt-3 text-left font-display text-display-lg">
                        {item.name}
                    </p>
                </button>
            )}
        </LayoutGroup>
    );
}

export default function MotionGuide({ items }: { items: MenuItem[] }) {
    const t = useT();
    const first = items[0];
    const placeCards = ['18:00', '18:15', '18:30', '18:45', '19:00', '19:15'];
    const notes = [
        ['The daal is worth crossing London for.', 'Hannah, Walthamstow'],
        ['Lamb chops, then more lamb chops.', 'Marcus, Bow'],
        [
            'They remembered my nut allergy on the second visit.',
            'Aisha, Hackney',
        ],
    ];

    return (
        <>
            <Head title={t('styleguide.motion_title')}>
                <meta name="robots" content="noindex" />
            </Head>
            <div className="flex flex-col gap-10">
                <SectionHeading
                    as="h1"
                    size="xl"
                    eyebrow="Served from the pass"
                    title={t('styleguide.motion_title')}
                    description={t('styleguide.motion_intro')}
                />

                <Demo
                    title="Plate set-down"
                    description="Rises 32px while scaling from 0.96, on a soft spring; the shadow arrives last, as if it just touched the table."
                >
                    {() => (
                        <Course className="w-64">
                            {first && (
                                <Plate className="rounded-lg">
                                    <DishCard item={first} href="#" />
                                </Plate>
                            )}
                        </Course>
                    )}
                </Demo>

                <Demo
                    title="Course by course"
                    description="A group lands one after another in reading order, 90ms apart, and always within 600ms."
                >
                    {() => (
                        <Course className="grid grid-cols-2 gap-6 md:grid-cols-3">
                            {items.slice(0, 6).map((item) => (
                                <Plate key={item.id}>
                                    <DishCard item={item} href="#" />
                                </Plate>
                            ))}
                        </Course>
                    )}
                </Demo>

                <Demo
                    title="Steam clears"
                    description="Photos start soft and warm-dimmed and clear as they land. Hot dishes let one wisp rise."
                >
                    {() =>
                        first && (
                            <div className="relative w-56">
                                <ArchFrame>
                                    <Picture
                                        mediaKey={first.image}
                                        alt=""
                                        sizes="224px"
                                        steam
                                    />
                                </ArchFrame>
                                <SteamWisp className="absolute top-8 left-1/2 -translate-x-1/2" />
                            </div>
                        )
                    }
                </Demo>

                <Demo
                    title="A new course"
                    description="The section heading arrives first, then its dishes."
                >
                    {() => (
                        <div className="flex flex-col gap-6">
                            <Serve>
                                <SectionHeading
                                    title="From the Tandoor"
                                    size="md"
                                />
                            </Serve>
                            <Course className="grid grid-cols-3 gap-4">
                                {items.slice(0, 3).map((item) => (
                                    <Plate key={item.id}>
                                        <ArchFrame>
                                            <Picture
                                                mediaKey={item.image}
                                                alt=""
                                                sizes="200px"
                                            />
                                        </ArchFrame>
                                    </Plate>
                                ))}
                            </Course>
                        </div>
                    )}
                </Demo>

                <Demo
                    title="Kitchen ticket"
                    description="The active step lifts; finished steps are stamped."
                >
                    {(run) => (
                        <TicketSteps
                            current={Math.min(1 + (run % 4), 4)}
                            steps={[
                                { key: 'placed', label: 'Placed' },
                                { key: 'accepted', label: 'Accepted' },
                                { key: 'cooking', label: 'Cooking' },
                                { key: 'ready', label: 'Ready' },
                                { key: 'done', label: 'Served' },
                            ]}
                        />
                    )}
                </Demo>

                <Demo
                    title="Photo print"
                    description="Story photos develop like a print; the caption writes in after."
                >
                    {() => (
                        <PhotoPrint
                            className="max-w-md"
                            caption="Rakesh lights the tandoor at 10am. It takes four hours to reach heat."
                        >
                            <ArchFrame ratio="landscape">
                                <Picture
                                    mediaKey="story/tandoor"
                                    alt=""
                                    sizes="448px"
                                />
                            </ArchFrame>
                        </PhotoPrint>
                    )}
                </Demo>

                <Demo
                    title="Place cards"
                    description="Reservation times are set on the table like place cards."
                >
                    {() => (
                        <Course className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                            {placeCards.map((time, index) => (
                                <Settle
                                    key={time}
                                    tilt={index % 2 === 0 ? -2 : 2}
                                >
                                    <button
                                        type="button"
                                        className="h-14 w-full rounded-md border border-line-strong bg-surface font-semibold tabular-nums shadow-plate outline-hidden hover:border-ember focus-visible:outline-2 focus-visible:outline-ember"
                                    >
                                        {time}
                                    </button>
                                </Settle>
                            ))}
                        </Course>
                    )}
                </Demo>

                <Demo
                    title="Guest book"
                    description="Reviews land like notes in a guest book, a little askew, then straighten."
                >
                    {() => (
                        <Course className="grid gap-4 md:grid-cols-3">
                            {notes.map(([quote, who], index) => (
                                <Settle
                                    key={who}
                                    tilt={[-1.5, 1.2, -0.8][index]}
                                >
                                    <blockquote className="rounded-md bg-surface p-5 shadow-plate">
                                        <p className="font-display text-xl">
                                            “{quote}”
                                        </p>
                                        <footer className="mt-3 text-sm text-ink-muted">
                                            {who}
                                        </footer>
                                    </blockquote>
                                </Settle>
                            ))}
                        </Course>
                    )}
                </Demo>

                <Demo
                    title="The door opens"
                    description="Gallery frames uncover from a soft crop, like a door opening onto the dining room."
                >
                    {() => (
                        <DoorReveal className="max-w-2xl">
                            <ArchFrame ratio="landscape">
                                <Picture
                                    mediaKey="story/room"
                                    alt=""
                                    sizes="672px"
                                />
                            </ArchFrame>
                        </DoorReveal>
                    )}
                </Demo>

                <Demo
                    title="Receipt total"
                    description="Numbers count up like a receipt being totted up."
                >
                    {() => (
                        <dl className="grid grid-cols-3 gap-6">
                            <div>
                                <dt className="text-label text-ink-muted">
                                    Years open
                                </dt>
                                <dd className="font-display text-display-lg">
                                    <CountUp to={9} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-label text-ink-muted">
                                    Rating
                                </dt>
                                <dd className="font-display text-display-lg">
                                    <CountUp
                                        to={4.8}
                                        format={(value) => value.toFixed(1)}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-label text-ink-muted">
                                    Naan this year
                                </dt>
                                <dd className="font-display text-display-lg">
                                    <CountUp to={48210} />
                                </dd>
                            </div>
                        </dl>
                    )}
                </Demo>

                <Demo
                    title="Table marker"
                    description="The location pin drops like a table marker."
                >
                    {() => (
                        <div className="relative grid h-40 place-items-center rounded-lg bg-clay">
                            <MarkerDrop>
                                <span className="grid size-12 [transform:rotate(45deg)] place-items-center rounded-full rounded-br-none bg-ember text-ember-ink shadow-lift">
                                    <LogoMark className="size-6 [transform:rotate(-45deg)]" />
                                </span>
                            </MarkerDrop>
                        </div>
                    )}
                </Demo>

                <Demo
                    title="Cleared plates"
                    description="Dishes that don't match a filter are cleared; the rest close up."
                >
                    {() => <ClearedPlates items={items} />}
                </Demo>

                <Demo
                    title="To the basket"
                    description="A small plate travels to the basket and the badge gives one nod."
                >
                    {() => <ToTheCart />}
                </Demo>

                <Demo
                    title="Card to dish page"
                    description="The photo expands into the dish page hero (shared element)."
                >
                    {() => first && <SharedPhoto item={first} />}
                </Demo>

                <Demo
                    title="Order slip"
                    description="Toasts slide up like an order slip handed over the counter."
                >
                    {() => (
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.success('Table 7 has asked for the bill')
                            }
                        >
                            Show a toast
                        </Button>
                    )}
                </Demo>

                <Demo
                    title="Skeletons"
                    description="Loading shapes take the form of the card that is coming."
                    className="grid grid-cols-2 gap-6 md:grid-cols-3"
                >
                    {() => (
                        <>
                            <DishCardSkeleton />
                            <DishCardSkeleton />
                            <div className={cn('hidden md:block')}>
                                <DishCardSkeleton />
                            </div>
                        </>
                    )}
                </Demo>

                <p className="text-sm text-ink-muted">
                    Reduced motion turns every pattern into a 150ms fade.
                    Prices, as everywhere: <Price pence={1550} />.
                </p>
            </div>
        </>
    );
}
