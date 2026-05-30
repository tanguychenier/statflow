<script setup lang="ts">
// =============================================================================
// CommandPalette — ⌘K palette (components.md §8)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Reka UI Dialog + custom list. Fuzzy-searches navigation + host-supplied
// actions, groups them by section, and supports full keyboard control: arrows
// navigate, Enter activates, Escape closes (focus restored by Reka). Opening is
// driven by the useCommandPalette singleton (⌘K / Ctrl+K).
// =============================================================================
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'
import { Search } from 'lucide-vue-next'
import { useCommandPalette } from '@/composables/useCommandPalette'
import {
  NAVIGATION_COMMANDS,
  fuzzyMatch,
  optionDomId,
  activeDescendantId,
  type Command,
  type CommandSection,
} from './commands'

const props = withDefaults(defineProps<{ extraCommands?: Command[] }>(), {
  extraCommands: () => [],
})

const LISTBOX_ID = 'command-palette-listbox'

const { t } = useI18n()
const router = useRouter()
const { isOpen, close } = useCommandPalette()

const query = ref('')
const activeIndex = ref(0)

const allCommands = computed<Command[]>(() => [...NAVIGATION_COMMANDS, ...props.extraCommands])

const filtered = computed<Command[]>(() =>
  allCommands.value.filter((command) => fuzzyMatch(query.value, t(command.labelKey))),
)

const SECTION_ORDER: CommandSection[] = ['recent', 'navigate', 'actions']

const grouped = computed(() =>
  SECTION_ORDER.map((section) => ({
    section,
    commands: filtered.value.filter((command) => command.section === section),
  })).filter((group) => group.commands.length > 0),
)

const hasResults = computed(() => filtered.value.length > 0)
const activeDescendant = computed(() =>
  activeDescendantId(activeIndex.value, filtered.value.length),
)

// Reset selection whenever the result set changes so Enter targets a valid row.
watch([filtered, isOpen], () => {
  activeIndex.value = 0
  if (!isOpen.value) query.value = ''
})

function runCommand(command: Command) {
  close()
  if (command.run) command.run()
  else if (command.to) router.push({ name: command.to })
}

function onKeydown(event: KeyboardEvent) {
  const max = filtered.value.length - 1
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(max, activeIndex.value + 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(0, activeIndex.value - 1)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const command = filtered.value[activeIndex.value]
    if (command) runCommand(command)
  }
}

function flatIndex(section: CommandSection, indexInSection: number): number {
  // Offset by the lengths of the visible groups ordered before this one.
  let offset = 0
  for (const group of grouped.value) {
    if (group.section === section) break
    offset += group.commands.length
  }
  return offset + indexInSection
}
</script>

<template>
  <DialogRoot :open="isOpen" @update:open="(value) => !value && close()">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-[70] bg-black/50 backdrop-blur-sm" />
      <DialogContent
        class="fixed left-1/2 top-[15vh] z-[70] w-[560px] max-w-[calc(100vw-32px)] -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-bg-overlay shadow-xl outline-none"
        @keydown="onKeydown"
      >
        <DialogTitle class="sr-only">{{ t('commandPalette.placeholder') }}</DialogTitle>
        <div class="flex items-center gap-2 border-b border-border px-4">
          <Search class="size-4 text-fg-muted" aria-hidden="true" />
          <input
            v-model="query"
            type="text"
            role="combobox"
            :aria-expanded="hasResults"
            :aria-controls="LISTBOX_ID"
            :aria-activedescendant="activeDescendant"
            aria-autocomplete="list"
            autocomplete="off"
            class="h-12 w-full bg-transparent text-sm text-fg-primary outline-none placeholder:text-fg-muted"
            :placeholder="t('commandPalette.placeholder')"
            :aria-label="t('commandPalette.placeholder')"
            autofocus
          />
          <kbd class="rounded border border-border px-1.5 py-0.5 text-[0.625rem] text-fg-muted">
            Esc
          </kbd>
        </div>

        <div
          :id="LISTBOX_ID"
          class="max-h-80 overflow-y-auto p-2"
          role="listbox"
          :aria-label="t('commandPalette.placeholder')"
        >
          <p v-if="filtered.length === 0" class="px-3 py-6 text-center text-sm text-fg-secondary">
            {{ t('commandPalette.noResults', { query }) }}
            <span class="mt-1 block text-xs text-fg-muted">{{ t('commandPalette.noResultsHint') }}</span>
          </p>
          <div v-for="group in grouped" v-else :key="group.section" class="mb-1">
            <p class="px-2 py-1 text-[0.625rem] font-semibold uppercase tracking-widest text-fg-muted">
              {{ t(`commandPalette.sections.${group.section}`) }}
            </p>
            <button
              v-for="(command, i) in group.commands"
              :id="optionDomId(flatIndex(group.section, i))"
              :key="command.id"
              type="button"
              role="option"
              tabindex="-1"
              :aria-selected="flatIndex(group.section, i) === activeIndex"
              class="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-sm text-fg-primary outline-none"
              :class="
                flatIndex(group.section, i) === activeIndex ? 'bg-accent-subtle' : 'hover:bg-bg-subtle'
              "
              @mouseenter="activeIndex = flatIndex(group.section, i)"
              @click="runCommand(command)"
            >
              <span>{{ t(command.labelKey) }}</span>
              <kbd
                v-if="command.shortcut"
                class="rounded border border-border px-1.5 py-0.5 text-[0.625rem] text-fg-muted"
              >
                {{ command.shortcut }}
              </kbd>
            </button>
          </div>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
