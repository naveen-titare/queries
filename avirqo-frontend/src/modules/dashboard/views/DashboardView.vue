<template>
  <AppLayout>
    <main class="dashboard-page">
      <section class="dashboard-hero">
        <div>
          <p class="eyebrow">{{ data?.financial_year?.label || 'Financial year' }}</p>
          <h1>Dashboard</h1>
          <p>Track orders, voucher value, top customers and top brands for the active financial year.</p>
        </div>
        <button class="avq-btn-ghost" @click="loadDashboard" :disabled="loading">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </section>

      <div v-if="error" class="dashboard-alert">
        {{ error }}
      </div>

      <section class="dashboard-grid">
        <DashboardCard
          title="Orders"
          subtitle="Order value vs discounts and service charge"
          :period="periods.orders_period"
          :period-options="periodOptions"
          :range="data?.orders?.range?.label"
          @change-period="setPeriod('orders_period', $event)"
        >
          <div class="metric-row">
            <Metric label="Order value" :value="money(data?.orders?.summary?.order_value)" />
            <Metric label="Discount" :value="money(data?.orders?.summary?.discount)" />
            <Metric label="Service charge" :value="money(data?.orders?.summary?.service_charge)" />
          </div>
          <BarChart
            :items="data?.orders?.timeline || []"
            mode="orders"
            empty-text="No orders found for this period."
          />
        </DashboardCard>

        <DashboardCard
          title="Vouchers sent"
          subtitle="Total voucher denomination value delivered"
          :period="periods.vouchers_period"
          :period-options="periodOptions"
          :range="data?.vouchers?.range?.label"
          @change-period="setPeriod('vouchers_period', $event)"
        >
          <div class="metric-row single">
            <Metric label="Voucher amount" :value="money(data?.vouchers?.summary?.voucher_amount)" />
          </div>
          <BarChart
            :items="data?.vouchers?.timeline || []"
            mode="vouchers"
            empty-text="No vouchers sent for this period."
          />
        </DashboardCard>

        <DashboardCard
          title="Top customers"
          subtitle="Highest order value customers"
          :period="periods.customers_period"
          :period-options="periodOptions"
          :range="data?.customers?.range?.label"
          @change-period="setPeriod('customers_period', $event)"
        >
          <RankList
            :items="data?.customers?.top_customers || []"
            name-key="company_name"
            empty-text="No customer orders found for this period."
          />
        </DashboardCard>

        <DashboardCard
          title="Top brands"
          subtitle="Highest order value brands"
          :period="periods.brands_period"
          :period-options="periodOptions"
          :range="data?.brands?.range?.label"
          @change-period="setPeriod('brands_period', $event)"
        >
          <RankList
            :items="data?.brands?.top_brands || []"
            name-key="brand_name"
            empty-text="No brand orders found for this period."
          />
        </DashboardCard>
      </section>
    </main>
  </AppLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import AppLayout from '../../shared/components/AppLayout.vue';
import dashboardApi from '../api/dashboardApi';

const periodOptions = [
  { value: 'week', label: 'Week' },
  { value: 'bi-weekly', label: 'Bi-weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'half-yearly', label: 'Half yearly' },
  { value: 'yearly', label: 'Yearly' },
];

const periods = reactive({
  orders_period: 'week',
  vouchers_period: 'week',
  customers_period: 'week',
  brands_period: 'week',
});

const data = ref(null);
const loading = ref(false);
const error = ref('');

function money(value) {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

async function loadDashboard() {
  loading.value = true;
  error.value = '';

  try {
    const response = await dashboardApi.summary({ ...periods });
    data.value = response.data;
  } catch (err) {
    error.value = err?.response?.data?.message || 'Unable to load dashboard right now.';
  } finally {
    loading.value = false;
  }
}

function setPeriod(key, value) {
  periods[key] = value;
  loadDashboard();
}

onMounted(loadDashboard);

const DashboardCard = defineComponent({
  props: {
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    period: { type: String, required: true },
    periodOptions: { type: Array, required: true },
    range: { type: String, default: '' },
  },
  emits: ['change-period'],
  setup(props, { emit, slots }) {
    return () => h('article', { class: 'dashboard-card' }, [
      h('div', { class: 'card-head' }, [
        h('div', [
          h('h2', props.title),
          h('p', props.subtitle),
          props.range ? h('span', { class: 'range-pill' }, props.range) : null,
        ]),
        h('select', {
          class: 'period-select',
          value: props.period,
          onChange: (event) => emit('change-period', event.target.value),
        }, props.periodOptions.map((option) => h('option', { value: option.value }, option.label))),
      ]),
      slots.default?.(),
    ]);
  },
});

const Metric = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: String, required: true },
  },
  setup(props) {
    return () => h('div', { class: 'metric' }, [
      h('span', props.label),
      h('strong', props.value),
    ]);
  },
});

const BarChart = defineComponent({
  props: {
    items: { type: Array, default: () => [] },
    mode: { type: String, required: true },
    emptyText: { type: String, required: true },
  },
  setup(props) {
    const maxValue = computed(() => Math.max(
      1,
      ...props.items.map((item) => props.mode === 'vouchers'
        ? Number(item.voucher_amount || 0)
        : Math.max(Number(item.order_value || 0), Number(item.discount || 0), Number(item.service_charge || 0))),
    ));

    const hasData = computed(() => props.items.some((item) => (
      Number(item.order_value || 0) ||
      Number(item.discount || 0) ||
      Number(item.service_charge || 0) ||
      Number(item.voucher_amount || 0)
    )));

    function height(value) {
      return `${Math.max(4, (Number(value || 0) / maxValue.value) * 150)}px`;
    }

    function tooltipRows(item) {
      if (props.mode === 'vouchers') {
        return [
          ['Voucher amount', money(item.voucher_amount)],
        ];
      }

      return [
        ['Order value', money(item.order_value)],
        ['Discount', money(item.discount)],
        ['Service charge', money(item.service_charge)],
      ];
    }

    return () => {
      if (!props.items.length || !hasData.value) {
        return h('div', { class: 'empty-state' }, props.emptyText);
      }

      return h('div', { class: 'bar-chart' }, [
        h('div', {
          class: 'bar-chart-inner',
          style: { width: props.items.length > 7 ? `${props.items.length * 74}px` : '100%' },
        }, props.items.map((item) => h('div', { class: 'bar-group' }, [
          h('div', { class: 'chart-tooltip' }, [
            h('strong', item.label),
            ...tooltipRows(item).map(([label, value]) => h('div', { class: 'tooltip-row' }, [
              h('span', label),
              h('b', value),
            ])),
          ]),
          props.mode === 'vouchers'
            ? h('div', { class: 'bar voucher', style: { height: height(item.voucher_amount) } })
            : h('div', { class: 'stack' }, [
              h('div', { class: 'bar order', style: { height: height(item.order_value) } }),
              h('div', { class: 'bar discount', style: { height: height(item.discount) } }),
              h('div', { class: 'bar charge', style: { height: height(item.service_charge) } }),
            ]),
          h('span', item.label),
        ]))),
      ]);
    };
  },
});

const RankList = defineComponent({
  props: {
    items: { type: Array, default: () => [] },
    nameKey: { type: String, required: true },
    emptyText: { type: String, required: true },
  },
  setup(props) {
    return () => {
      if (!props.items.length) {
        return h('div', { class: 'empty-state' }, props.emptyText);
      }

      return h('div', { class: 'rank-list' }, props.items.map((item) => h('div', { class: 'rank-row' }, [
        h('span', { class: 'rank' }, `#${item.rank}`),
        h('div', { class: 'rank-main' }, [
          h('strong', item[props.nameKey]),
          h('span', `${item.orders_count} order${Number(item.orders_count) === 1 ? '' : 's'}`),
        ]),
        h('span', { class: 'rank-value' }, money(item.order_value)),
      ])));
    };
  },
});
</script>

<style>
.dashboard-page {
  padding: 28px;
}

.dashboard-hero {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: flex-start;
  margin-bottom: 22px;
}

.eyebrow {
  color: var(--teal-deep);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.dashboard-hero h1 {
  font-family: var(--fd);
  font-size: 36px;
  line-height: 1;
  margin-bottom: 8px;
}

.dashboard-hero p {
  color: var(--ink-muted);
  font-size: 15px;
}

.dashboard-alert {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 12px;
  color: #9a3412;
  padding: 12px 14px;
  margin-bottom: 16px;
  font-weight: 700;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.dashboard-card {
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 16px 40px rgba(8, 80, 65, 0.04);
}

.card-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 18px;
}

.card-head h2 {
  font-size: 20px;
  margin-bottom: 4px;
}

.card-head p {
  color: var(--ink-muted);
  font-size: 13px;
  margin-bottom: 8px;
}

.range-pill {
  display: inline-flex;
  background: var(--teal-pale);
  color: var(--teal-deep);
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 700;
}

.period-select {
  border: 1px solid var(--border-2);
  border-radius: 10px;
  padding: 9px 12px;
  min-width: 140px;
  font: inherit;
  font-weight: 700;
  background: #fff;
  color: var(--ink);
}

.metric-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 18px;
}

.metric-row.single {
  grid-template-columns: 1fr;
}

.metric {
  background: var(--surface-2);
  border-radius: 14px;
  padding: 12px;
}

.metric span {
  display: block;
  color: var(--ink-muted);
  font-size: 12px;
  margin-bottom: 5px;
}

.metric strong {
  font-size: 20px;
  color: var(--teal-deep);
}

.bar-chart {
  min-height: 210px;
  border-top: 1px solid var(--border-2);
  padding-top: 64px;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: thin;
  scrollbar-color: var(--border-2) transparent;
}

.bar-chart-inner {
  min-width: 100%;
  min-height: 155px;
  display: flex;
  align-items: flex-end;
  gap: 12px;
}

.bar-group {
  position: relative;
  flex: 1;
  min-width: 56px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.chart-tooltip {
  position: absolute;
  bottom: calc(100% - 26px);
  left: 50%;
  transform: translate(-50%, 8px);
  min-width: 190px;
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: 0 18px 42px rgba(8, 80, 65, 0.16);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
  z-index: 5;
}

.chart-tooltip strong {
  display: block;
  color: var(--ink);
  font-size: 12px;
  margin-bottom: 7px;
}

.tooltip-row {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  color: var(--ink-muted);
  font-size: 12px;
  line-height: 1.5;
}

.tooltip-row b {
  color: var(--teal-deep);
  white-space: nowrap;
}

.bar-group:hover .chart-tooltip {
  opacity: 1;
  transform: translate(-50%, 0);
}

.bar-group span {
  color: var(--ink-muted);
  font-size: 11px;
  text-align: center;
  min-height: 28px;
}

.stack {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 4px;
  height: 155px;
}

.bar {
  width: 12px;
  min-height: 4px;
  border-radius: 8px 8px 3px 3px;
  transition: transform 0.15s ease;
}

.bar-group:hover .bar {
  transform: translateY(-3px);
}

.bar.order,
.bar.voucher {
  background: var(--teal-deep);
}

.bar.discount {
  background: #16a34a;
}

.bar.charge {
  background: #d97706;
}

.rank-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rank-row {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--surface-2);
  border-radius: 14px;
  padding: 12px;
}

.rank {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--teal-pale);
  color: var(--teal-deep);
  display: grid;
  place-items: center;
  font-weight: 800;
}

.rank-main {
  flex: 1;
  min-width: 0;
}

.rank-main strong,
.rank-main span {
  display: block;
}

.rank-main strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rank-main span {
  color: var(--ink-muted);
  font-size: 12px;
}

.rank-value {
  color: var(--teal-deep);
  font-weight: 800;
}

.empty-state {
  min-height: 190px;
  display: grid;
  place-items: center;
  border: 1px dashed var(--border-2);
  border-radius: 14px;
  color: var(--ink-muted);
  font-weight: 700;
  text-align: center;
  padding: 20px;
}

@media (max-width: 1100px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}
</style>
