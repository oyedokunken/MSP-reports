import { useState } from 'react'
import type { ReportItem, Stat } from './types'

export const useReportsState = (initialReports: ReportItem[], initialStats: Stat[]) => {
  const [layoutMode, setLayoutMode] = useState<'grid' | 'list'>('grid')
  const [reports] = useState<ReportItem[]>(initialReports)
  const [stats] = useState<Stat[]>(initialStats)
  return { layoutMode, setLayoutMode, reports, stats }
}


