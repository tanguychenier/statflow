<script setup lang="ts">
// =============================================================================
// GeoMap — choropleth sessions/visitors by country (data-viz.md §3.5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Registers the bundled world topology (local, no CDN) on mount before the
// chart renders. While the topology lazy-loads, a skeleton is shown.
// =============================================================================
import { computed, ref, onMounted } from 'vue'
import ChartWrapper from './ChartWrapper.vue'
import { buildGeoMapOption } from '@/charts/options'
import { useChartFormat } from '@/composables/useChartFormat'
import { registerWorldMap, WORLD_MAP_NAME } from '@/charts/geo'

const props = withDefaults(
  defineProps<{
    title: string
    data: Array<{ name: string; value: number }>
    loading?: boolean
    error?: boolean
    height?: number
  }>(),
  { loading: false, error: false, height: 360 },
)

const emit = defineEmits<{ retry: []; click: [params: unknown] }>()
const { formatters } = useChartFormat()

const mapReady = ref(false)
onMounted(async () => {
  await registerWorldMap()
  mapReady.value = true
})

const isEmpty = computed(() => props.data.length === 0)
const option = computed(() => buildGeoMapOption(WORLD_MAP_NAME, props.data, formatters.value))
</script>

<template>
  <ChartWrapper
    :title="title"
    :option="option"
    :loading="loading || !mapReady"
    :error="error"
    :empty="isEmpty"
    :height="height"
    @retry="emit('retry')"
    @click="emit('click', $event)"
  >
    <template v-if="$slots.table" #table><slot name="table" /></template>
  </ChartWrapper>
</template>
