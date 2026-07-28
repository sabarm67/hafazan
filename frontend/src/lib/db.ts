import { openDB, type DBSchema, type IDBPDatabase } from 'idb'

/**
 * Offline-first storage schema. Object stores are declared now so the
 * upgrade path is stable, but nothing writes to them yet — offline
 * sync/conflict resolution is a future phase (see docs/01-requirements-analysis.md).
 */
interface HafazanDb extends DBSchema {
  cachedAyat: {
    key: number // number_in_quran
    value: {
      numberInQuran: number
      surahNumber: number
      numberInSurah: number
      textArabicUthmani: string
      cachedAt: number
    }
  }
  pendingReviewLogs: {
    key: string // client-generated UUID, becomes the idempotency key on sync
    value: {
      id: string
      reviewSessionId: number
      ayahId: number
      payload: unknown
      createdAt: number
      syncedAt: number // 0 means "not yet synced"
    }
    indexes: { 'by-synced': number }
  }
}

const DB_NAME = 'hafazan-offline'
const DB_VERSION = 1

let dbPromise: Promise<IDBPDatabase<HafazanDb>> | null = null

export function getDb(): Promise<IDBPDatabase<HafazanDb>> {
  dbPromise ??= openDB<HafazanDb>(DB_NAME, DB_VERSION, {
    upgrade(db) {
      db.createObjectStore('cachedAyat', { keyPath: 'numberInQuran' })

      const pendingLogs = db.createObjectStore('pendingReviewLogs', { keyPath: 'id' })
      pendingLogs.createIndex('by-synced', 'syncedAt')
    },
  })

  return dbPromise
}
