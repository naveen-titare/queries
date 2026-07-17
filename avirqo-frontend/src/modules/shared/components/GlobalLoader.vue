<template>
  <Teleport to="body">
    <div 
      v-if="isLoading" 
      class="global-loader-overlay"
      :class="{ 'with-progress': showProgress }"
    >
      <div class="global-loader-content">
        <div class="spinner-ring">
          <div class="spinner-inner"></div>
        </div>
        
        <div v-if="loadingText" class="loader-text">{{ loadingText }}</div>
        
        <div v-if="showProgress" class="progress-bar-container">
          <div class="progress-bar" :style="{ width: progress + '%' }"></div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';

// Global loading state
const isLoading = ref(false);
const loadingText = ref('');
const showProgress = ref(false);
const progress = ref(0);

let loadingCount = 0;

export function useGlobalLoader() {
  function startLoading(text = '', withProgress = false) {
    loadingCount++;
    isLoading.value = true;
    loadingText.value = text || 'Processing...';
    showProgress.value = withProgress;
    progress.value = 0;
    
    if (withProgress) {
      simulateProgress();
    }
  }

  function stopLoading() {
    loadingCount = Math.max(0, loadingCount - 1);
    if (loadingCount === 0) {
      isLoading.value = false;
      loadingText.value = '';
      showProgress.value = false;
      progress.value = 0;
    }
  }

  function setProgress(value) {
    progress.value = Math.min(100, Math.max(0, value));
  }

  function setText(text) {
    loadingText.value = text;
  }

  function simulateProgress() {
    let current = 0;
    const interval = setInterval(() => {
      if (!isLoading.value || loadingCount === 0) {
        clearInterval(interval);
        return;
      }
      
      // Simulate progress - slow down as it approaches 90%
      if (current < 90) {
        current += Math.random() * 10;
        progress.value = Math.min(current, 90);
      }
    }, 200);
  }

  return {
    isLoading: computed(() => isLoading.value),
    loadingText: computed(() => loadingText.value),
    showProgress: computed(() => showProgress.value),
    progress: computed(() => progress.value),
    startLoading,
    stopLoading,
    setProgress,
    setText,
  };
}
</script>

<style scoped>
.global-loader-overlay {
  position: fixed;
  inset: 0;
  background: rgba(8, 8, 12, 0.65);
  backdrop-filter: blur(4px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.global-loader-content {
  background: #fff;
  border-radius: 16px;
  padding: 40px 48px;
  text-align: center;
  min-width: 280px;
  max-width: 90vw;
  box-shadow: 0 24px 64px rgba(8, 8, 12, 0.3);
  animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
  from { 
    opacity: 0; 
    transform: translateY(16px) scale(0.96); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1); 
  }
}

.spinner-ring {
  width: 56px;
  height: 56px;
  margin: 0 auto 20px;
  position: relative;
}

.spinner-ring::before,
.spinner-ring::after {
  content: '';
  position: absolute;
  inset: 0;
  border: 3px solid transparent;
  border-radius: 50%;
  animation: spinRing 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

.spinner-ring::before {
  border-top-color: var(--teal-deep, #1d9e75);
  border-right-color: var(--teal-deep, #1d9e75);
}

.spinner-ring::after {
  animation-direction: reverse;
  animation-duration: 0.8s;
  border-bottom-color: var(--teal-mid, #2eb886);
  border-left-color: var(--teal-mid, #2eb886);
}

@keyframes spinRing {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loader-text {
  font-size: 15px;
  color: var(--ink, #1a1a1a);
  font-weight: 500;
  margin-bottom: 16px;
  font-family: var(--fb, system-ui);
}

.progress-bar-container {
  width: 100%;
  height: 4px;
  background: var(--surface-2, #e8e8e8);
  border-radius: 2px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--teal-deep, #1d9e75), var(--teal-mid, #2eb886));
  border-radius: 2px;
  transition: width 0.3s ease-out;
  width: 0%;
}
</style>
