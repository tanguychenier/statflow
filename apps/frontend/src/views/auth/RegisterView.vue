<script setup lang="ts">
// =============================================================================
// RegisterView — account creation (screens.md §7.3, ADR-0009 §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Fields: full name, email, password (12-char min), confirmation, and a terms
// checkbox. On success the account exists but is unverified (openapi.yaml), so
// we sign the user straight in with the same credentials and route them to the
// overview — matching the "log in immediately" contract — then surface a toast
// reminding them to verify their email.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Button, Checkbox, FormField, Input, toast } from '@/components/ui'
import AuthCard from '@/components/auth/AuthCard.vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { useAuthActions } from '@/components/auth/useAuthActions'
import { authErrorMessageKey, authFieldErrors } from '@/components/auth/authErrors'
import {
  PASSWORD_MIN_LENGTH,
  allValid,
  validateEmail,
  validateName,
  validateNewPassword,
  validatePasswordConfirmation,
  validateTermsAccepted,
} from '@/components/auth/authValidation'

const { t } = useI18n()
const router = useRouter()
const { register, login } = useAuthActions()

const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const acceptedTerms = ref(false)
const submitting = ref(false)
const submitted = ref(false)
const serverFieldErrors = ref<Record<string, string>>({})

const nameResult = computed(() => validateName(name.value))
const emailResult = computed(() => validateEmail(email.value))
const passwordResult = computed(() => validateNewPassword(password.value))
const confirmResult = computed(() =>
  validatePasswordConfirmation(password.value, confirmPassword.value),
)
const termsResult = computed(() => validateTermsAccepted(acceptedTerms.value))

function fieldError(server: string | undefined, messageKey: string | undefined) {
  if (server) return server
  if (submitted.value && messageKey) return t(messageKey)
  return undefined
}
const nameError = computed(() =>
  fieldError(serverFieldErrors.value.name, nameResult.value.messageKey),
)
const emailError = computed(() =>
  fieldError(serverFieldErrors.value.email, emailResult.value.messageKey),
)
const passwordError = computed(() =>
  fieldError(serverFieldErrors.value.password, passwordResult.value.messageKey),
)
const confirmError = computed(() => fieldError(undefined, confirmResult.value.messageKey))
const termsError = computed(() => fieldError(undefined, termsResult.value.messageKey))

const passwordHintId = 'register-password-hint'

function clearServerError(field: string) {
  if (serverFieldErrors.value[field]) {
    const next = { ...serverFieldErrors.value }
    delete next[field]
    serverFieldErrors.value = next
  }
}

async function onSubmit() {
  submitted.value = true
  serverFieldErrors.value = {}
  if (
    !allValid(
      nameResult.value,
      emailResult.value,
      passwordResult.value,
      confirmResult.value,
      termsResult.value,
    )
  ) {
    return
  }

  submitting.value = true
  const credentials = { email: email.value.trim(), password: password.value }
  try {
    await register({ ...credentials, name: name.value.trim() })
    await login(credentials)
    toast.success(t('auth.register.successTitle'), {
      description: t('auth.register.successDetail'),
    })
    await router.push({ name: 'overview' })
  } catch (error) {
    serverFieldErrors.value = authFieldErrors(error)
    toast.error(t(authErrorMessageKey(error, 'register')))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthCard :title="t('auth.register.title')">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <FormField v-slot="{ id, describedBy }" :label="t('auth.register.name')" :error="nameError">
        <Input
          :id="id"
          v-model="name"
          autocomplete="name"
          autofocus
          :disabled="submitting"
          :error="Boolean(nameError)"
          :aria-describedby="describedBy"
          :placeholder="t('auth.register.namePlaceholder')"
          @update:model-value="clearServerError('name')"
        />
      </FormField>

      <FormField v-slot="{ id, describedBy }" :label="t('auth.register.email')" :error="emailError">
        <Input
          :id="id"
          v-model="email"
          type="email"
          inputmode="email"
          autocomplete="email"
          :disabled="submitting"
          :error="Boolean(emailError)"
          :aria-describedby="describedBy"
          :placeholder="t('auth.register.emailPlaceholder')"
          @update:model-value="clearServerError('email')"
        />
      </FormField>

      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.register.password')"
        :error="passwordError"
      >
        <PasswordInput
          :id="id"
          v-model="password"
          autocomplete="new-password"
          :disabled="submitting"
          :error="Boolean(passwordError)"
          :described-by="[describedBy, passwordHintId].filter(Boolean).join(' ') || undefined"
          @update:model-value="clearServerError('password')"
        />
        <p :id="passwordHintId" class="text-xs text-fg-secondary">
          {{ t('auth.register.passwordHint', { min: PASSWORD_MIN_LENGTH }) }}
        </p>
      </FormField>

      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.register.confirmPassword')"
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

      <div class="flex flex-col gap-1.5">
        <div class="flex items-start gap-2">
          <Checkbox
            id="register-terms"
            :model-value="acceptedTerms"
            :disabled="submitting"
            :aria-label="t('auth.register.termsAria')"
            @update:model-value="acceptedTerms = $event === true"
          />
          <span class="text-sm text-fg-secondary">
            <i18n-t keypath="auth.register.terms" tag="span">
              <template #terms>
                <a
                  href="/terms"
                  target="_blank"
                  rel="noopener"
                  class="text-accent-text underline-offset-4 hover:underline"
                  >{{ t('auth.register.termsLink') }}</a
                >
              </template>
              <template #privacy>
                <a
                  href="/privacy"
                  target="_blank"
                  rel="noopener"
                  class="text-accent-text underline-offset-4 hover:underline"
                  >{{ t('auth.register.privacyLink') }}</a
                >
              </template>
            </i18n-t>
          </span>
        </div>
        <p v-if="termsError" id="register-terms-error" role="alert" class="text-xs text-negative-text">
          {{ termsError }}
        </p>
      </div>

      <Button type="submit" class="mt-1 w-full" :loading="submitting" @click.prevent="onSubmit">
        {{ submitting ? t('auth.register.submitting') : t('auth.register.submit') }}
      </Button>
    </form>

    <template #footer>
      <p>
        {{ t('auth.register.hasAccount') }}
        <RouterLink
          :to="{ name: 'login' }"
          class="rounded font-medium text-accent-text underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ t('auth.register.signIn') }}
        </RouterLink>
      </p>
    </template>
  </AuthCard>
</template>
