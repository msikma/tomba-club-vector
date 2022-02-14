/**
 * Highlights the currently active sidebar link.
 */
 function highlightSidenavLink() {
  // The currently active location.
  const url = new URL(window.location)
  const urlFull = url.href
  const urlPartial = url.pathname + url.search
  
  // Get a list of all sidebar navigation links.
  const navs = document.querySelectorAll('#mw-panel .mw-portlet.vector-menu')
  const items = [...navs].flatMap(nav => [...nav.querySelectorAll('.vector-menu-content-list > li')])
  
  // Check which one points to the link we're at, and change its style.
  items.forEach(item => {
    const href = item.querySelector(':scope > a').getAttribute('href')
    if (href === urlFull || href === urlPartial) {
      item.classList.add('active-link')
    }
  })
}

/**
 * Ensures there's always something inside the right navigation section.
 *
 * On uneditable pages, such as special pages, the nav would otherwise
 * be completely empty.
 */
function ensureNonEmptyNav() {
  const url = new URL(window.location)
  
  // A 'read' link pointing to the current page.
  const emptyItem = `<li id="ca-view" class="selected"><a href="${url.pathname + url.search}" data-jzz-gui-player="true">Read</a></li>`
  
  const navContainer = document.querySelector('#p-views')
  const nav = navContainer.querySelector('.vector-menu-content .vector-menu-content-list')
  const items = nav.querySelectorAll(':scope > li')
  if (items.length === 0) {
    nav.innerHTML = emptyItem
  }
  
  // Ensure the portlet is visible.
  navContainer.classList.remove('emptyPortlet')
}

/**
 * Script for the Tomba Club Mediawiki skin.
 * 
 * This runs after the <footer> has been printed.
 */
function main() {
  highlightSidenavLink()
  ensureNonEmptyNav()
}

main()
