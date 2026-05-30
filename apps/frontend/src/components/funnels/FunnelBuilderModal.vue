<script setup lang="ts">
// =============================================================================
// FunnelBuilderModal — create/edit a funnel definition (screens.md §3.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Wraps the builder draft model in the design-system Modal. Holds a single
// reactive draft, runs validation on every change, and emits a clean
// FunnelCreateRequest on save. All draft rules (limits, validity, serialization)
// live in funnelBuilder.ts — this file is the binding + layout only.
// =============================================================================
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus } from 'lucide-vue-next'
import { Modal } from '@/components/ui/dialog'
import Button from '@/components/ui/button/Button.vue'
import FormField from '@/components/ui/input/FormField.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import FunnelStepRow from './FunnelStepRow.vue'
import {
  CONVERSION_WINDOW_DAYS,
  canAddStep,
  canRemoveStep,
  createDraftStep,
  createEmptyDraft,
  draftFromFunnel,
  reorderStep,
  toCreateRequest,
  validateDraft,
  type CountMode,
  type DraftStep,
  type FunnelDraft,
} from './funnelBuilder'
import type { Funnel, FunnelCreateRequest } from '@/api/types'

const props = withDefaults(
  defineProps<{
    open: boolean
    /** When provided, the modal is in Edit mode and is seeded from this funnel. */
    funnel?: Funnel | null
    saving?: boolean
  }>(),
  { funnel: null, saving: false },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [body: FunnelCreateRequest]
}>()

const { t } = useI18n()

const draft = ref<FunnelDraft>(createEmptyDraft())
const showErrors = ref(false)

// Re-seed the draft whenever the modal opens so each session starts clean (new
// funnel) or pre-filled (edit). Resetting on close would flash empty content.
watch(
  () => props.open,
  (open) => {
    if (!open) return
    draft.value = props.funnel ? draftFromFunnel(props.funnel) : createEmptyDraft()
    showErrors.value = false
  },
  { immediate: true },
)

const isEdit = computed(() => Boolean(props.funnel))
const validation = computed(() => validateDraft(draft.value))

const nameError = computed(() => {
  if (!showErrors.value || !validation.value.nameError) return undefined
  return t(`funnels.builder.errors.name.${validation.value.nameError}`)
})

const windowOptions = computed(() =>
  CONVERSION_WINDOW_DAYS.map((days) => ({
    value: String(days),
    label: t('funnels.builder.windowDays', { count: days }, days),
  })),
)

const countOptions = computed(() => [
  { value: 'unique_users', label: t('funnels.builder.uniqueUsers') },
  { value: 'sessions', label: t('funnels.builder.sessions') },
])

function patchStep(uid: string, patch: Partial<DraftStep>) {
  draft.value.steps = draft.value.steps.map((step) =>
    step.uid === uid ? { ...step, ...patch } : step,
  )
}

function addStep() {
  if (canAddStep(draft.value)) draft.value.steps = [...draft.value.steps, createDraftStep()]
}

function removeStep(uid: string) {
  if (canRemoveStep(draft.value)) {
    draft.value.steps = draft.value.steps.filter((step) => step.uid !== uid)
  }
}

function moveStep(index: number, direction: -1 | 1) {
  draft.value.steps = reorderStep(draft.value, index, direction)
}

function stepHasError(uid: string): boolean {
  return showErrors.value && Boolean(validation.value.stepErrors[uid])
}

function onSave() {
  if (!validation.value.valid) {
    showErrors.value = true
    return
  }
  emit('save', toCreateRequest(draft.value))
}

function onCount(value: string) {
  draft.value.countMode = value as CountMode
}

function onWindow(value: string) {
  draft.value.conversionWindowDays = Number(value)
}

const stepCountLabel = computed(() =>
  t('funnels.builder.stepCount', { current: draft.value.steps.length, max: 16 }),
)
</script>

<template>
  <Modal
    :open="open"
    size="lg"
    :title="isEdit ? t('funnels.editFunnel') : t('funnels.newFunnel')"
    :close-label="t('common.close')"
    @update:open="emit('update:open', $event)"
  >
    <div class="flex flex-col gap-5">
      <FormField :label="t('funnels.builder.title')" required :error="nameError">
        <template #default="{ id, describedBy, invalid }">
          <Input
            :id="id"
            v-model="draft.name"
            :aria-describedby="describedBy"
            :error="invalid"
            :placeholder="t('funnels.builder.namePlaceholder')"
            maxlength="200"
          />
        </template>
      </FormField>

      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-fg-primary">{{ t('funnels.builder.steps') }}</h3>
          <span class="text-xs text-fg-muted">{{ stepCountLabel }}</span>
        </div>

        <p
          v-if="showErrors && validation.stepsError"
          role="alert"
          class="text-xs text-negative-text"
        >
          {{ t('funnels.builder.errors.steps.tooFew') }}
        </p>

        <ol class="flex flex-col gap-2">
          <FunnelStepRow
            v-for="(step, index) in draft.steps"
            :key="step.uid"
            :step="step"
            :position="index + 1"
            :invalid="stepHasError(step.uid)"
            :can-move-up="index > 0"
            :can-move-down="index < draft.steps.length - 1"
            :can-remove="canRemoveStep(draft)"
            @update="patchStep(step.uid, $event)"
            @remove="removeStep(step.uid)"
            @move="moveStep(index, $event)"
          />
        </ol>

        <Button
          variant="outline"
          size="sm"
          class="w-fit"
          :disabled="!canAddStep(draft)"
          @click="addStep"
        >
          <template #leading><Plus class="size-4" aria-hidden="true" /></template>
          {{ t('funnels.builder.addStep') }}
        </Button>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField :label="t('funnels.conversionWindow')">
          <template #default="{ id }">
            <Select
              :id="id"
              :model-value="String(draft.conversionWindowDays)"
              :options="windowOptions"
              @update:model-value="onWindow"
            />
          </template>
        </FormField>

        <FormField :label="t('funnels.builder.countBy')">
          <template #default>
            <SegmentedControl
              :model-value="draft.countMode"
              :options="countOptions"
              :aria-label="t('funnels.builder.countBy')"
              @update:model-value="onCount"
            />
          </template>
        </FormField>
      </div>
    </div>

    <template #footer>
      <Button variant="outline" @click="emit('update:open', false)">{{ t('common.cancel') }}</Button>
      <Button :loading="saving" @click="onSave">{{ t('funnels.builder.save') }}</Button>
    </template>
  </Modal>
</template>
