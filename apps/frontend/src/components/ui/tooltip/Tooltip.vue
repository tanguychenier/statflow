<script setup lang="ts">
// =============================================================================
// Tooltip — Reka UI Tooltip wrapper (components.md §15.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// 150ms open delay, arrow, 240px max width. The trigger goes in the default
// slot; `content` prop or `content` slot supplies the label.
// =============================================================================
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

withDefaults(
  defineProps<{ content?: string; side?: 'top' | 'right' | 'bottom' | 'left'; delay?: number }>(),
  { side: 'top', delay: 150 },
)
</script>

<template>
  <TooltipProvider :delay-duration="delay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <slot />
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="side"
          :side-offset="6"
          class="z-50 max-w-60 rounded-md border border-border-strong bg-bg-overlay px-2.5 py-1.5 text-xs text-fg-primary shadow-lg"
        >
          <slot name="content">{{ content }}</slot>
          <TooltipArrow class="fill-bg-overlay" :width="8" :height="4" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>
</template>
