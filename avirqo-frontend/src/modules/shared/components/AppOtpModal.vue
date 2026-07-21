<template>
  <Teleport to="body">
    <div v-if="open" class="avq-modal-overlay otp-overlay" @click.self="emit('cancel')">
      <div class="avq-modal avq-modal-sm otp-modal">
        <div class="otp-shell">
          <div class="otp-head">
            <div class="otp-icon" aria-hidden="true">✉</div>
            <div class="otp-head-copy">
              <h3>{{ title }}</h3>
              <p v-if="message" class="otp-message">{{ message }}</p>
            </div>
          </div>

          <div class="otp-card">
            <label class="otp-label">{{ inputLabel }}</label>
            <input
              :value="otp"
              class="avq-input otp-input"
              type="text"
              inputmode="numeric"
              maxlength="6"
              :placeholder="placeholder"
              @input="emit('update:otp', $event.target.value)"
              @keyup.enter="onConfirm"
            />
            <div class="otp-row">
              <button v-if="showResend" type="button" class="otp-secondary-btn" @click="emit('resend')" :disabled="resendLoading">
                {{ resendLoading ? resendLoadingText : resendText }}
              </button>
              <span class="otp-count">{{ otp.length }}/6 digits</span>
            </div>
            <p v-if="error" class="otp-error">{{ error }}</p>
          </div>

          <div class="otp-actions">
            <button v-if="showCancel" type="button" class="otp-cancel-btn" @click="emit('cancel')">
              {{ cancelText }}
            </button>
            <button
              type="button"
              class="otp-confirm-btn"
              :disabled="confirmDisabled || loading"
              @click="onConfirm"
            >
              {{ loading ? loadingText : confirmText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  otp: { type: String, default: '' },
  placeholder: { type: String, default: '123456' },
  inputLabel: { type: String, default: 'Enter 6-Digit OTP' },
  error: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  loadingText: { type: String, default: 'Verifying…' },
  confirmText: { type: String, default: 'Verify OTP' },
  confirmDisabled: { type: Boolean, default: false },
  showCancel: { type: Boolean, default: true },
  cancelText: { type: String, default: 'Cancel' },
  showResend: { type: Boolean, default: true },
  resendLoading: { type: Boolean, default: false },
  resendText: { type: String, default: 'Resend OTP' },
  resendLoadingText: { type: String, default: 'Resending…' },
});

const emit = defineEmits(['cancel', 'confirm', 'resend', 'update:otp']);

function onConfirm() {
  if (props.confirmDisabled || props.loading) return;
  emit('confirm');
}
</script>

<style scoped>
.otp-message {
  color: #737373;
  font-size: 16px;
  line-height: 1.35;
  margin: 0;
}

.otp-shell {
  border: 2px solid #f59e0b;
  border-radius: 26px;
  background: #fff7e8;
  padding: 28px 28px 30px;
}

.otp-head {
  display: flex;
  gap: 18px;
  align-items: center;
  margin-bottom: 26px;
}

.otp-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%);
  border: 1px solid #f59e0b;
  color: #2f8bd6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  flex: 0 0 auto;
}

.otp-head-copy {
  min-width: 0;
}

.otp-head-copy h3 {
  margin-bottom: 4px;
  font-size: 24px;
  line-height: 1.15;
  color: #f97316;
  font-family: var(--fd);
}

.otp-field,
.otp-card {
  background: #fff;
  border: 2px solid #f59e0b;
  border-radius: 18px;
}

.otp-card {
  padding: 26px 28px 22px;
}

.otp-label {
  display: block;
  font-size: 18px;
  font-weight: 700;
  color: #525252;
  margin-bottom: 14px;
}

.otp-input {
  width: 100%;
  height: 60px;
  padding: 0 18px;
  border-radius: 16px;
  letter-spacing: 0.18em;
  text-align: center;
  font-size: 22px;
  font-weight: 700;
  font-family: var(--fb);
  box-sizing: border-box;
  border: 3px solid #dbe7e6;
}

.otp-input::placeholder {
  letter-spacing: 0.16em;
  color: #a1a1aa;
}

.otp-error {
  color: #b91c1c;
  margin-top: 8px;
  font-size: 13px;
}

.otp-overlay {
  z-index: 10000;
}

.otp-modal {
  padding: 0;
  background: transparent;
  border: none;
  box-shadow: none;
}

.otp-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-top: 18px;
}

.otp-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-top: 34px;
}

.otp-secondary-btn,
.otp-cancel-btn,
.otp-confirm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  font-size: 14px;
  font-weight: 700;
  font-family: var(--fb);
  cursor: pointer;
  border: 2px solid #dbe7e6;
  background: #fff;
  color: #404040;
  min-height: 74px;
  padding: 14px 24px;
  box-sizing: border-box;
}

.otp-secondary-btn {
  min-width: 230px;
  background: #fff;
}

.otp-secondary-btn:hover {
  background: #fafafa;
}

.otp-count {
  color: #6b7280;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}

.otp-cancel-btn {
  min-width: 222px;
  background: #fff5dd;
}

.otp-confirm-btn {
  flex: 1 1 auto;
  min-width: 0;
  background: #88a08e;
  border-color: #88a08e;
  color: #fff;
  font-size: 18px;
  line-height: 1.25;
}

.otp-confirm-btn:hover {
  background: #7a927f;
}

.otp-confirm-btn:disabled,
.otp-secondary-btn:disabled,
.otp-cancel-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
