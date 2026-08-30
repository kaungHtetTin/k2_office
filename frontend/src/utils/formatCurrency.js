export function formatCurrency(value, currency = 'MMK') {
  return `${Number(value || 0).toLocaleString()} ${currency}`
}
