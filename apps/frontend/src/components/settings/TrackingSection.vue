<script setup lang="ts">
// =============================================================================
// TrackingSection — excluded IPs, allowed domains, retention, collection toggles
// Copyright (c) Tanguy Chénier — AGPL-3.0  (screens.md §6.2)
//
// Owns a local TrackingDraft seeded from the loaded SiteSettings; emits a full
// SiteSettings object on save (the API PUT replaces the whole object, so the
// model merges back fields this screen does not edit). Validation lives in
// settingsModel; the cookieless note is locked/informational (privacy.md).
// =============================================================================
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Lock } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import FormField from '@/components/ui/input/FormField.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import Button from '@/components/ui/button/Button.vue'
import { Divider } from '@/components/ui/divider'
import ListEditor from './ListEditor.vue'
import {
  MAX_EXCLUDED_IPS,
  MAX_ALLOWED_DOMAINS,
  RETENTION_MAX_DAYS,
  RETENTION_MIN_DAYS,
  RETENTION_PRESETS,
  draftFromSettings,
  isValidDomainPattern,
  isValidIpOrCidr,
  settingsFromDraft,
  validateTracking,
  type TrackingDraft,
} from './settingsModel'
import type { SiteSettings } from '@/api/types'

const props = withDefaults(
  defineProps<{ settings: SiteSettings; saving?: boolean; readonly?: boolean }>(),
  { saving: false, readonly: false },
)

const emit = defineEmits<{ save: [body: SiteSettings] }>()

const { t } = useI18n()

const draft = reactive<TrackingDraft>(draftFromSettings(props.settings))

function isPreset(days: number): boolean {
  return (RETENTION_PRESETS as readonly number[]).includes(days)
}

// Selecting "Custom…" must reveal the number field even while the value is still
// a preset number, so an explicit flag tracks the chosen mode independently.
const customRetention = ref(!isPreset(draft.dataRetentionDays))

watch(
  () => props.settings,
  (settings) => {
    Object.assign(draft, draftFromSettings(settings))
    customRetention.value = !isPreset(draft.dataRetentionDays)
  },
)

const validation = computed(() => validateTracking(draft))

const retentionOptions = computed(() => [
  ...RETENTION_PRESETS.map((days) => ({
    value: String(days),
    label: t('common.days', { count: days }, days),
  })),
  { value: 'custom', label: t('settings.tracking.retention.custom') },
])

const showCustomRetention = computed(() => customRetention.value || !isPreset(draft.dataRetentionDays))
const retentionSelectValue = computed(() =>
  showCustomRetention.value ? 'custom' : String(draft.dataRetentionDays),
)

function onRetentionSelect(value: string) {
  if (value === 'custom') {
    customRetention.value = true
  } else {
    customRetention.value = false
    draft.dataRetentionDays = Number(value)
  }
}

function onRetentionInput(value: string) {
  draft.dataRetentionDays = Number(value)
}

type BooleanToggleKey =
  | 'trackClicks'
  | 'trackScroll'
  | 'trackEngagementTime'
  | 'trackOutboundLinks'
  | 'hashBasedRouting'

const toggles = computed<{ key: BooleanToggleKey; label: string }[]>(() => [
  { key: 'trackClicks', label: t('settings.tracking.collect.clicks') },
  { key: 'trackScroll', label: t('settings.tracking.collect.scroll') },
  { key: 'trackEngagementTime', label: t('settings.tracking.collect.engagement') },
  { key: 'trackOutboundLinks', label: t('settings.tracking.collect.outbound') },
  { key: 'hashBasedRouting', label: t('settings.tracking.collect.hashRouting') },
])

function setToggle(key: BooleanToggleKey, value: boolean) {
  draft[key] = value
}

function onSave() {
  if (!validation.value.valid) return
  emit('save', settingsFromDraft(draft, props.settings))
}
</script>

<template>
  <Card>
    <CardHeader :title="t('settings.sections.tracking')" />

    <div class="flex flex-col gap-6">
      <!-- Cookieless note — locked, informational (privacy.md / ADR-0008) -->
      <div
        class="flex items-start gap-2 rounded-md border border-border bg-bg-subtle p-3 text-xs text-fg-secondary"
      >
        <Lock class="mt-0.5 size-4 shrink-0 text-fg-muted" aria-hidden="true" />
        <p>{{ t('settings.tracking.cookielessNote') }}</p>
      </div>

      <FormField
        :label="t('settings.tracking.excludedIps.label')"
        :hint="t('settings.tracking.excludedIps.hint', { max: MAX_EXCLUDED_IPS })"
      >
        <template #default>
          <ListEditor
            v-model="draft.excludedIps"
            :validate="isValidIpOrCidr"
            :placeholder="t('settings.tracking.excludedIps.placeholder')"
            :input-aria-label="t('settings.tracking.excludedIps.label')"
            :add-label="t('settings.actions.add')"
            :remove-label="t('settings.actions.remove')"
            :empty-label="t('settings.tracking.excludedIps.empty')"
            :invalid-label="t('settings.tracking.excludedIps.invalid')"
            :disabled="readonly"
          />
        </template>
      </FormField>

      <Divider />

      <FormField
        :label="t('settings.tracking.allowedDomains.label')"
        :hint="t('settings.tracking.allowedDomains.hint', { max: MAX_ALLOWED_DOMAINS })"
      >
        <template #default>
          <ListEditor
            v-model="draft.allowedDomains"
            :validate="isValidDomainPattern"
            :placeholder="t('settings.tracking.allowedDomains.placeholder')"
            :input-aria-label="t('settings.tracking.allowedDomains.label')"
            :add-label="t('settings.actions.add')"
            :remove-label="t('settings.actions.remove')"
            :empty-label="t('settings.tracking.allowedDomains.empty')"
            :invalid-label="t('settings.tracking.allowedDomains.invalid')"
            :disabled="readonly"
          />
        </template>
      </FormField>

      <Divider />

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField :label="t('settings.tracking.retention.label')">
          <template #default="{ id }">
            <Select
              :id="id"
              :model-value="retentionSelectValue"
              :options="retentionOptions"
              :disabled="readonly"
              class="w-full"
              @update:model-value="onRetentionSelect"
            />
          </template>
        </FormField>

        <FormField
          v-if="showCustomRetention"
          :label="t('settings.tracking.retention.customLabel')"
          :hint="t('settings.tracking.retention.range', { min: RETENTION_MIN_DAYS, max: RETENTION_MAX_DAYS })"
          :error="validation.retentionValid ? undefined : t('settings.tracking.retention.invalid')"
        >
          <template #default="{ id, describedBy, invalid }">
            <Input
              :id="id"
              type="number"
              :model-value="draft.dataRetentionDays"
              :min="RETENTION_MIN_DAYS"
              :max="RETENTION_MAX_DAYS"
              :aria-describedby="describedBy"
              :error="invalid"
              :disabled="readonly"
              @update:model-value="onRetentionInput"
            />
          </template>
        </FormField>
      </div>

      <Divider />

      <div class="flex flex-col gap-3">
        <h3 class="text-sm font-semibold text-fg-primary">{{ t('settings.tracking.collect.title') }}</h3>

        <label
          class="flex items-center justify-between gap-3 text-sm text-fg-primary"
        >
          <span>{{ t('settings.tracking.stripQuery.label') }}</span>
          <Switch
            v-model="draft.stripQueryParams"
            :disabled="readonly"
            :aria-label="t('settings.tracking.stripQuery.label')"
          />
        </label>

        <label
          v-for="toggle in toggles"
          :key="toggle.key"
          class="flex items-center justify-between gap-3 text-sm text-fg-primary"
        >
          <span>{{ toggle.label }}</span>
          <Switch
            :model-value="draft[toggle.key]"
            :disabled="readonly"
            :aria-label="toggle.label"
            @update:model-value="(value) => setToggle(toggle.key, value)"
          />
        </label>
      </div>
    </div>

    <div v-if="!readonly" class="mt-6 flex justify-end">
      <Button :disabled="!validation.valid || saving" :loading="saving" @click="onSave">
        {{ t('settings.actions.saveChanges') }}
      </Button>
    </div>
  </Card>
</template>
