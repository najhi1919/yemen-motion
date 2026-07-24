export type YmLocale = 'ar' | 'en'

const ARABIC_DIGITS = /[\u0660-\u0669\u06f0-\u06f9]/g

export function toLatinDigits(value: string | number): string {
  return String(value).replace(ARABIC_DIGITS, digit => {
    const code = digit.charCodeAt(0)
    return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660)
  })
}

export function localeWithLatinNumerals(locale: YmLocale): string {
  return locale === 'ar' ? 'ar-YE-u-nu-latn' : 'en-US-u-nu-latn'
}

export function formatYmNumber(
  value: number,
  locale: YmLocale,
  options: Intl.NumberFormatOptions = {}
): string {
  return toLatinDigits(new Intl.NumberFormat(localeWithLatinNumerals(locale), options).format(value))
}

function parsedDate(value: string | Date | null | undefined): Date | null {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function dateParts(value: Date, locale: YmLocale): Record<string, string> {
  return Object.fromEntries(
    new Intl.DateTimeFormat(localeWithLatinNumerals(locale), {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    })
      .formatToParts(value)
      .filter(part => part.type !== 'literal')
      .map(part => [part.type, toLatinDigits(part.value)])
  )
}

export function formatYmDate(
  value: string | Date | null | undefined,
  locale: YmLocale
): string {
  const date = parsedDate(value)
  if (!date) return '—'
  const parts = dateParts(date, locale)
  return `${parts.day}/${parts.month}/${parts.year}`
}

export function formatYmDateTime(
  value: string | Date | null | undefined,
  locale: YmLocale
): string {
  const date = parsedDate(value)
  if (!date) return '—'
  const time = toLatinDigits(new Intl.DateTimeFormat(localeWithLatinNumerals(locale), {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23'
  }).format(date))
  return `${formatYmDate(date, locale)} · ${time}`
}
