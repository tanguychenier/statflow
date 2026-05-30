// =============================================================================
// Statflow Dashboard — Class name utility
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Merge conditional class names and resolve conflicting Tailwind utilities.
 * Conflict resolution matters because shadcn-vue components pass a `class` prop
 * through to the root element, where it must win over the component's defaults.
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}
