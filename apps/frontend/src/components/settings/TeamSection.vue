<script setup lang="ts">
// =============================================================================
// TeamSection — members list, role management, invite + role legend
// Copyright (c) Tanguy Chénier — AGPL-3.0  (screens.md §6.2 Team)
//
// A presentational orchestrator: members and the manage flag come in as props;
// every mutation is delegated up via events so the queries/mutations stay in the
// view. The role legend documents the four-role model (ADR-0009) inline.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import { Divider } from '@/components/ui/divider'
import { SkeletonText } from '@/components/ui/skeleton'
import { EmptyState } from '@/components/ui/empty-state'
import { TEAM_ROLES } from './settingsModel'
import InviteMemberForm from './InviteMemberForm.vue'
import TeamMemberRow from './TeamMemberRow.vue'
import type { InviteTeamMemberRequest, TeamMember, TeamRole } from '@/api/types'

const props = withDefaults(
  defineProps<{
    members: TeamMember[]
    loading?: boolean
    error?: boolean
    canManage?: boolean
    inviting?: boolean
  }>(),
  { loading: false, error: false, canManage: false, inviting: false },
)

const emit = defineEmits<{
  invite: [body: InviteTeamMemberRequest]
  'change-role': [payload: { userId: string; role: TeamRole }]
  remove: [member: TeamMember]
  retry: []
}>()

const { t } = useI18n()

const isEmpty = computed(() => !props.loading && !props.error && props.members.length === 0)
</script>

<template>
  <Card>
    <CardHeader :title="t('settings.sections.team')" />

    <div v-if="canManage" class="mb-4">
      <InviteMemberForm :submitting="inviting" @invite="emit('invite', $event)" />
      <Divider class="mt-4" />
    </div>

    <div v-if="loading" class="flex flex-col gap-3" aria-busy="true">
      <SkeletonText v-for="i in 3" :key="i" :lines="2" />
    </div>

    <EmptyState
      v-else-if="error"
      variant="error"
      :title="t('errors.loadFailed')"
      :action-label="t('common.retry')"
      @action="emit('retry')"
    />

    <EmptyState
      v-else-if="isEmpty"
      variant="no-data"
      :title="t('settings.team.empty.title')"
      :description="t('settings.team.empty.description')"
    />

    <ul v-else class="divide-y divide-border" :aria-label="t('settings.sections.team')">
      <li v-for="member in members" :key="member.id">
        <TeamMemberRow
          :member="member"
          :can-manage="canManage"
          @change-role="emit('change-role', $event)"
          @remove="emit('remove', $event)"
        />
      </li>
    </ul>

    <Divider class="my-4" />

    <section :aria-label="t('settings.team.legend.title')">
      <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-muted">
        {{ t('settings.team.legend.title') }}
      </h3>
      <dl class="flex flex-col gap-1.5 text-xs">
        <div v-for="role in TEAM_ROLES" :key="role" class="flex gap-2">
          <dt class="w-16 shrink-0 font-medium text-fg-primary">{{ t(`settings.team.roles.${role}`) }}</dt>
          <dd class="text-fg-secondary">{{ t(`settings.team.legend.${role}`) }}</dd>
        </div>
      </dl>
    </section>
  </Card>
</template>
