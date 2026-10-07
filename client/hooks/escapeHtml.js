/**
 * Escapes text that is shown as part of a message that is rendered as HTML, e.g. names that users entered.
 */
export function escapeHtml (text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}
