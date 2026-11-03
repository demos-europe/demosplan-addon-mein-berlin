import { escapeHtml } from './escapeHtml'

/**
 * A bold heading and a list of at most the delivered names, "…" stands for the rest.
 */
export function nameListHtml (heading, names, count) {
  const items = [...names.map(escapeHtml), ...(count > names.length ? ['…'] : [])]

  return `<p class="mb-2"><strong>${heading}</strong></p>`
    + `<ul class="list-disc pl-4 mb-2">${items.map(item => `<li>${item}</li>`).join('')}</ul>`
}
