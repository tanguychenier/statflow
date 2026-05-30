<script setup lang="ts">
// =============================================================================
// ResetPasswordView — set a new password from an emailed link (ADR-0009 §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The single-use token arrives in the `?token=` query (emailed by the
// forgot-password flow). With no token the form is useless, so we show a
// recovery state pointing back to forgot-password. On success the backend
// revokes every session, so the user must sign in again — we route to /login.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle2 } from 'lucide-vue-next'
import { Button, FormField } from '@/components/ui'
import AuthCard from '@/components/auth/AuthCard.vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { useAuthActions } from '@/components/auth/useAuthActions'
import { authErrorMessageKey } from '@/components/auth/authErrors'
import {
  PASSWORD_MIN_LENGTH,
  allValid,
  validateNewPassword,
  validatePasswordConfirmation,
} from '@/components/auth/authValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { resetPassword } = useAuthActions()

const token = computed(() => {
  const raw = route.query.token
  return typeof raw === 'string' ? raw : ''
})
const hasToken = computed(() => token.value.length > 0)

const password = ref('')
const confirmPassword = ref('')
const submitting = ref(false)
const submitted = ref(false)
const succeeded = ref(false)
const formError = ref('')

const passwordResult = computed(() => validateNewPassword(password.value))
const confirmResult = computed(() =>
  validatePasswordConfirmation(password.value, confirmPassword.value),
)

const passwordError = computed(() =>
  submitted.value && passwordResult.value.messageKey ? t(passwordResult.value.messageKey) : undefined,
)
const confirmError = computed(() =>
  submitted.value && confirmResult.value.messageKey ? t(confirmResult.value.messageKey) : undefined,
)

const passwordHintId = 'reset-password-hint'

async function onSubmit() {
  submitted.value = true
  formError.value = ''
  if (!allValid(passwordResult.value, confirmResult.value)) return

  submitting.value = true
  try {
    await resetPassword({ token: token.value, new_password: password.value })
    succeeded.value = true
  } catch (error) {
    formError.value = t(authErrorMessageKey(error, 'resetPassword'))
  } finally {
    submitting.value = false
  }
}

function goToLogin() {
  return router.push({ name: 'login' })
}
</script>

<template>
  <AuthCard
    :title="t('auth.resetPassword.title')"
    :subtitle="hasToken && !succeeded ? t('auth.resetPassword.description') : undefined"
  >
    <div
      v-if="succeeded"
      class="flex flex-col gap-4"
      role="status"
      aria-live="polite"
      data-testid="reset-success"
    >
      <div class="flex items-start gap-3 rounded-lg border border-border bg-bg-subtle p-4">
        <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-positive-text" aria-hidden="true" />
        <div class="flex flex-col gap-1">
          <p class="text-sm font-medium text-fg-primary">
            {{ t('auth.resetPassword.successTitle') }}
          </p>
          <p class="text-sm text-fg-secondary">
            {{ t('auth.resetPassword.successDescription') }}
          </p>
        </div>
      </div>
      <Button class="w-full" @click="goToLogin">{{ t('auth.resetPassword.backToLogin') }}</Button>
    </div>

    <div v-else-if="!hasToken" class="flex flex-col gap-4" data-testid="reset-missing-token">
      <p class="text-sm text-fg-secondary">{{ t('auth.resetPassword.missingToken') }}</p>
      <Button variant="outline" class="w-full" @click="router.push({ name: 'forgot-password' })">
        {{ t('auth.resetPassword.requestNewLink') }}
      </Button>
    </div>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <p v-if="formError" role="alert" class="text-sm text-negative-text">{{ formError }}</p>

      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.resetPassword.password')"
        :error="passwordError"
      >
        <PasswordInput
          :id="id"
          v-model="password"
          autocomplete="new-password"
          :disabled="submitting"
          :error="Boolean(passwordError)"
          :described-by="[describedBy, passwordHintId].filter(Boolean).join(' ') || undefined"
        />
        <p :id="passwordHintId" class="text-xs text-fg-secondary">
          {{ t('auth.resetPassword.passwordHint', { min: PASSWORD_MIN_LENGTH }) }}
        </p>
      </FormField>

      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.resetPassword.confirmPassword')"
        :error="confirmError"
      >
        <PasswordInput
          :id="id"
          v-model="confirmPassword"
          autocomplete="new-password"
          :disabled="submitting"
          :error="Boolean(confirmError)"
          :described-by="describedBy"
        />
      </FormField>

      <Button type="submit" class="w-full" :loading="submitting" @click.prevent="onSubmit">
        {{ submitting ? t('auth.resetPassword.submitting') : t('auth.resetPassword.submit') }}
      </Button>
    </form>

    <template #footer>
      <p>
        <RouterLink
          :to="{ name: 'login' }"
          class="rounded font-medium text-accent-text underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ t('auth.resetPassword.backToLogin') }}
        </RouterLink>
      </p>
    </template>
  </AuthCard>
</template>
