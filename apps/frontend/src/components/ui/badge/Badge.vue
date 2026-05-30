<script setup lang="ts">
// =============================================================================
// Badge — status pill (components.md §14.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { badgeVariants, type BadgeVariant } from './index'

const props = withDefaults(defineProps<{ variant?: BadgeVariant; class?: string }>(), {
  variant: 'default',
})

const classes = computed(() => cn(badgeVariants({ variant: props.variant }), props.class))
</script>

<template>
  <span :class="classes"><slot /></span>
</template>
