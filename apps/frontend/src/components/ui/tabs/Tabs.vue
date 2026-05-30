<script setup lang="ts">
// =============================================================================
// Tabs — underline tabs on Reka UI Tabs (components.md §16.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Sub-navigation within a page. Items render in the tab list; the matching
// panel content is provided per-value via a scoped slot named after the value.
// =============================================================================
import {
  TabsContent,
  TabsIndicator,
  TabsList,
  TabsRoot,
  TabsTrigger,
} from 'reka-ui'

interface TabItem {
  value: string
  label: string
}

withDefaults(defineProps<{ modelValue: string; items: TabItem[] }>(), {})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <TabsRoot :model-value="modelValue" @update:model-value="emit('update:modelValue', $event as string)">
    <TabsList class="relative flex gap-1 border-b border-border">
      <TabsTrigger
        v-for="item in items"
        :key="item.value"
        :value="item.value"
        class="relative px-3 py-2 text-sm font-medium text-fg-secondary transition-colors hover:text-fg-primary data-[state=active]:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus outline-none"
        @click="emit('update:modelValue', item.value)"
      >
        {{ item.label }}
      </TabsTrigger>
      <TabsIndicator
        class="absolute bottom-0 left-0 h-0.5 w-[var(--reka-tabs-indicator-size)] translate-x-[var(--reka-tabs-indicator-position)] bg-accent transition-[width,transform] duration-200"
      />
    </TabsList>
    <TabsContent v-for="item in items" :key="item.value" :value="item.value" class="pt-4 outline-none">
      <slot :name="item.value" />
    </TabsContent>
  </TabsRoot>
</template>
