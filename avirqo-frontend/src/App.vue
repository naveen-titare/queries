<script setup>
import { RouterView } from 'vue-router'
import SessionReauthModal from './modules/auth/components/SessionReauthModal.vue'
</script>

<template>
  <RouterView />
  <SessionReauthModal />
</template>
