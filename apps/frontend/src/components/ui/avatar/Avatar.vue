<script setup lang="ts">
// =============================================================================
// Avatar — image → initials → icon fallback (components.md §17)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import { AvatarFallback, AvatarImage, AvatarRoot } from 'reka-ui'
import { User } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

type AvatarSize = 'sm' | 'md' | 'lg' | 'xl'

const props = withDefaults(
  defineProps<{ src?: string; name?: string; size?: AvatarSize; class?: string }>(),
  { size: 'md' },
)

const SIZE_CLASS: Record<AvatarSize, string> = {
  sm: 'size-6 text-[0.625rem]',
  md: 'size-8 text-xs',
  lg: 'size-10 text-sm',
  xl: 'size-14 text-lg',
}

const initials = computed(() => {
  if (!props.name) return ''
  return props.name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
})
</script>

<template>
  <AvatarRoot
    :class="
      cn(
        'inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full bg-bg-subtle font-medium text-fg-secondary',
        SIZE_CLASS[size],
        $props.class,
      )
    "
  >
    <AvatarImage v-if="src" :src="src" :alt="name ?? ''" class="size-full object-cover" />
    <AvatarFallback class="flex size-full items-center justify-center">
      <span v-if="initials">{{ initials }}</span>
      <User v-else class="size-1/2" aria-hidden="true" />
    </AvatarFallback>
  </AvatarRoot>
</template>
