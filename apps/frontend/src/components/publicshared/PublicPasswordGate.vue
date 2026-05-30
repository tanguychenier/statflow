<script setup lang="ts">
// =============================================================================
// PublicPasswordGate — password prompt for protected share links (screens.md §8.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A centred card (login style) with a single password field. Purely presentational
// and self-contained: it owns the input value, surfaces validity to the parent on
// submit, and reflects the parent-owned `loading`/`error` flags. The parent verifies
// the password against the API and decides whether to persist it.
// =============================================================================
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Eye, EyeOff, Lock } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import FormField from '@/components/ui/input/FormField.vue'

withDefaults(
  defineProps<{
    /** A verification attempt is in flight — disables the form. */
    loading?: boolean
    /** The previous attempt failed (wrong/expired password). */
    error?: boolean
  }>(),
  { loading: false, error: false },
)

const emit = defineEmits<{ submit: [password: string] }>()

const { t } = useI18n()

const password = ref('')
const revealed = ref(false)

function onSubmit() {
  const value = password.value.trim()
  if (!value) return
  emit('submit', value)
}

function toggleReveal() {
  revealed.value = !revealed.value
}
</script>

<template>
  <main class="public-gate" :aria-label="t('publicDashboard.password.aria')">
    <form class="public-gate__card" novalidate @submit.prevent="onSubmit">
      <div class="public-gate__brand" aria-hidden="true">
        <span class="public-gate__brand-mark">◆</span> Statflow
      </div>

      <div class="public-gate__lead">
        <span class="public-gate__lock" aria-hidden="true">
          <Lock class="size-5" />
        </span>
        <h1 class="public-gate__title">{{ t('publicDashboard.password.title') }}</h1>
        <p class="public-gate__subtitle">{{ t('publicDashboard.password.subtitle') }}</p>
      </div>

      <FormField
        :label="t('publicDashboard.password.label')"
        :error="error ? t('publicDashboard.password.invalid') : undefined"
      >
        <template #default="{ id, describedBy, invalid }">
          <Input
            :id="id"
            v-model="password"
            :type="revealed ? 'text' : 'password'"
            :error="invalid"
            :disabled="loading"
            :aria-describedby="describedBy"
            name="share-password"
            autocomplete="current-password"
            autofocus
            :placeholder="t('publicDashboard.password.placeholder')"
          >
            <template #trailing>
              <button
                type="button"
                class="public-gate__reveal"
                :aria-label="
                  revealed ? t('publicDashboard.password.hide') : t('publicDashboard.password.show')
                "
                :aria-pressed="revealed"
                tabindex="-1"
                @click="toggleReveal"
              >
                <EyeOff v-if="revealed" class="size-4" aria-hidden="true" />
                <Eye v-else class="size-4" aria-hidden="true" />
              </button>
            </template>
          </Input>
        </template>
      </FormField>

      <Button
        type="submit"
        class="w-full"
        :loading="loading"
        :disabled="password.trim().length === 0"
      >
        {{ t('publicDashboard.password.submit') }}
      </Button>
    </form>
  </main>
</template>

<style scoped>
.public-gate {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--sf-bg-base);
  padding: var(--sf-space-4);
}

.public-gate__card {
  width: 400px;
  max-width: 100%;
  background-color: var(--sf-bg-surface);
  border: 1px solid var(--sf-border);
  border-radius: var(--sf-radius-xl);
  padding: var(--sf-space-8);
  box-shadow: var(--sf-shadow-xl);
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-6);
}

.public-gate__brand {
  font-size: var(--sf-text-xl);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

.public-gate__brand-mark {
  color: var(--sf-accent);
}

.public-gate__lead {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
}

.public-gate__lock {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--sf-radius-full);
  background-color: var(--sf-bg-subtle);
  color: var(--sf-fg-secondary);
  margin-block-end: var(--sf-space-1);
}

.public-gate__title {
  margin: 0;
  font-size: var(--sf-text-xl);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

.public-gate__subtitle {
  margin: 0;
  color: var(--sf-fg-secondary);
  font-size: var(--sf-text-sm);
}

.public-gate__reveal {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--sf-fg-muted);
  outline: none;
  border-radius: var(--sf-radius-sm);
}

.public-gate__reveal:hover {
  color: var(--sf-fg-secondary);
}

.public-gate__reveal:focus-visible {
  box-shadow: 0 0 0 2px var(--sf-border-focus);
}
</style>
