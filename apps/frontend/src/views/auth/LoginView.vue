<script setup lang="ts">
// =============================================================================
// LoginView — email + password sign-in (screens.md §7.1, ADR-0009 §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// v1 is email + password only — no OAuth buttons (ADR-0009). On success we
// honour a `?redirect=` query (set by the router guard) and fall back to the
// overview. Credentials errors mark both fields and surface an error toast.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import { Button, FormField, Input, toast } from '@/components/ui'
import AuthCard from '@/components/auth/AuthCard.vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { useAuthActions } from '@/components/auth/useAuthActions'
import { authErrorMessageKey, authFieldErrors } from '@/components/auth/authErrors'
import { allValid, validateEmail, validateLoginPassword } from '@/components/auth/authValidation'
import { ApiError } from '@/api/ApiError'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { login } = useAuthActions()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const submitted = ref(false)
const serverFieldErrors = ref<Record<string, string>>({})
/** True after a 401 — marks both fields invalid without a visible message. */
const credentialsInvalid = ref(false)

const emailResult = computed(() => validateEmail(email.value))
const passwordResult = computed(() => validateLoginPassword(password.value))

const emailError = computed(() => {
  if (serverFieldErrors.value.email) return serverFieldErrors.value.email
  if (submitted.value && emailResult.value.messageKey) return t(emailResult.value.messageKey)
  return undefined
})
const passwordError = computed(() => {
  if (serverFieldErrors.value.password) return serverFieldErrors.value.password
  if (submitted.value && passwordResult.value.messageKey) return t(passwordResult.value.messageKey)
  return undefined
})

// Whether a field should render aria-invalid (covers both validation errors and
// a 401 "wrong credentials" response which marks both fields without a message).
const emailInvalid = computed(() => credentialsInvalid.value || Boolean(emailError.value))
const passwordInvalid = computed(() => credentialsInvalid.value || Boolean(passwordError.value))

function clearServerError(field: string) {
  credentialsInvalid.value = false
  if (serverFieldErrors.value[field]) {
    const next = { ...serverFieldErrors.value }
    delete next[field]
    serverFieldErrors.value = next
  }
}

async function onSubmit() {
  submitted.value = true
  serverFieldErrors.value = {}
  credentialsInvalid.value = false
  if (!allValid(emailResult.value, passwordResult.value)) return

  submitting.value = true
  try {
    await login({ email: email.value.trim(), password: password.value })
    toast.success(t('auth.login.welcomeBack'), { description: t('auth.login.welcomeBackDetail') })
    const redirect = route.query.redirect
    await router.push(typeof redirect === 'string' && redirect ? redirect : { name: 'overview' })
  } catch (error) {
    serverFieldErrors.value = authFieldErrors(error)
    if (error instanceof ApiError && error.status === 401) {
      credentialsInvalid.value = true
    }
    toast.error(t(authErrorMessageKey(error, 'login')))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthCard :title="t('auth.login.title')">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <FormField v-slot="{ id, describedBy }" :label="t('auth.login.email')" :error="emailError">
        <Input
          :id="id"
          v-model="email"
          type="email"
          inputmode="email"
          autocomplete="email"
          autofocus
          :disabled="submitting"
          :error="emailInvalid"
          :aria-describedby="describedBy"
          :placeholder="t('auth.login.emailPlaceholder')"
          @update:model-value="clearServerError('email')"
        />
      </FormField>

      <FormField
        v-slot="{ id, describedBy }"
        :label="t('auth.login.password')"
        :error="passwordError"
      >
        <PasswordInput
          :id="id"
          v-model="password"
          autocomplete="current-password"
          :disabled="submitting"
          :error="passwordInvalid"
          :described-by="describedBy"
          @update:model-value="clearServerError('password')"
        />
      </FormField>

      <div class="flex items-center justify-between gap-3 pt-1">
        <Button type="submit" :loading="submitting" @click.prevent="onSubmit">
          {{ submitting ? t('auth.login.submitting') : t('auth.login.submit') }}
          <template #trailing>
            <ArrowRight v-if="!submitting" class="size-4" aria-hidden="true" />
          </template>
        </Button>
        <RouterLink
          :to="{ name: 'forgot-password' }"
          class="rounded text-sm text-accent-text underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ t('auth.login.forgot') }}
        </RouterLink>
      </div>
    </form>

    <template #footer>
      <p>
        {{ t('auth.login.noAccount') }}
        <RouterLink
          :to="{ name: 'register' }"
          class="rounded font-medium text-accent-text underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ t('auth.login.createOne') }}
        </RouterLink>
      </p>
    </template>
  </AuthCard>
</template>
