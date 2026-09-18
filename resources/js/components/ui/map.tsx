import DottedMap from 'dotted-map';
import { motion } from 'framer-motion';
import { Minus, Plus, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export type MapPin = {
    id: string;
    lat: number;
    lng: number;
    label: string;
    value: number;
};

type MapProps = {
    pins: MapPin[];
    /** Pin that every other active pin draws an arc to. */
    targetId?: string;
    lineColor?: string;
    /** Labels always shown for this many busiest pins; the rest appear when zoomed in. */
    labelCount?: number;
};

type View = { x: number; y: number; w: number; h: number };

// Pins are projected with dotted-map itself so they sit on the dots.
const map = new DottedMap({ height: 100, grid: 'diagonal' });
const W = map.image.width;
const H = map.image.height;
const fullView: View = { x: 0, y: 0, w: W, h: H };
const maxZoom = 8;

const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), max);

const clampView = (view: View): View => ({
    ...view,
    x: clamp(view.x, 0, W - view.w),
    y: clamp(view.y, 0, H - view.h),
});

const arcPath = (a: { x: number; y: number }, b: { x: number; y: number }) => {
    const lift = Math.hypot(b.x - a.x, b.y - a.y) * 0.3;

    return `M ${a.x} ${a.y} Q ${(a.x + b.x) / 2} ${Math.min(a.y, b.y) - lift} ${b.x} ${b.y}`;
};

export function WorldMap({
    pins,
    targetId,
    lineColor = '#0ea5e9',
    labelCount = 10,
}: MapProps) {
    const svgRef = useRef<SVGSVGElement>(null);
    const drag = useRef<{ clientX: number; clientY: number; view: View }>(null);
    const [view, setView] = useState(fullView);
    const [width, setWidth] = useState(800);
    const { resolvedAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    const mapImage = useMemo(
        () =>
            `data:image/svg+xml;utf8,${encodeURIComponent(
                map.getSVG({
                    radius: 0.22,
                    color: dark ? '#FFFFFF40' : '#00000040',
                    shape: 'circle',
                    backgroundColor: dark ? 'black' : 'white',
                }),
            )}`,
        [dark],
    );

    const projected = useMemo(() => {
        const ranked = pins
            .filter((pin) => pin.value > 0)
            .sort((a, b) => b.value - a.value);
        const max = ranked[0]?.value ?? 1;

        return pins.flatMap((pin) => {
            const point = map.getPin(pin);

            if (!point) {
                return [];
            }

            return [
                {
                    ...pin,
                    x: point.x,
                    y: point.y,
                    rank: ranked.indexOf(pin),
                    radius:
                        pin.value > 0 ? 3 + 5 * Math.sqrt(pin.value / max) : 2,
                },
            ];
        });
    }, [pins]);

    const target = projected.find((pin) => pin.id === targetId);
    const zoom = W / view.w;
    // Pin groups are drawn in screen pixels, whatever the zoom or container width.
    const pixel = view.w / width;

    const toMap = (clientX: number, clientY: number) => {
        const rect = svgRef.current!.getBoundingClientRect();

        return {
            x: view.x + ((clientX - rect.left) / rect.width) * view.w,
            y: view.y + ((clientY - rect.top) / rect.height) * view.h,
        };
    };

    const zoomAt = (
        factor: number,
        cx = view.x + view.w / 2,
        cy = view.y + view.h / 2,
    ) =>
        setView((current) => {
            const w = clamp(current.w / factor, W / maxZoom, W);
            const h = (w * H) / W;

            return clampView({
                x: cx - ((cx - current.x) * w) / current.w,
                y: cy - ((cy - current.y) * h) / current.h,
                w,
                h,
            });
        });

    useEffect(() => {
        const observer = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width),
        );
        observer.observe(svgRef.current!);

        return () => observer.disconnect();
    }, []);

    // Pinch (reported as ctrl+wheel) or ⌘/Ctrl+scroll zooms; plain scroll keeps scrolling the page.
    useEffect(() => {
        const svg = svgRef.current!;
        const onWheel = (event: WheelEvent) => {
            if (!event.ctrlKey && !event.metaKey) {
                return;
            }

            event.preventDefault();
            const { x, y } = toMap(event.clientX, event.clientY);
            zoomAt(Math.exp(-event.deltaY * 0.01), x, y);
        };
        svg.addEventListener('wheel', onWheel, { passive: false });

        return () => svg.removeEventListener('wheel', onWheel);
    });

    const onPointerDown = (event: PointerEvent<SVGSVGElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            clientX: event.clientX,
            clientY: event.clientY,
            view,
        };
    };

    const onPointerMove = (event: PointerEvent<SVGSVGElement>) => {
        if (!drag.current) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const start = drag.current;
        setView(
            clampView({
                ...start.view,
                x:
                    start.view.x -
                    ((event.clientX - start.clientX) / rect.width) *
                        start.view.w,
                y:
                    start.view.y -
                    ((event.clientY - start.clientY) / rect.height) *
                        start.view.h,
            }),
        );
    };

    const endDrag = () => (drag.current = null);

    return (
        <div className="relative overflow-hidden rounded-lg bg-white dark:bg-black">
            <svg
                ref={svgRef}
                viewBox={`${view.x} ${view.y} ${view.w} ${view.h}`}
                className="block w-full cursor-grab select-none active:cursor-grabbing"
                style={{
                    aspectRatio: `${W} / ${H}`,
                    touchAction: zoom > 1 ? 'none' : 'pan-y',
                }}
                role="img"
                aria-label="World map of news by country"
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={endDrag}
                onPointerCancel={endDrag}
                onDoubleClick={(event) => {
                    const { x, y } = toMap(event.clientX, event.clientY);
                    zoomAt(2, x, y);
                }}
            >
                <image href={mapImage} width={W} height={H} />

                {target &&
                    projected
                        .filter((pin) => pin.value > 0 && pin !== target)
                        .map((pin, i) => {
                            const d = arcPath(pin, target);
                            const timing = {
                                duration: 3,
                                times: [0, 0.6, 1],
                                delay: i * 0.3,
                                repeat: Infinity,
                                repeatDelay: 1,
                                ease: 'easeInOut' as const,
                            };

                            return (
                                <g
                                    key={`arc-${pin.id}`}
                                    className="pointer-events-none"
                                >
                                    <motion.path
                                        d={d}
                                        fill="none"
                                        stroke={lineColor}
                                        strokeOpacity={0.8}
                                        strokeWidth={1.5 * pixel}
                                        initial={{ pathLength: 0 }}
                                        animate={{ pathLength: [0, 1, 1] }}
                                        transition={timing}
                                    />
                                    <motion.circle
                                        r={3 * pixel}
                                        fill={lineColor}
                                        initial={{
                                            offsetDistance: '0%',
                                            opacity: 0,
                                        }}
                                        animate={{
                                            offsetDistance: [
                                                '0%',
                                                '100%',
                                                '100%',
                                            ],
                                            opacity: [0, 1, 0],
                                        }}
                                        transition={timing}
                                        style={{ offsetPath: `path('${d}')` }}
                                    />
                                </g>
                            );
                        })}

                {projected.map((pin) => {
                    const active = pin.value > 0;
                    const isTarget = pin === target;
                    const showLabel =
                        active &&
                        (isTarget || pin.rank < labelCount || zoom >= 3);

                    return (
                        <g
                            key={pin.id}
                            transform={`translate(${pin.x} ${pin.y}) scale(${pixel})`}
                        >
                            <title>
                                {`${pin.label}: ${pin.value} ${pin.value === 1 ? 'item' : 'items'}`}
                            </title>
                            {/* Larger invisible hit area for the hover tooltip */}
                            <circle r={pin.radius + 4} fill="transparent" />
                            <circle
                                r={pin.radius}
                                fill={lineColor}
                                fillOpacity={active ? 1 : 0.35}
                            />
                            {active && (
                                <circle
                                    r={pin.radius}
                                    fill={lineColor}
                                    className="pointer-events-none"
                                >
                                    <animate
                                        attributeName="r"
                                        from={pin.radius}
                                        to={pin.radius * 3}
                                        dur="2s"
                                        repeatCount="indefinite"
                                    />
                                    <animate
                                        attributeName="opacity"
                                        from="0.5"
                                        to="0"
                                        dur="2s"
                                        repeatCount="indefinite"
                                    />
                                </circle>
                            )}
                            {showLabel && (
                                <text
                                    // Target label goes below its pin so it doesn't collide with close neighbours.
                                    y={
                                        isTarget
                                            ? pin.radius + 14
                                            : -pin.radius - 6
                                    }
                                    textAnchor="middle"
                                    paintOrder="stroke"
                                    strokeWidth={4}
                                    strokeLinejoin="round"
                                    className="pointer-events-none fill-black stroke-white text-[11px] font-medium dark:fill-white dark:stroke-black"
                                >
                                    {pin.label} · {pin.value}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>

            <div className="absolute top-2 right-2 flex flex-col gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Zoom in"
                    disabled={zoom >= maxZoom}
                    onClick={() => zoomAt(2)}
                >
                    <Plus />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Zoom out"
                    disabled={zoom <= 1}
                    onClick={() => zoomAt(0.5)}
                >
                    <Minus />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Reset zoom"
                    disabled={zoom <= 1}
                    onClick={() => setView(fullView)}
                >
                    <RotateCcw />
                </Button>
            </div>
            <p className="text-muted-foreground pointer-events-none absolute bottom-2 left-3 hidden text-xs sm:block">
                Drag to pan · pinch, ⌘/Ctrl + scroll or double-click to zoom
            </p>
        </div>
    );
}
