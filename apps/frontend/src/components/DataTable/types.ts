// =============================================================================
// Statflow Dashboard — DataTable column model
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

export type SortDirection = 'asc' | 'desc'

export interface SortState {
  key: string
  direction: SortDirection
}

export interface ColumnDef<Row> {
  /** Unique column key; also the default value accessor on the row. */
  key: string
  /** Header label (already translated by the caller). */
  header: string
  /** Right-align + monospace for numeric values (components.md §5.3). */
  numeric?: boolean
  sortable?: boolean
  /** Pixel width or CSS grid track. Omit for auto. */
  width?: string
  /** Custom value accessor; defaults to `row[key]`. */
  accessor?: (row: Row) => unknown
}

export type Density = 'compact' | 'default' | 'relaxed'
