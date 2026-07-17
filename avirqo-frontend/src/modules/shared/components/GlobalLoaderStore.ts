import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useGlobalLoaderStore = defineStore('global-loader', () => {
  // Global loading state
  const isLoading = ref(false);
  const loadingText = ref('');
  const showProgress = ref(false);
  const progress = ref(0);

  let loadingCount = 0;

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

  function setProgress(value: number) {
    progress.value = Math.min(100, Math.max(0, value));
  }

  function setText(text: string) {
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
});