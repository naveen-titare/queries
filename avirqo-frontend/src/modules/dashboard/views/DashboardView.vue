<template>
  <AppLayout>
    <main class="avq-content">
      <p class="avq-welcome">Welcome back, {{ firstName }} — session renews until {{ formattedExpiry }}</p>

      <div class="avq-grid">
        <!-- Left column -->
        <div class="avq-col-main">
          <!-- Analytics bar chart -->
          <section class="avq-card">
            <div class="avq-card-head">
              <h3>Vouchers Sent</h3>
              <span class="avq-pill">Last 6 months</span>
            </div>
            <svg class="avq-barchart" viewBox="0 0 340 140" preserveAspectRatio="none">
              <line x1="0" y1="20" x2="340" y2="20" stroke="#F0B429" stroke-dasharray="4 4" stroke-width="1" />
              <rect
                v-for="(bar, i) in bars"
                :key="bar.label"
                :x="i * 56 + 10"
                :y="120 - bar.height"
                width="30"
                :height="bar.height"
                rx="4"
                :fill="bar.peak ? '#1D9E75' : '#E4EDE9'"
              />
            </svg>
            <div class="avq-barchart-labels">
              <span v-for="bar in bars" :key="bar.label + '-lbl'">{{ bar.label }}</span>
            </div>
          </section>

          <!-- Engagement stats -->
          <section class="avq-card">
            <h3>Engagement</h3>
            <div class="avq-stats-row">
              <div class="avq-stat">
                <div class="avq-stat-val">12.9K</div>
                <div class="avq-stat-label">Recipients reached</div>
                <svg class="avq-spark" viewBox="0 0 80 28"><polyline :points="sparkUp" fill="none" stroke="#1D9E75" stroke-width="2" /></svg>
              </div>
              <div class="avq-stat">
                <div class="avq-stat-val">94%</div>
                <div class="avq-stat-label">Redemption rate</div>
                <svg class="avq-spark" viewBox="0 0 80 28"><polyline :points="sparkFlat" fill="none" stroke="#085041" stroke-width="2" /></svg>
              </div>
            </div>
          </section>

          <!-- Donuts -->
          <section class="avq-card">
            <h3>Redemption Breakdown</h3>
            <div class="avq-donuts">
              <div class="avq-donut-item" v-for="d in donuts" :key="d.label">
                <svg viewBox="0 0 80 80" class="avq-donut">
                  <circle cx="40" cy="40" r="32" fill="none" stroke="#E4EDE9" stroke-width="10" />
                  <circle
                    cx="40" cy="40" r="32" fill="none"
                    :stroke="d.color" stroke-width="10"
                    stroke-linecap="round"
                    :stroke-dasharray="`${d.pct * 2.0106} 1000`"
                    transform="rotate(-90 40 40)"
                  />
                  <text x="40" y="45" text-anchor="middle" class="avq-donut-text">{{ d.pct }}%</text>
                </svg>
                <div class="avq-donut-label">{{ d.label }}</div>
              </div>
            </div>
          </section>
        </div>

        <!-- Right column -->
        <div class="avq-col-side">
          <section class="avq-card">
            <h3>Redemption Trend</h3>
            <svg class="avq-trend" viewBox="0 0 220 90" preserveAspectRatio="none">
              <polyline :points="trendPoints" fill="none" stroke="#085041" stroke-width="2.5" />
            </svg>
            <div class="avq-trend-labels">
              <span>Mon</span><span>Wed</span><span>Fri</span><span>Sun</span>
            </div>
          </section>

          <section class="avq-card">
            <h3>Top Brands &amp; Recipients</h3>
            <div class="avq-toplists">
              <div>
                <div class="avq-toplist-title">Top Brands</div>
                <ol>
                  <li v-for="b in topBrands" :key="b">{{ b }}</li>
                </ol>
              </div>
              <div>
                <div class="avq-toplist-title">Top Recipients</div>
                <ol>
                  <li v-for="r in topRecipients" :key="r">{{ r }}</li>
                </ol>
              </div>
            </div>
          </section>
        </div>
      </div>

      <p class="avq-note">Placeholder data — this wires up to the real vouchers/rewards module next.</p>
    </main>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../auth/store/authStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const auth = useAuthStore();

const firstName = computed(() => (auth.user?.name || 'there').split(' ')[0]);
const formattedExpiry = computed(() => {
  if (!auth.accessTokenExpiresAt) return '';
  return new Date(auth.accessTokenExpiresAt).toLocaleString();
});

const rawBars = [1200, 1600, 1400, 2100, 1800, 2400];
const maxBar = Math.max(...rawBars);
const bars = rawBars.map((v, i) => ({
  label: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'][i],
  height: Math.round((v / maxBar) * 90),
  peak: v === maxBar,
}));

const sparkUp = '0,24 15,20 30,22 45,10 60,14 80,4';
const sparkFlat = '0,14 15,16 30,10 45,12 60,8 80,6';
const trendPoints = '0,60 30,40 60,55 90,25 120,45 150,20 180,35 220,15';

const donuts = [
  { label: 'Redeemed', pct: 85, color: '#F0B429' },
  { label: 'Pending', pct: 40, color: '#7C3AED' },
  { label: 'Sent', pct: 65, color: '#4F46E5' },
];

const topBrands = ['Amazon', 'AJIO', 'Starbucks'];
const topRecipients = ['Rahul K.', 'Ananya S.', 'Priya M.'];
</script>

<style>
.avq-content { padding: 28px; }
.avq-welcome { color: var(--ink-muted); font-size: 13px; margin-bottom: 20px; }

.avq-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}

@media (max-width: 860px) {
  .avq-grid { grid-template-columns: 1fr; }
}

.avq-col-main, .avq-col-side { display: flex; flex-direction: column; gap: 20px; }

.avq-card {
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 4px 16px rgba(8, 80, 65, 0.05);
}

.avq-card h3 {
  font-family: var(--fd);
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 14px;
}

.avq-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.avq-card-head h3 { margin-bottom: 0; }

.avq-pill {
  font-size: 11px;
  font-weight: 600;
  background: var(--teal-pale);
  color: var(--teal-deep);
  padding: 4px 10px;
  border-radius: 100px;
}

.avq-barchart { width: 100%; height: 130px; }
.avq-barchart-labels {
  display: flex;
  justify-content: space-around;
  font-size: 11px;
  color: var(--ink-muted);
  margin-top: 4px;
}

.avq-stats-row { display: flex; gap: 24px; }
.avq-stat { flex: 1; }
.avq-stat-val { font-family: var(--fd); font-size: 24px; font-weight: 600; }
.avq-stat-label { font-size: 12px; color: var(--ink-muted); margin-bottom: 8px; }
.avq-spark { width: 100%; height: 28px; }

.avq-donuts { display: flex; justify-content: space-between; gap: 12px; }
.avq-donut-item { text-align: center; flex: 1; }
.avq-donut { width: 80px; height: 80px; }
.avq-donut-text { font-family: var(--fb); font-size: 14px; font-weight: 700; fill: var(--ink); }
.avq-donut-label { font-size: 11px; color: var(--ink-muted); margin-top: 4px; }

.avq-trend { width: 100%; height: 90px; }
.avq-trend-labels {
  display: flex; justify-content: space-between;
  font-size: 11px; color: var(--ink-muted); margin-top: 4px;
}

.avq-toplists { display: flex; gap: 24px; }
.avq-toplist-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-muted); margin-bottom: 8px; }
.avq-toplists ol { padding-left: 18px; font-size: 13px; color: var(--ink-soft); line-height: 2; }

.avq-note { text-align: center; color: var(--ink-faint, #B4B2A9); font-size: 12px; margin-top: 24px; }
</style>
