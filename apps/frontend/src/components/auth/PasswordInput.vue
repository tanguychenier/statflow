<script setup lang="ts">
// =============================================================================
// PasswordInput — password field with a show/hide toggle (screens.md §7.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Wraps the design-system Input and renders the eye toggle in its trailing
// slot. The toggle is a real button with an accessible label that reflects the
// current state (aria-pressed), and switching to text never breaks v-model.
// `autocomplete` is a prop so the same control serves login ("current-password")
// and register/reset ("new-password").
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Eye, EyeOff } from 'lucide-vue-next'
import { Input } from '@/components/ui'

withDefaults(
  defineProps<{
    modelValue: string
    id?: string
    error?: boolean
    disabled?: boolean
    autocomplete?: string
    describedBy?: string
    placeholder?: string
  }>(),
  { error: false, disabled: false, autocomplete: 'current-password' },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const { t } = useI18n()
const revealed = ref(false)

const toggleLabel = computed(() =>
  revealed.value ? t('auth.passwordToggle.hide') : t('auth.passwordToggle.show'),
)

function toggle() {
  revealed.value = !revealed.value
}
</script>

<template>
  <Input
    :id="id"
    :model-value="modelValue"
    :type="revealed ? 'text' : 'password'"
    :error="error"
    :disabled="disabled"
    :autocomplete="autocomplete"
    :placeholder="placeholder"
    :aria-describedby="describedBy"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #trailing>
      <button
        type="button"
        class="-mr-1 inline-flex size-7 items-center justify-center rounded-md text-fg-muted outline-none transition-colors hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus disabled:cursor-not-allowed disabled:opacity-40"
        :aria-label="toggleLabel"
        :aria-pressed="revealed"
        :disabled="disabled"
        :tabindex="disabled ? -1 : 0"
        data-testid="password-toggle"
        @click="toggle"
      >
        <EyeOff v-if="revealed" class="size-4" aria-hidden="true" />
        <Eye v-else class="size-4" aria-hidden="true" />
      </button>
    </template>
  </Input>
</template>
