<script setup lang="ts">
// =============================================================================
// ForgotPasswordView — request a reset link (screens.md §7.4)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The backend always answers 202 regardless of whether the address exists
// (no user enumeration — openapi.yaml). We mirror that: any non-error response
// flips the card to a neutral "if an account exists…" confirmation. Only a
// genuine transport / rate-limit failure surfaces an error.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { MailCheck } from 'lucide-vue-next'
import { Button, FormField, Input } from '@/components/ui'
import AuthCard from '@/components/auth/AuthCard.vue'
import { useAuthActions } from '@/components/auth/useAuthActions'
import { authErrorMessageKey, authFieldErrors } from '@/components/auth/authErrors'
import { allValid, validateEmail } from '@/components/auth/authValidation'

const { t } = useI18n()
const { forgotPassword } = useAuthActions()

const email = ref('')
const submitting = ref(false)
const submitted = ref(false)
const succeeded = ref(false)
const sentToEmail = ref('')
const formError = ref('')
const serverFieldErrors = ref<Record<string, string>>({})

const emailResult = computed(() => validateEmail(email.value))
const emailError = computed(() => {
  if (serverFieldErrors.value.email) return serverFieldErrors.value.email
  if (submitted.value && emailResult.value.messageKey) return t(emailResult.value.messageKey)
  return undefined
})

async function onSubmit() {
  submitted.value = true
  formError.value = ''
  serverFieldErrors.value = {}
  if (!allValid(emailResult.value)) return

  submitting.value = true
  const trimmed = email.value.trim()
  try {
    await forgotPassword({ email: trimmed })
    sentToEmail.value = trimmed
    succeeded.value = true
  } catch (error) {
    serverFieldErrors.value = authFieldErrors(error)
    formError.value = t(authErrorMessageKey(error, 'forgotPassword'))
  } finally {
    submitting.value = false
  }
}

function reset() {
  succeeded.value = false
  submitted.value = false
  email.value = ''
}
</script>

<template>
  <AuthCard
    :title="t('auth.forgotPassword.title')"
    :subtitle="succeeded ? undefined : t('auth.forgotPassword.description')"
  >
    <div
      v-if="succeeded"
      class="flex flex-col gap-4"
      role="status"
      aria-live="polite"
      data-testid="forgot-success"
    >
      <div class="flex items-start gap-3 rounded-lg border border-border bg-bg-subtle p-4">
        <MailCheck class="mt-0.5 size-5 shrink-0 text-positive-text" aria-hidden="true" />
        <div class="flex flex-col gap-1">
          <p class="text-sm font-medium text-fg-primary">
            {{ t('auth.forgotPassword.successTitle') }}
          </p>
          <p class="text-sm text-fg-secondary">
            {{ t('auth.forgotPassword.successDescription', { email: sentToEmail }) }}
          </p>
        </div>
      </div>
      <Button variant="outline" class="w-full" @click="reset">
        {{ t('auth.forgotPassword.resend') }}
      </Button>
    </div>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <p v-if="formError" role="alert" class="text-sm text-negative-text">{{ formError }}</p>
      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.forgotPassword.email')"
        :error="emailError"
      >
        <Input
          :id="id"
          v-model="email"
          type="email"
          inputmode="email"
          autocomplete="email"
          autofocus
          :disabled="submitting"
          :error="Boolean(emailError)"
          :aria-describedby="describedBy"
          :placeholder="t('auth.forgotPassword.emailPlaceholder')"
        />
      </FormField>
      <Button type="submit" class="w-full" :loading="submitting">
        {{ submitting ? t('auth.forgotPassword.submitting') : t('auth.forgotPassword.submit') }}
      </Button>
    </form>

    <template #footer>
      <p>
        <RouterLink
          :to="{ name: 'login' }"
          class="rounded font-medium text-accent-text underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ t('auth.forgotPassword.backToLogin') }}
        </RouterLink>
      </p>
    </template>
  </AuthCard>
</template>
