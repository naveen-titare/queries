<template>
  <div v-if="open" class="avq-modal-overlay" @click.self="onCancel">
    <div class="avq-modal" :class="sizeClass">
      <div class="dialog-head">
        <div>
          <h3>{{ title }}</h3>
          <p v-if="message" class="dialog-message">{{ message }}</p>
        </div>
      </div>

      <div v-if="showInput" class="dialog-input">
        <label v-if="inputLabel" class="dialog-label">{{ inputLabel }}</label>
        <input
          class="avq-input dialog-field"
          :type="inputType"
          :placeholder="inputPlaceholder"
          :value="inputValue"
          @input="onInput"
          @keyup.enter="onConfirm"
        />
      </div>

      <slot />

      <div class="modal-footer dialog-footer">
        <button v-if="showCancel" type="button" class="avq-btn-ghost" @click="onCancel">
          {{ cancelText }}
        </button>
        <button
          type="button"
          class="avq-btn-primary"
          :class="variantClass"
          :disabled="confirmDisabled || loading"
          @click="onConfirm"
        >
          {{ loading ? loadingText : confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'OK' },
  cancelText: { type: String, default: 'Cancel' },
  loadingText: { type: String, default: 'Please wait…' },
  showCancel: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
  confirmDisabled: { type: Boolean, default: false },
  variant: { type: String, default: 'default' }, // default | danger | warning
  size: { type: String, default: 'md' }, // sm | md | lg
  showInput: { type: Boolean, default: false },
  inputLabel: { type: String, default: '' },
  inputPlaceholder: { type: String, default: '' },
  inputType: { type: String, default: 'text' },
  inputValue: { type: [String, Number], default: '' },
});

const emit = defineEmits(['cancel', 'confirm', 'update:inputValue']);

const sizeClass = computed(() => ({
  'avq-modal-sm': props.size === 'sm',
  'avq-modal-lg': props.size === 'lg',
}));

const variantClass = computed(() => ({
  'dialog-btn-danger': props.variant === 'danger',
  'dialog-btn-warning': props.variant === 'warning',
}));

function onCancel() {
  emit('cancel');
}

function onConfirm() {
  if (props.confirmDisabled || props.loading) return;
  emit('confirm');
}

function onInput(event) {
  emit('update:inputValue', event.target.value);
}
</script>

<style scoped>
.dialog-head h3 {
  font-family: var(--fd);
  font-size: 22px;
  margin-bottom: 8px;
}

.dialog-message {
  color: var(--ink-muted);
  font-size: 14px;
  line-height: 1.5;
}

.dialog-input {
  margin: 18px 0 4px;
}

.dialog-label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--ink-muted);
  margin-bottom: 8px;
}

.dialog-field {
  width: 100%;
}

.dialog-footer {
  margin-top: 24px;
}

.dialog-btn-danger {
  background: #b91c1c;
}

.dialog-btn-danger:hover {
  background: #991b1b;
}

.dialog-btn-warning {
  background: #c2410c;
}

.dialog-btn-warning:hover {
  background: #9a3412;
}
</style>
