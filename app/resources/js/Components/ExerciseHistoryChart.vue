<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    // [{ date: 'YYYY-MM-DD', value: number, weight: number, reps: number }] performed_on 昇順
    points: {
        type: Array,
        required: true,
    },
    // '1rm' | 'reps' | 'weight' など。'reps' のときだけレップ数専用の
    // 表示分岐が効く。それ以外は既定で「推定1RM」文言を使うが、label を
    // 渡すとそちらを使う(体重推移グラフでの再利用向け。Issue #21)。
    metric: {
        type: String,
        required: true,
    },
    unit: {
        type: String,
        default: 'kg',
    },
    // 例: '体重'。指定すると tooltip / 終点ラベル / aria-label の文言に
    // 「推定1RM」の代わりにこちらを使う。種目別履歴以外(体重推移)で
    // このコンポーネントを再利用するための最小限の拡張。
    label: {
        type: String,
        default: null,
    },
});

/*
 * SVG を自前で描く。データ点数は種目1つ・全期間でもせいぜい数十件で、
 * ズーム/パン等の対話操作も要らないため、Chart.js 等のライブラリ
 * (十数〜数十kB + 学習コスト)を入れるほどの規模ではないと判断した。
 * 色は Tailwind の @theme トークンをそのまま var(--color-*) として
 * 埋め込むため、ライト/ダークの切り替えに追従する JS 分岐が要らない。
 */

const PAD_LEFT = 40;
const PAD_RIGHT = 20;
const PAD_TOP = 16;
const PAD_BOTTOM = 28;
const PLOT_HEIGHT = 180;
const POINT_GAP = 56;
const MIN_WIDTH = 280;
// 親要素の幅が十分あるときに点と点の間を広げてよい上限。これがないと
// 点数が少ないグラフを親幅いっぱいに引き伸ばした際に間延びして見える
// (詳細は Issue #30 参照)。
const MAX_POINT_GAP = 120;

// 高さは常に固定。幅だけを親要素に合わせて可変にする(縦横比を保って
// 拡大縮小するのではなく、SVG のピクセル単位そのものを実測幅で描き直す)。
// これにより、幅が伸びても縦長になったり文字・点が歪んだりしない。
const chartHeight = PAD_TOP + PLOT_HEIGHT + PAD_BOTTOM;

// 点の配置に使う「自然な最小幅」。スマホ等、親要素がこれより狭い場合は
// この値をそのまま描画幅として使い、親の overflow-x-auto で横スクロール
// させる(従来の挙動を維持)。
const chartWidth = computed(() => {
    const span = Math.max(props.points.length - 1, 0) * POINT_GAP;
    return Math.max(MIN_WIDTH, PAD_LEFT + PAD_RIGHT + span + 24);
});

// 親要素の幅がどれだけ大きくても、これ以上には広げない上限。
const maxChartWidth = computed(() => {
    const span = Math.max(props.points.length - 1, 0) * MAX_POINT_GAP;
    return Math.max(MIN_WIDTH, PAD_LEFT + PAD_RIGHT + span + 24);
});

// 実測した親要素(.overflow-x-auto の div)の幅。ResizeObserver 未対応環境や
// マウント前は 0 のままとし、その場合は chartWidth(自然な最小幅)にフォールバックする。
const containerRef = ref(null);
const containerWidth = ref(0);
const resizeObserver =
    typeof ResizeObserver === 'undefined'
        ? null
        : new ResizeObserver(([entry]) => {
              if (entry) {
                  containerWidth.value = entry.contentRect.width;
              }
          });

// v-if で表示・非表示が切り替わる要素なので、ref の付け外しを watch で
// 検知して observe/unobserve する(onMounted 一回だけだと、記録が0件→
// 複数件に変わって要素が後から現れたケースを取りこぼす)。
watch(
    containerRef,
    (el, prevEl) => {
        if (!resizeObserver) {
            return;
        }
        if (prevEl) {
            resizeObserver.unobserve(prevEl);
        }
        if (el) {
            containerWidth.value = el.clientWidth;
            resizeObserver.observe(el);
        } else {
            containerWidth.value = 0;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
});

// 実際に描画する幅。「自然な最小幅」と「上限」の間で、親要素の幅に合わせる。
// スクロールのヒントは「実際にはみ出すとき」だけ出す。
// 点数だけで判定すると、デスクトップでグラフが収まっているのに
// 「横にスクロールできます」と出てしまう。
const isScrollable = computed(
    () => containerWidth.value > 0 && renderWidth.value > containerWidth.value + 1,
);

const renderWidth = computed(() => {
    if (containerWidth.value <= 0) {
        return chartWidth.value;
    }
    return Math.min(Math.max(containerWidth.value, chartWidth.value), maxChartWidth.value);
});

const domain = computed(() => {
    if (props.points.length === 0) {
        return [0, 1];
    }

    const values = props.points.map((p) => p.value);
    const min = Math.min(...values);
    const max = Math.max(...values);

    // 記録の範囲から動的に決める(0起点にすると変化が潰れて見える)。
    // 全点が同値・1点のみの場合は値の周りに一定幅を持たせる。
    const basePad = props.metric === 'reps' ? 2 : 2.5;
    let lo, hi;
    if (max === min) {
        const pad = Math.max(Math.abs(min) * 0.1, basePad);
        lo = min - pad;
        hi = max + pad;
    } else {
        const pad = (max - min) * 0.15;
        lo = min - pad;
        hi = max + pad;
    }

    // レップ数は負の値を取り得ないので、パディングで下限が0を割ったら0で止める。
    if (props.metric === 'reps' && lo < 0) {
        lo = 0;
    }

    return [lo, hi];
});

function xAt(index) {
    const n = props.points.length;
    if (n <= 1) {
        return PAD_LEFT;
    }
    const span = renderWidth.value - PAD_LEFT - PAD_RIGHT - 24;
    const gap = span / (n - 1);
    return PAD_LEFT + index * gap;
}

function yAt(value) {
    const [lo, hi] = domain.value;
    const ratio = (value - lo) / (hi - lo);
    return PAD_TOP + (1 - ratio) * PLOT_HEIGHT;
}

const gridLines = computed(() => {
    const [lo, hi] = domain.value;
    const steps = 4;
    return Array.from({ length: steps + 1 }, (_, i) => {
        const value = lo + ((hi - lo) * i) / steps;
        return { value, y: yAt(value) };
    });
});

const polylinePoints = computed(() =>
    props.points.map((p, i) => `${xAt(i)},${yAt(p.value)}`).join(' '),
);

const xTickIndices = computed(() => {
    const n = props.points.length;
    if (n <= 6) {
        return Array.from({ length: n }, (_, i) => i);
    }
    const count = 5;
    const indices = new Set();
    for (let i = 0; i < count; i++) {
        indices.add(Math.round((i * (n - 1)) / (count - 1)));
    }
    return Array.from(indices).sort((a, b) => a - b);
});

function shortDate(dateStr) {
    const [, month, day] = dateStr.split('-');
    return `${Number(month)}/${Number(day)}`;
}

function tooltipFor(point) {
    if (props.label) {
        return `${point.date} ${props.label} ${formatNumber(point.value)}${props.unit}`;
    }
    const base =
        props.metric === 'reps' ? `${point.reps}回` : `推定1RM ${formatNumber(point.value)}kg`;
    const weightNote = point.weight > 0 ? `(加重 ${formatNumber(point.weight)}kg)` : '';
    return `${point.date} ${base}${weightNote}`;
}

const lastIndex = computed(() => props.points.length - 1);
</script>

<template>
    <div v-if="points.length === 0" class="py-8 text-center text-sm text-ink-2">
        この期間の記録がありません。
    </div>

    <div v-else ref="containerRef" class="overflow-x-auto">
        <p v-if="isScrollable" class="label-micro mb-1 text-[10px] text-ink-3">
            ← 横にスクロールできます →
        </p>
        <svg
            :viewBox="`0 0 ${renderWidth} ${chartHeight}`"
            :width="renderWidth"
            :height="chartHeight"
            role="img"
            :aria-label="`${label ?? (metric === 'reps' ? 'レップ数' : '推定1RM')}の推移グラフ`"
        >
            <!-- gridlines -->
            <g v-for="(line, i) in gridLines" :key="i">
                <line
                    :x1="PAD_LEFT"
                    :y1="line.y"
                    :x2="renderWidth - PAD_RIGHT"
                    :y2="line.y"
                    stroke="var(--color-line)"
                    stroke-width="1"
                />
                <text
                    :x="PAD_LEFT - 6"
                    :y="line.y + 3"
                    text-anchor="end"
                    font-size="10"
                    fill="var(--color-ink-3)"
                    class="font-mono tabular-nums"
                >
                    {{ formatNumber(Math.round(line.value * 10) / 10) }}
                </text>
            </g>

            <!-- line -->
            <polyline
                v-if="points.length > 1"
                :points="polylinePoints"
                fill="none"
                stroke="var(--color-accent)"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <!-- points -->
            <g v-for="(point, i) in points" :key="point.date + '-' + i">
                <circle
                    :cx="xAt(i)"
                    :cy="yAt(point.value)"
                    :r="i === lastIndex ? 4 : 3"
                    fill="var(--color-accent)"
                    :stroke="i === lastIndex ? 'var(--color-surface)' : 'none'"
                    stroke-width="2"
                >
                    <title>{{ tooltipFor(point) }}</title>
                </circle>
                <!-- 加重ありの自重種目は、レップ数を主軸にしつつ各点に加重を併記する -->
                <text
                    v-if="metric === 'reps' && point.weight > 0"
                    :x="xAt(i)"
                    :y="yAt(point.value) - 9"
                    text-anchor="middle"
                    font-size="9"
                    fill="var(--color-ink-2)"
                    class="font-mono"
                >
                    +{{ formatNumber(point.weight) }}
                </text>
            </g>

            <!-- endpoint label -->
            <text
                v-if="points.length > 0"
                :x="xAt(lastIndex) - 6"
                :y="yAt(points[lastIndex].value) - 12"
                text-anchor="end"
                font-size="12"
                font-weight="700"
                fill="var(--color-accent)"
                class="font-display tabular-nums"
            >
                {{ formatNumber(points[lastIndex].value)
                }}{{ label ? unit : metric === 'reps' ? '回' : 'kg' }}
            </text>

            <!-- x axis -->
            <line
                :x1="PAD_LEFT"
                :y1="PAD_TOP + PLOT_HEIGHT"
                :x2="renderWidth - PAD_RIGHT"
                :y2="PAD_TOP + PLOT_HEIGHT"
                stroke="var(--color-ink-3)"
                stroke-width="1"
            />
            <text
                v-for="i in xTickIndices"
                :key="'x-' + i"
                :x="xAt(i)"
                :y="PAD_TOP + PLOT_HEIGHT + 18"
                text-anchor="middle"
                font-size="10"
                fill="var(--color-ink-3)"
                class="font-mono"
            >
                {{ shortDate(points[i].date) }}
            </text>
        </svg>
    </div>
</template>
