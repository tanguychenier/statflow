<script setup lang="ts">
// =============================================================================
// FunnelsView — multi-step conversion funnels (screens.md §3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Three states share one route: the funnel list, a selected funnel's detail,
// and the create/edit builder modal. The list is fed by the persisted-funnels
// query; opening a funnel mounts FunnelDetail, which runs the analysis queries.
// Create / update / delete go through the typed funnel composables with toast
// feedback; all data shaping lives in the ./components/funnels adapters so this
// file stays a declarative orchestrator.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Plus } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import { EmptyState } from '@/components/ui/empty-state'
import { ConfirmDialog } from '@/components/ui/dialog'
import { SkeletonCard } from '@/components/ui/skeleton'
import { ChartEmpty } from '@/components/charts'
import {
  FunnelListItem,
  FunnelDetail,
  FunnelBuilderModal,
  buildListEntry,
  useUpdateFunnel,
} from '@/components/funnels'
import { useCurrentSiteStore } from '@/stores/currentSite'
import { useFunnels, useCreateFunnel, useDeleteFunnel } from '@/api/composables/useSites'
import { toast } from '@/composables/useToast'
import type { Funnel, FunnelCreateRequest } from '@/api/types'

const { t } = useI18n()

const siteStore = useCurrentSiteStore()
const { currentSiteId } = storeToRefs(siteStore)
// The mutation composables require a non-null site id; queries below stay
// disabled until one exists, so mutations only fire once it is present.
const siteId = computed(() => currentSiteId.value ?? '')

const funnels = useFunnels(currentSiteId)
const createFunnel = useCreateFunnel(siteId)
const updateFunnel = useUpdateFunnel(siteId)
const deleteFunnel = useDeleteFunnel(siteId)

const listEntries = computed(() =>
  (funnels.data.value ?? []).map((funnel) => buildListEntry(funnel)),
)
const isEmpty = computed(
  () => !funnels.isPending.value && !funnels.isError.value && listEntries.value.length === 0,
)

// ── Navigation between list and detail ───────────────────────────────────────
const selectedFunnel = ref<Funnel | null>(null)

function openFunnel(funnel: Funnel) {
  selectedFunnel.value = funnel
}

function backToList() {
  selectedFunnel.value = null
}

// ── Builder modal (create + edit) ────────────────────────────────────────────
const builderOpen = ref(false)
const editingFunnel = ref<Funnel | null>(null)
const saving = computed(() => createFunnel.isPending.value || updateFunnel.isPending.value)

function openCreate() {
  editingFunnel.value = null
  builderOpen.value = true
}

function openEdit(funnel: Funnel) {
  editingFunnel.value = funnel
  builderOpen.value = true
}

async function onSave(body: FunnelCreateRequest) {
  try {
    if (editingFunnel.value) {
      await updateFunnel.mutateAsync({ funnelId: editingFunnel.value.id, body })
      toast.success(t('funnels.toast.updated'))
    } else {
      await createFunnel.mutateAsync(body)
      toast.success(t('funnels.toast.created'))
    }
    builderOpen.value = false
    editingFunnel.value = null
  } catch {
    toast.error(t('errors.saveFailed'))
  }
}

// ── Delete confirmation ──────────────────────────────────────────────────────
const pendingDelete = ref<Funnel | null>(null)
const deleting = computed(() => deleteFunnel.isPending.value)

function requestDelete(funnel: Funnel) {
  pendingDelete.value = funnel
}

async function confirmDelete() {
  const target = pendingDelete.value
  if (!target) return
  try {
    await deleteFunnel.mutateAsync(target.id)
    toast.success(t('funnels.toast.deleted'))
    if (selectedFunnel.value?.id === target.id) selectedFunnel.value = null
  } catch {
    toast.error(t('errors.generic'))
  } finally {
    pendingDelete.value = null
  }
}
</script>

<template>
  <FunnelDetail
    v-if="selectedFunnel"
    :funnel="selectedFunnel"
    @back="backToList"
    @edit="openEdit(selectedFunnel)"
  />

  <div v-else class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-semibold tracking-tight text-fg-primary">{{ t('funnels.title') }}</h1>
      <Button size="sm" @click="openCreate">
        <template #leading><Plus class="size-4" aria-hidden="true" /></template>
        {{ t('funnels.newFunnel') }}
      </Button>
    </div>

    <div v-if="funnels.isPending.value" class="flex flex-col gap-3" aria-busy="true">
      <SkeletonCard v-for="i in 3" :key="i" />
    </div>

    <ChartEmpty v-else-if="funnels.isError.value" variant="error" @retry="funnels.refetch()" />

    <EmptyState
      v-else-if="isEmpty"
      variant="no-data"
      :title="t('funnels.empty.title')"
      :description="t('funnels.empty.description')"
      :action-label="t('funnels.empty.action')"
      @action="openCreate"
    />

    <ul v-else class="flex flex-col gap-3">
      <li v-for="entry in listEntries" :key="entry.funnel.id">
        <FunnelListItem
          :entry="entry"
          @open="openFunnel(entry.funnel)"
          @edit="openEdit(entry.funnel)"
          @remove="requestDelete(entry.funnel)"
        />
      </li>
    </ul>
  </div>

  <FunnelBuilderModal
    v-model:open="builderOpen"
    :funnel="editingFunnel"
    :saving="saving"
    @save="onSave"
  />

  <ConfirmDialog
    :open="pendingDelete !== null"
    :title="t('funnels.deleteConfirm.title')"
    :description="
      pendingDelete ? t('funnels.deleteConfirm.description', { name: pendingDelete.name }) : ''
    "
    :confirm-label="t('common.delete')"
    :cancel-label="t('common.cancel')"
    :loading="deleting"
    @update:open="pendingDelete = $event ? pendingDelete : null"
    @confirm="confirmDelete"
    @cancel="pendingDelete = null"
  />
</template>
