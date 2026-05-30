<script setup lang="ts">
// =============================================================================
// RangeSlider — labelled accessible range input (screens.md §2.1 sliders)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Intensity / radius controls for the heatmap overlay. A native <input
// type="range"> keeps full keyboard + AT support for free; we only theme the
// track/thumb with design tokens and surface the current value as text.
// =============================================================================
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: number
    min?: number
    max?: number
    step?: number
    label: string
    /** Optional formatter for the trailing value readout. */
    format?: (value: number) => string
  }>(),
  { min: 0, max: 100, step: 1 },
)

const emit = defineEmits<{ 'update:modelValue': [value: number] }>()

const readout = computed(() =>
  props.format ? props.format(props.modelValue) : String(props.modelValue),
)

function onInput(event: Event) {
  emit('update:modelValue', Number((event.target as HTMLInputElement).value))
}
</script>

<template>
  <label class="range-slider">
    <span class="range-slider__head">
      <span class="range-slider__label">{{ label }}</span>
      <span class="range-slider__value">{{ readout }}</span>
    </span>
    <input
      class="range-slider__input"
      type="range"
      :min="min"
      :max="max"
      :step="step"
      :value="modelValue"
      :aria-label="label"
      :aria-valuetext="readout"
      @input="onInput"
    />
  </label>
</template>

<style scoped>
.range-slider {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-1);
}

.range-slider__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sf-space-2);
  font-size: var(--sf-text-xs);
}

.range-slider__label {
  color: var(--sf-fg-secondary);
}

.range-slider__value {
  font-variant-numeric: tabular-nums;
  color: var(--sf-fg-primary);
}

.range-slider__input {
  width: 100%;
  height: 1rem;
  appearance: none;
  background: transparent;
  cursor: pointer;
}

.range-slider__input:focus-visible {
  outline: 2px solid var(--sf-border-focus);
  outline-offset: 4px;
  border-radius: var(--sf-radius-full);
}

.range-slider__input::-webkit-slider-runnable-track {
  height: 0.25rem;
  border-radius: var(--sf-radius-full);
  background: var(--sf-accent-subtle);
}

.range-slider__input::-moz-range-track {
  height: 0.25rem;
  border-radius: var(--sf-radius-full);
  background: var(--sf-accent-subtle);
}

.range-slider__input::-webkit-slider-thumb {
  appearance: none;
  width: 0.875rem;
  height: 0.875rem;
  margin-top: -0.3125rem;
  border-radius: var(--sf-radius-full);
  background: var(--sf-accent);
}

.range-slider__input::-moz-range-thumb {
  width: 0.875rem;
  height: 0.875rem;
  border: 0;
  border-radius: var(--sf-radius-full);
  background: var(--sf-accent);
}
</style>
