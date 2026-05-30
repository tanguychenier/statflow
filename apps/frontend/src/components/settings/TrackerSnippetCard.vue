<script setup lang="ts">
// =============================================================================
// TrackerSnippetCard — install snippet + public key rotation (screens.md §6.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Shows the paste-and-forget loading snippet built from the site's public key
// and domain, with a copy button, and (for managers) a key-rotation action. The
// rotation result includes a grace window during which the old key keeps working
// (RotateTrackerKeyResponse.old_key_valid_until); we surface it so operators can
// redeploy without dropping events. Snippet text is built by the pure model.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, Copy, RefreshCw } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Button from '@/components/ui/button/Button.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import { ConfirmDialog } from '@/components/ui/dialog'
import { buildTrackerSnippet } from './settingsModel'
import type { Site } from '@/api/types'

const props = withDefaults(
  defineProps<{
    site: Site
    /** Origin the tracker is served from (operator's own instance, no CDN). */
    origin: string
    rotating?: boolean
    /** Grace deadline for the previous key after the most recent rotation. */
    oldKeyValidUntil?: string | null
    readonly?: boolean
  }>(),
  { rotating: false, oldKeyValidUntil: null, readonly: false },
)

const emit = defineEmits<{ rotate: [] }>()

const { t, d } = useI18n()

const snippet = computed(() => buildTrackerSnippet(props.site.tracker_key, props.site.domain, props.origin))

const copied = ref(false)
let copyTimer: ReturnType<typeof setTimeout> | undefined

async function copy() {
  try {
    await navigator.clipboard.writeText(snippet.value)
    copied.value = true
    clearTimeout(copyTimer)
    copyTimer = setTimeout(() => (copied.value = false), 2000)
  } catch {
    copied.value = false
  }
}

const rotateOpen = ref(false)

function confirmRotate() {
  emit('rotate')
  rotateOpen.value = false
}

const graceDeadline = computed(() => {
  if (!props.oldKeyValidUntil) return null
  return d(new Date(props.oldKeyValidUntil), 'long')
})
</script>

<template>
  <Card>
    <CardHeader :title="t('settings.general.trackingSnippet')">
      <template #subtitle>
        <p class="mt-0.5 text-xs text-fg-secondary">{{ t('settings.tracker.snippetHint') }}</p>
      </template>
    </CardHeader>

    <div class="relative">
      <pre
        class="overflow-x-auto rounded-md border border-border bg-bg-base p-3 font-mono text-xs leading-relaxed text-fg-secondary"
      ><code>{{ snippet }}</code></pre>
      <Button
        variant="outline"
        size="sm"
        class="absolute right-2 top-2"
        :aria-label="copied ? t('common.copied') : t('common.copy')"
        @click="copy"
      >
        <template #leading>
          <Check v-if="copied" class="size-4 text-positive-text" aria-hidden="true" />
          <Copy v-else class="size-4" aria-hidden="true" />
        </template>
        {{ copied ? t('common.copied') : t('common.copy') }}
      </Button>
    </div>

    <dl class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
      <dt class="text-fg-muted">{{ t('settings.tracker.publicKey') }}</dt>
      <dd class="font-mono text-fg-secondary">{{ site.tracker_key }}</dd>
    </dl>

    <div
      v-if="graceDeadline"
      class="mt-3 flex items-start gap-2 rounded-md border border-border bg-bg-subtle p-3 text-xs text-fg-secondary"
    >
      <Badge variant="warning">{{ t('settings.tracker.rotated') }}</Badge>
      <span>{{ t('settings.tracker.gracePeriod', { date: graceDeadline }) }}</span>
    </div>

    <div v-if="!readonly" class="mt-5 flex items-center justify-between gap-3">
      <p class="text-xs text-fg-muted">{{ t('settings.tracker.rotateHint') }}</p>
      <Button variant="outline" size="sm" :loading="rotating" @click="rotateOpen = true">
        <template #leading><RefreshCw class="size-4" aria-hidden="true" /></template>
        {{ t('settings.tracker.rotateKey') }}
      </Button>
    </div>

    <ConfirmDialog
      :open="rotateOpen"
      :title="t('settings.tracker.rotateConfirm.title')"
      :description="t('settings.tracker.rotateConfirm.description')"
      :confirm-label="t('settings.tracker.rotateKey')"
      :cancel-label="t('common.cancel')"
      confirm-variant="default"
      :loading="rotating"
      @update:open="rotateOpen = $event"
      @confirm="confirmRotate"
      @cancel="rotateOpen = false"
    />
  </Card>
</template>
