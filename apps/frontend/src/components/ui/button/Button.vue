<script setup lang="ts">
// =============================================================================
// Button — token-driven button (components.md §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Renders as a Reka UI Primitive so callers can swap the element (`as`) or
// render-as-child (`asChild`) — e.g. wrap a RouterLink while keeping styles.
// Loading state swaps the leading slot for a spinner and blocks interaction
// without removing the element from the accessibility tree.
// =============================================================================
import { computed } from 'vue'
import { Primitive, type PrimitiveProps } from 'reka-ui'
import { Loader2 } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { buttonVariants, type ButtonSize, type ButtonVariant } from './index'

defineOptions({ inheritAttrs: true })

const props = withDefaults(
  defineProps<{
    variant?: ButtonVariant
    size?: ButtonSize
    loading?: boolean
    disabled?: boolean
    as?: PrimitiveProps['as']
    asChild?: boolean
    class?: string
  }>(),
  { variant: 'default', size: 'default', loading: false, disabled: false, as: 'button' },
)

const isDisabled = computed(() => props.disabled || props.loading)
const classes = computed(() => cn(buttonVariants({ variant: props.variant, size: props.size }), props.class))

// Disabled buttons stay in the accessibility tree (aria-disabled) but out of the
// tab order (tabindex=-1), per accessibility.md §3.3 — the native `disabled`
// attribute is intentionally avoided so screen readers still announce them.
function onClickCapture(event: MouseEvent) {
  if (isDisabled.value) {
    event.preventDefault()
    event.stopImmediatePropagation()
  }
}
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :class="classes"
    :aria-disabled="isDisabled || undefined"
    :tabindex="isDisabled ? -1 : undefined"
    :aria-busy="loading || undefined"
    :data-loading="loading || undefined"
    @click.capture="onClickCapture"
  >
    <Loader2 v-if="loading" class="sf-btn__spinner size-4 animate-spin" aria-hidden="true" />
    <slot v-else name="leading" />
    <slot />
    <slot name="trailing" />
  </Primitive>
</template>
