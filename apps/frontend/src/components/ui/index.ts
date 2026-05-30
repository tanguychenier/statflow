// =============================================================================
// Statflow Dashboard — Design-system UI barrel
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Single import surface for the primitive component library (components.md).
// Screen agents: `import { Button, Card, Modal, ... } from '@/components/ui'`.
// =============================================================================

export { Button, buttonVariants } from './button'
export type { ButtonVariant, ButtonSize } from './button'

export { Input, Textarea, Label, FormField } from './input'

export { Card, CardHeader, CardFooter } from './card'

export { Badge, Chip, ProBadge, badgeVariants } from './badge'
export type { BadgeVariant } from './badge'

export { Skeleton, SkeletonText, SkeletonCard } from './skeleton'

export { Spinner } from './spinner'

export { Divider } from './divider'

export { EmptyState } from './empty-state'
export type { EmptyStateVariant } from './empty-state'

export { Modal, ConfirmDialog } from './dialog'
export type { ModalSize } from './dialog'

export { Tooltip } from './tooltip'
export { Popover } from './popover'

export { Select } from './select'
export { Switch } from './switch'
export { Checkbox } from './checkbox'

export { Tabs, SegmentedControl } from './tabs'
export { Avatar } from './avatar'

export { Toaster } from './toast'
export { useToast, toast } from '@/composables/useToast'
export type { ToastItem, ToastVariant, ToastOptions, ToastAction } from '@/composables/useToast'
