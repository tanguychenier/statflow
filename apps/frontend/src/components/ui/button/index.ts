// =============================================================================
// Statflow Dashboard — Button variants
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { cva, type VariantProps } from 'class-variance-authority'

export { default as Button } from './Button.vue'

/**
 * Token-driven button recipe (components.md §2). Colours/sizing reference the
 * Tailwind utilities that map to semantic tokens in tokens.css, so the same
 * class set adapts to dark/light automatically.
 */
export const buttonVariants = cva(
  'sf-btn inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md font-medium ' +
    'transition-colors outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' +
    'aria-disabled:pointer-events-none aria-disabled:opacity-40 active:scale-[0.97]',
  {
    variants: {
      variant: {
        default: 'bg-accent text-white hover:bg-accent-hover',
        secondary: 'bg-bg-subtle text-fg-primary hover:bg-bg-muted',
        ghost: 'bg-transparent text-fg-secondary hover:bg-bg-subtle hover:text-fg-primary',
        outline: 'border border-border bg-transparent text-fg-primary hover:bg-bg-subtle',
        destructive: 'bg-negative text-white hover:opacity-90',
        link: 'bg-transparent text-accent-text underline-offset-4 hover:underline',
      },
      size: {
        xs: 'h-7 px-2.5 text-xs',
        sm: 'h-8 px-3 text-sm',
        default: 'h-9 px-4 text-sm',
        lg: 'h-10 px-5 text-base',
        icon: 'h-9 w-9',
        'icon-sm': 'h-7 w-7',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
)

export type ButtonVariant = NonNullable<VariantProps<typeof buttonVariants>['variant']>
export type ButtonSize = NonNullable<VariantProps<typeof buttonVariants>['size']>
