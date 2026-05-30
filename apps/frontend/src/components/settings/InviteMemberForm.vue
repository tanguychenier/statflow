<script setup lang="ts">
// =============================================================================
// InviteMemberForm — email + role invite row (screens.md §6.2 Team)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Validates the email locally and emits an InviteTeamMemberRequest on submit,
// then clears for the next invite. Roles are limited to the assignable set
// (ownership is transferred elsewhere, not granted via invite — ADR-0009).
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { UserPlus } from 'lucide-vue-next'
import FormField from '@/components/ui/input/FormField.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Button from '@/components/ui/button/Button.vue'
import { ASSIGNABLE_ROLES } from './settingsModel'
import type { InviteTeamMemberRequest, TeamRole } from '@/api/types'

withDefaults(defineProps<{ submitting?: boolean }>(), { submitting: false })

const emit = defineEmits<{ invite: [body: InviteTeamMemberRequest] }>()

const { t } = useI18n()

const email = ref('')
const role = ref<TeamRole>('viewer')
const showError = ref(false)

// Deliberately conservative: a single `@`, a dot in the domain, no spaces.
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const emailValid = computed(() => EMAIL_RE.test(email.value.trim()))

const emailError = computed(() =>
  showError.value && !emailValid.value ? t('settings.team.invite.emailInvalid') : undefined,
)

const roleOptions = computed(() =>
  ASSIGNABLE_ROLES.map((r) => ({ value: r, label: t(`settings.team.roles.${r}`) })),
)

function onRole(value: string) {
  role.value = value as TeamRole
}

function onSubmit() {
  showError.value = true
  if (!emailValid.value) return
  emit('invite', { email: email.value.trim(), role: role.value })
  email.value = ''
  role.value = 'viewer'
  showError.value = false
}
</script>

<template>
  <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="onSubmit">
    <FormField :label="t('settings.team.invite.email')" :error="emailError" class="flex-1">
      <template #default="{ id, describedBy, invalid }">
        <Input
          :id="id"
          v-model="email"
          type="email"
          :aria-describedby="describedBy"
          :error="invalid"
          placeholder="you@example.com"
          autocomplete="off"
        />
      </template>
    </FormField>

    <FormField :label="t('settings.team.invite.role')" class="sm:w-40">
      <template #default="{ id }">
        <Select
          :id="id"
          :model-value="role"
          :options="roleOptions"
          class="w-full"
          @update:model-value="onRole"
        />
      </template>
    </FormField>

    <Button type="submit" :loading="submitting" class="sm:mb-0" @click.prevent="onSubmit">
      <template #leading><UserPlus class="size-4" aria-hidden="true" /></template>
      {{ t('settings.team.invite.submit') }}
    </Button>
  </form>
</template>
