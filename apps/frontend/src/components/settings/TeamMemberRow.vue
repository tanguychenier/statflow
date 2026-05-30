<script setup lang="ts">
// =============================================================================
// TeamMemberRow — one member: avatar, identity, role, status, actions
// Copyright (c) Tanguy Chénier — AGPL-3.0  (screens.md §6.2 Team)
//
// Role is editable in place for managers (except the owner, whose role is fixed
// and whose membership cannot be revoked here). Pending invitations render an
// "invited" badge. All mutations are delegated to the parent via events.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Trash2 } from 'lucide-vue-next'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Select from '@/components/ui/select/Select.vue'
import Button from '@/components/ui/button/Button.vue'
import { ASSIGNABLE_ROLES, roleBadgeVariant } from './settingsModel'
import type { TeamMember, TeamRole } from '@/api/types'

const props = withDefaults(
  defineProps<{ member: TeamMember; canManage?: boolean }>(),
  { canManage: false },
)

const emit = defineEmits<{
  'change-role': [payload: { userId: string; role: TeamRole }]
  remove: [member: TeamMember]
}>()

const { t, d } = useI18n()

const isOwner = computed(() => props.member.role === 'owner')
const isPending = computed(() => props.member.status === 'invited')
const isEditable = computed(() => props.canManage && !isOwner.value)

const roleOptions = computed(() =>
  ASSIGNABLE_ROLES.map((role) => ({ value: role, label: t(`settings.team.roles.${role}`) })),
)

function onRole(role: string) {
  emit('change-role', { userId: props.member.user_id, role: role as TeamRole })
}
</script>

<template>
  <div class="flex items-center gap-3 py-3">
    <Avatar :name="member.name" size="md" />

    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-fg-primary"><span>{{ member.name || member.email }}</span></p>
      <p class="truncate text-xs text-fg-secondary">{{ member.email }}</p>
    </div>

    <Badge v-if="isPending" variant="warning">{{ t('settings.team.status.invited') }}</Badge>
    <span v-else-if="member.joined_at" class="hidden text-xs text-fg-muted sm:inline">
      {{ t('settings.team.joinedOn', { date: d(new Date(member.joined_at), 'short') }) }}
    </span>

    <Select
      v-if="isEditable"
      :model-value="member.role"
      :options="roleOptions"
      :aria-label="t('settings.team.changeRoleFor', { name: member.name || member.email })"
      class="w-32"
      @update:model-value="onRole"
    />
    <Badge v-else :variant="roleBadgeVariant(member.role)">
      {{ t(`settings.team.roles.${member.role}`) }}
    </Badge>

    <Button
      v-if="isEditable"
      variant="ghost"
      size="icon"
      :aria-label="t('settings.team.revokeFor', { name: member.name || member.email })"
      @click="emit('remove', member)"
    >
      <Trash2 class="size-4 text-fg-muted" aria-hidden="true" />
    </Button>
    <span v-else class="size-8 shrink-0" aria-hidden="true" />
  </div>
</template>
