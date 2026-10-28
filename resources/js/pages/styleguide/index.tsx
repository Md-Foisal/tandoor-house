import { Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { allergenCodes, AllergenIcon } from '@/components/brand/allergen-icon';
import { AllergenList } from '@/components/brand/allergen-list';
import { ArchFrame } from '@/components/brand/arch-frame';
import { DietMarks, SpiceMeter } from '@/components/brand/diet';
import { OpenStatus } from '@/components/brand/open-status';
import { Picture, PicturePlaceholder } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { QuantityStepper } from '@/components/brand/quantity-stepper';
import { SectionHeading } from '@/components/brand/section-heading';
import {
    EmptyState,
    ErrorState,
    OfflineBanner,
} from '@/components/brand/states';
import { TicketSteps } from '@/components/brand/ticket-steps';
import { DishCard, DishCardSkeleton } from '@/components/menu/dish-card';
import { DishRow, DishRowSkeleton } from '@/components/menu/dish-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import type { MenuItem } from '@/types';

const swatches = [
    ['paper', 'bg-paper'],
    ['surface', 'bg-surface'],
    ['clay', 'bg-clay'],
    ['ink', 'bg-ink'],
    ['ink-muted', 'bg-ink-muted'],
    ['line', 'bg-line'],
    ['line-strong', 'bg-line-strong'],
    ['ember', 'bg-ember'],
    ['ember-soft', 'bg-ember-soft'],
    ['turmeric', 'bg-turmeric'],
    ['turmeric-soft', 'bg-turmeric-soft'],
    ['leaf', 'bg-leaf'],
    ['leaf-soft', 'bg-leaf-soft'],
    ['chilli', 'bg-chilli'],
    ['chilli-soft', 'bg-chilli-soft'],
] as const;

const typeScale = [
    ['display-2xl', 'font-display text-display-2xl'],
    ['display-xl', 'font-display text-display-xl'],
    ['display-lg', 'font-display text-display-lg'],
    ['display-md', 'font-display text-display-md'],
    ['title', 'text-title font-semibold'],
    ['body-lg', 'text-body-lg'],
    ['body', 'text-base'],
    ['small', 'text-sm'],
    ['label', 'text-label'],
] as const;

function Section({
    title,
    id,
    children,
}: {
    title: string;
    id: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className="flex flex-col gap-6 border-t border-line pt-10"
        >
            <h2 id={id} className="font-display text-display-md">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Specimen({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <p className="text-label text-ink-muted">{label}</p>
            <div className="flex flex-wrap items-center gap-3">{children}</div>
        </div>
    );
}

export default function Styleguide({ items }: { items: MenuItem[] }) {
    const t = useT();
    const [quantity, setQuantity] = useState(2);
    const [card, row, ...rest] = items;

    return (
        <>
            <Head title={t('styleguide.title')}>
                <meta name="robots" content="noindex" />
            </Head>

            <div className="flex flex-col gap-14">
                <SectionHeading
                    as="h1"
                    size="xl"
                    eyebrow="Clay and Ember"
                    title={t('styleguide.title')}
                    description={t('styleguide.intro')}
                />

                <Section id="colour" title={t('styleguide.sections.colour')}>
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {swatches.map(([name, className]) => (
                            <li
                                key={name}
                                className="overflow-hidden rounded-md border border-line bg-surface"
                            >
                                <div className={`h-16 ${className}`} />
                                <p className="px-3 py-2 font-mono text-xs">
                                    {name}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section id="type" title={t('styleguide.sections.type')}>
                    <div className="flex flex-col gap-5">
                        {typeScale.map(([name, className]) => (
                            <div
                                key={name}
                                className="grid gap-1 md:grid-cols-[10rem_1fr] md:items-baseline"
                            >
                                <p className="font-mono text-xs text-ink-muted">
                                    {name}
                                </p>
                                <p className={className}>
                                    Charred at the edges
                                </p>
                            </div>
                        ))}
                        <p className="font-mono text-xs text-ink-muted">
                            Rozha One (display) · Hanken Grotesk (interface) ·
                            tabular figures: <Price pence={1250} />{' '}
                            <Price pence={950} /> <Price pence={10450} />
                        </p>
                    </div>
                </Section>

                <Section id="buttons" title={t('styleguide.sections.buttons')}>
                    <Specimen label="Variants">
                        <Button>Book a table</Button>
                        <Button variant="outline">View the menu</Button>
                        <Button variant="secondary">Add a note</Button>
                        <Button variant="ghost">Cancel</Button>
                        <Button variant="destructive">Reject order</Button>
                        <Button variant="link">Allergen guide</Button>
                    </Specimen>
                    <Specimen label="Sizes">
                        <Button size="lg">Pay £42.50</Button>
                        <Button>Order now</Button>
                        <Button size="sm">Edit</Button>
                    </Specimen>
                    <Specimen label="States">
                        <Button disabled>Disabled</Button>
                        <Button disabled aria-busy="true">
                            <Loader2
                                className="animate-spin"
                                aria-hidden="true"
                            />
                            Sending to the kitchen
                        </Button>
                    </Specimen>
                </Section>

                <Section id="forms" title={t('styleguide.sections.forms')}>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="sg-postcode">Postcode</Label>
                            <Input
                                id="sg-postcode"
                                placeholder="E1 6QR"
                                autoComplete="postal-code"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sg-phone">Mobile number</Label>
                            <Input
                                id="sg-phone"
                                defaultValue="07700 90"
                                aria-invalid="true"
                                aria-describedby="sg-phone-error"
                            />
                            <p
                                id="sg-phone-error"
                                className="text-sm text-chilli"
                            >
                                Enter a UK mobile number, like 07700 900123.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sg-disabled">Table</Label>
                            <Input
                                id="sg-disabled"
                                defaultValue="Table 12"
                                disabled
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sg-time">Pickup time</Label>
                            <Select defaultValue="1830">
                                <SelectTrigger
                                    id="sg-time"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1815">18:15</SelectItem>
                                    <SelectItem value="1830">18:30</SelectItem>
                                    <SelectItem value="1845">18:45</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="sg-notes">
                                Notes for the kitchen
                            </Label>
                            <Textarea
                                id="sg-notes"
                                placeholder="No coriander, please"
                            />
                        </div>
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-3">
                                <Checkbox id="sg-c1" defaultChecked />
                                <Label htmlFor="sg-c1">
                                    Extra chicken (+£3.50)
                                </Label>
                            </div>
                            <div className="flex items-center gap-3">
                                <Checkbox id="sg-c2" />
                                <Label htmlFor="sg-c2">
                                    Fresh green chilli
                                </Label>
                            </div>
                            <div className="flex items-center gap-3">
                                <Checkbox id="sg-c3" disabled />
                                <Label htmlFor="sg-c3">
                                    Extra sauce (sold out)
                                </Label>
                            </div>
                        </div>
                        <RadioGroup defaultValue="half" aria-label="Portion">
                            <div className="flex items-center gap-3">
                                <RadioGroupItem value="half" id="sg-r1" />
                                <Label htmlFor="sg-r1">Half, £12.50</Label>
                            </div>
                            <div className="flex items-center gap-3">
                                <RadioGroupItem value="full" id="sg-r2" />
                                <Label htmlFor="sg-r2">Full, £19.50</Label>
                            </div>
                        </RadioGroup>
                        <div className="flex items-center gap-3">
                            <Switch id="sg-s1" defaultChecked />
                            <Label htmlFor="sg-s1">Auto-accept orders</Label>
                        </div>
                        <div className="flex items-center gap-3">
                            <QuantityStepper
                                value={quantity}
                                onChange={setQuantity}
                                itemName="Butter Naan"
                            />
                            <QuantityStepper
                                value={1}
                                onChange={() => undefined}
                                itemName="Mango Lassi"
                                disabled
                            />
                        </div>
                    </div>
                </Section>

                <Section id="menu" title={t('styleguide.sections.menu')}>
                    <Specimen label="Marks">
                        <DietMarks vegetarian vegan={false} />
                        <DietMarks vegetarian vegan />
                        <SpiceMeter level={1} showLabel />
                        <SpiceMeter level={3} showLabel />
                        <Badge>New</Badge>
                        <Badge variant="turmeric">{t('ui.special')}</Badge>
                        <Badge variant="leaf">Ready</Badge>
                        <Badge variant="destructive">Late</Badge>
                        <Badge variant="outline">Table 4</Badge>
                        <Badge variant="solid">Delivery</Badge>
                    </Specimen>
                    <Specimen label="Open status">
                        <OpenStatus status={{ isOpen: true, until: '22:30' }} />
                        <OpenStatus
                            status={{
                                isOpen: false,
                                opensAt: 'tomorrow at 12:00',
                            }}
                        />
                    </Specimen>
                    <div className="grid gap-10 lg:grid-cols-[22rem_1fr]">
                        <div className="flex flex-col gap-6">
                            {card && (
                                <DishCard
                                    item={card}
                                    href={`/menu/${card.slug}`}
                                />
                            )}
                            <DishCardSkeleton />
                        </div>
                        <div className="divide-y divide-line">
                            {row && (
                                <DishRow
                                    item={row}
                                    href={`/menu/${row.slug}`}
                                    onAdd={() => undefined}
                                />
                            )}
                            {rest.map((item) => (
                                <DishRow
                                    key={item.id}
                                    item={item}
                                    href={`/menu/${item.slug}`}
                                    onAdd={() => undefined}
                                />
                            ))}
                            <DishRowSkeleton />
                        </div>
                    </div>
                    <Specimen label="Arch frame and placeholder">
                        <ArchFrame className="w-40">
                            {card && (
                                <Picture
                                    mediaKey={card.image}
                                    alt=""
                                    sizes="160px"
                                />
                            )}
                        </ArchFrame>
                        <ArchFrame className="w-40">
                            <PicturePlaceholder />
                        </ArchFrame>
                        <ArchFrame ratio="landscape" className="w-64">
                            <Picture
                                mediaKey="story/tandoor"
                                alt=""
                                sizes="256px"
                            />
                        </ArchFrame>
                    </Specimen>
                </Section>

                <Section
                    id="allergens"
                    title={t('styleguide.sections.allergens')}
                >
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                        {allergenCodes.map((code) => (
                            <li
                                key={code}
                                className="flex flex-col items-center gap-2 rounded-md border border-line bg-surface p-4 text-center text-sm"
                            >
                                <AllergenIcon
                                    code={code}
                                    className="size-8 text-ember"
                                />
                                {t(`allergens.${code}`)}
                            </li>
                        ))}
                    </ul>
                    <AllergenList allergens={['gluten', 'milk', 'tree_nuts']} />
                    <AllergenList allergens={[]} />
                </Section>

                <Section
                    id="overlays"
                    title={t('styleguide.sections.overlays')}
                >
                    <Tabs defaultValue="delivery">
                        <TabsList>
                            <TabsTrigger value="delivery">Delivery</TabsTrigger>
                            <TabsTrigger value="pickup">Pickup</TabsTrigger>
                            <TabsTrigger value="table">
                                At the table
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent
                            value="delivery"
                            className="text-ink-muted"
                        >
                            We deliver within 3 miles. Minimum order £15.
                        </TabsContent>
                        <TabsContent value="pickup" className="text-ink-muted">
                            Collect from the pass, 14 Hanbury Street.
                        </TabsContent>
                        <TabsContent value="table" className="text-ink-muted">
                            Scan the code on your table.
                        </TabsContent>
                    </Tabs>
                    <div className="flex flex-wrap gap-3">
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline">Open dialog</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Cancel this order?
                                    </DialogTitle>
                                    <DialogDescription>
                                        The kitchen hasn't started yet, so
                                        you'll get a full refund.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="ghost">Keep order</Button>
                                    <Button variant="destructive">
                                        Cancel order
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline">Open sheet</Button>
                            </SheetTrigger>
                            <SheetContent
                                side="bottom"
                                className="rounded-t-xl"
                            >
                                <SheetHeader>
                                    <SheetTitle>Your basket</SheetTitle>
                                    <SheetDescription>
                                        Three dishes, ready in about 25 minutes.
                                    </SheetDescription>
                                </SheetHeader>
                            </SheetContent>
                        </Sheet>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                toast.success(
                                    'Butter Naan added to your basket',
                                )
                            }
                        >
                            Success toast
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                toast.error(
                                    "We couldn't reach the kitchen. Try again.",
                                )
                            }
                        >
                            Error toast
                        </Button>
                    </div>
                </Section>

                <Section id="states" title={t('styleguide.sections.states')}>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="rounded-lg border border-line bg-surface">
                            <EmptyState
                                description="Add a dish from the menu and it will wait here."
                                action={
                                    <Button variant="outline">
                                        See the menu
                                    </Button>
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-4">
                            <ErrorState onRetry={() => undefined} />
                            <div className="overflow-hidden rounded-md">
                                <OfflineBanner forceOffline />
                            </div>
                            <p
                                role="status"
                                className="rounded-md bg-leaf-soft px-4 py-3 font-semibold text-leaf"
                            >
                                Booked. We've sent the details to your email.
                            </p>
                        </div>
                    </div>
                </Section>

                <Section
                    id="progress"
                    title={t('styleguide.sections.progress')}
                >
                    <div className="grid gap-8 lg:grid-cols-2">
                        <TicketSteps
                            current={2}
                            steps={[
                                {
                                    key: 'placed',
                                    label: 'Order placed',
                                    detail: '18:02',
                                },
                                {
                                    key: 'accepted',
                                    label: 'Accepted',
                                    detail: '18:03',
                                },
                                {
                                    key: 'cooking',
                                    label: 'In the tandoor',
                                    detail: 'About 14 minutes',
                                },
                                { key: 'ready', label: 'Ready' },
                                { key: 'out', label: 'On its way' },
                            ]}
                        />
                        <TicketSteps
                            orientation="horizontal"
                            current={1}
                            steps={[
                                { key: 'basket', label: 'Basket' },
                                { key: 'details', label: 'Details' },
                                { key: 'pay', label: 'Pay' },
                            ]}
                        />
                    </div>
                </Section>

                <Section
                    id="surfaces"
                    title={t('styleguide.sections.surfaces')}
                >
                    <div className="flex flex-wrap gap-6">
                        <div className="grid h-24 w-32 place-items-center rounded-sm border border-line bg-surface text-xs">
                            sm 6px
                        </div>
                        <div className="grid h-24 w-32 place-items-center rounded-md border border-line bg-surface text-xs">
                            md 10px
                        </div>
                        <div className="grid h-24 w-32 place-items-center rounded-lg bg-surface text-xs shadow-plate">
                            lg · plate
                        </div>
                        <div className="grid h-24 w-32 place-items-center rounded-lg bg-surface text-xs shadow-lift">
                            lg · lift
                        </div>
                        <div className="grid h-32 w-28 place-items-center rounded-arch bg-clay text-xs">
                            arch
                        </div>
                    </div>
                </Section>
            </div>
        </>
    );
}
