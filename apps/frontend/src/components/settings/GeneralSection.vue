<script setup lang="ts">
// =============================================================================
// GeneralSection — site name / domain / timezone (screens.md §6.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Edits the three Site fields against a local draft so unsaved edits can be
// discarded and the Save button can disable until something actually changed.
// The parent owns the mutation; this component validates, then emits a clean
// SiteUpdateRequest. Read-only when the viewer lacks manage permission.
// =============================================================================
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import FormField from '@/components/ui/input/FormField.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Button from '@/components/ui/button/Button.vue'
import { TIMEZONES } from './timezones'
import type { Site, SiteUpdateRequest } from '@/api/types'
import { isValidDomainPattern } from './settingsModel'

const props = withDefaults(
  defineProps<{ site: Site; saving?: boolean; readonly?: boolean }>(),
  { saving: false, readonly: false },
)

const emit = defineEmits<{ save: [body: SiteUpdateRequest] }>()

const { t } = useI18n()

const draft = reactive({ name: props.site.name, domain: props.site.domain, timezone: props.site.timezone })
const showErrors = reactive({ name: false, domain: false })

// Re-seed the draft if the upstream site changes (e.g. switching property).
watch(
  () => props.site,
  (site) => {
    draft.name = site.name
    draft.domain = site.domain
    draft.timezone = site.timezone
    showErrors.name = false
    showErrors.domain = false
  },
)

const timezoneOptions = computed(() => TIMEZONES.map((tz) => ({ value: tz, label: tz })))

const nameValid = computed(() => draft.name.trim().length > 0)
const domainValid = computed(() => isValidDomainPattern(draft.domain.trim()))

const nameError = computed(() =>
  showErrors.name && !nameValid.value ? t('settings.general.errors.nameRequired') : undefined,
)
const domainError = computed(() =>
  showErrors.domain && !domainValid.value ? t('settings.general.errors.domainInvalid') : undefined,
)

const isDirty = computed(
  () =>
    draft.name !== props.site.name ||
    draft.domain !== props.site.domain ||
    draft.timezone !== props.site.timezone,
)

const canSave = computed(() => isDirty.value && !props.readonly && !props.saving)

function onSave() {
  showErrors.name = true
  showErrors.domain = true
  if (!nameValid.value || !domainValid.value) return
  emit('save', {
    name: draft.name.trim(),
    domain: draft.domain.trim(),
    timezone: draft.timezone,
  })
}
</script>

<template>
  <Card>
    <CardHeader :title="t('settings.sections.general')" />

    <div class="flex flex-col gap-4">
      <FormField :label="t('settings.general.siteName')" required :error="nameError">
        <template #default="{ id, describedBy, invalid }">
          <Input
            :id="id"
            v-model="draft.name"
            :aria-describedby="describedBy"
            :error="invalid"
            :disabled="readonly"
            maxlength="120"
          />
        </template>
      </FormField>

      <FormField
        :label="t('settings.general.domain')"
        required
        :error="domainError"
        :hint="t('settings.general.domainHint')"
      >
        <template #default="{ id, describedBy, invalid }">
          <Input
            :id="id"
            v-model="draft.domain"
            :aria-describedby="describedBy"
            :error="invalid"
            :disabled="readonly"
            placeholder="example.com"
            inputmode="url"
          />
        </template>
      </FormField>

      <FormField :label="t('settings.general.timezone')">
        <template #default="{ id }">
          <Select
            :id="id"
            v-model="draft.timezone"
            :options="timezoneOptions"
            :disabled="readonly"
            class="w-full"
          />
        </template>
      </FormField>
    </div>

    <div v-if="!readonly" class="mt-5 flex justify-end">
      <Button :disabled="!canSave" :loading="saving" @click="onSave">
        {{ t('settings.actions.saveChanges') }}
      </Button>
    </div>
  </Card>
</template>
