<template>
  <Teleport to="body">
    <Transition name="avq-toast-fade">
      <div v-if="open" class="avq-toast-wrap" :class="variantClass" role="status" aria-live="polite">
        <div class="avq-toast">{{ message }}</div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  message: { type: String, default: '' },
  variant: { type: String, default: 'success' }, // success | warning | danger | info
  duration: { type: Number, default: 3000 },
});

const emit = defineEmits(['close']);

let timer = null;

const variantClass = computed(() => ({
  'avq-toast-success': props.variant === 'success',
  'avq-toast-warning': props.variant === 'warning',
  'avq-toast-danger': props.variant === 'danger',
  'avq-toast-info': props.variant === 'info',
}));

function clearTimer() {
  if (timer) {
    clearTimeout(timer);
    timer = null;
  }
}

function scheduleClose() {
  clearTimer();
  if (!props.open) return;
  timer = setTimeout(() => emit('close'), props.duration);
}

watch(() => [props.open, props.message], scheduleClose, { immediate: true });

onBeforeUnmount(clearTimer);
</script>

<style scoped>
.avq-toast-wrap {
  position: fixed;
  left: 50%;
  bottom: 24px;
  transform: translateX(-50%);
  z-index: 9999;
  pointer-events: none;
}

.avq-toast {
  min-width: 240px;
  max-width: min(560px, calc(100vw - 32px));
  padding: 12px 20px;
  border-radius: 10px;
  color: #fff;
  font: 600 14px var(--fb, Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
  box-shadow: 0 8px 24px rgba(8, 80, 65, 0.25);
  text-align: center;
  white-space: pre-wrap;
}

.avq-toast-success .avq-toast {
  background: var(--teal-deep, #085041);
}

.avq-toast-warning .avq-toast {
  background: #c2410c;
}

.avq-toast-danger .avq-toast {
  background: #b91c1c;
}

.avq-toast-info .avq-toast {
  background: #1d4ed8;
}

.avq-toast-fade-enter-active,
.avq-toast-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.avq-toast-fade-enter-from,
.avq-toast-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(8px);
}
</style>
