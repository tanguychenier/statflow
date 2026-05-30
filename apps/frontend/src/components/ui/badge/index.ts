// =============================================================================
// Statflow Dashboard — Badge variants (components.md §14.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { cva, type VariantProps } from 'class-variance-authority'

export { default as Badge } from './Badge.vue'
export { default as Chip } from './Chip.vue'
export { default as ProBadge } from './ProBadge.vue'

export const badgeVariants = cva(
  'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.625rem] font-semibold uppercase tracking-widest',
  {
    variants: {
      variant: {
        default: 'bg-bg-subtle text-fg-secondary',
        success: 'bg-positive-bg text-positive-text',
        warning: 'bg-warning-bg text-warning-text',
        error: 'bg-negative-bg text-negative-text',
        info: 'bg-info-bg text-info-text',
        accent: 'bg-accent-subtle text-accent-text',
        outline: 'border border-border text-fg-secondary',
      },
    },
    defaultVariants: { variant: 'default' },
  },
)

export type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>
