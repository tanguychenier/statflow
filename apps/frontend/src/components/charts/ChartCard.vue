<script setup lang="ts">
// =============================================================================
// ChartCard — card framing a chart with header actions (components.md §4.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'

defineProps<{ title: string }>()
</script>

<template>
  <Card class="flex flex-col">
    <CardHeader :title="title">
      <template v-if="$slots.actions" #actions><slot name="actions" /></template>
    </CardHeader>
    <div class="min-h-0 flex-1">
      <slot />
    </div>
    <slot name="footer" />
  </Card>
</template>
