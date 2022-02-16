// Tomba Club Vector Skin v2 <https://github.com/msikma/tomba-club-vector/>
// MIT License

/**
 * Encloses namespaces in the title inside a <span>.
 *
 * This can be used to style namespaces in titles, e.g. the "User:" part of "User:Dada78641".
 */
function encloseTitleNamespace() {
  // These are supposed to be deprecated in favor of mw.config.get('wgTitle') etc.,
  // but the 'mw' object is not available right from the start at this point.
  const ns = RLCONF.wgCanonicalNamespace
  const nsTitle = RLCONF.wgPageName.replace(/_/g, ' ')
  const title = RLCONF.wgTitle
  const nsSplit = nsTitle.split(title)
  
  // Only do something if we're not in article space.
  if (!ns || nsSplit.length <= 1) {
    return
  }
  
  const split = nsTitle.split(':')
  const pageNs = split[0]
  const pageDelimiter = ':'
  const pageTitle = split.slice(1).join(':')
  
  document.querySelector('#firstHeading').innerHTML = `<span id="page_ns">${pageNs}</span><span id="page_delimiter">${pageDelimiter}</span><span id="page_title">${pageTitle}</span>`
}
