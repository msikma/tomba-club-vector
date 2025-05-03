// Tomba Club Vector Skin <https://github.com/msikma/tomba-club-vector/>
// MIT License

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
  const emptyItem = `<li id="ca-view" class="selected"><a href="${url.pathname + url.search}">Read</a></li>`
  
  const navContainer = document.querySelector('#p-views')
  const nav = navContainer.querySelector('.vector-menu-content .vector-menu-content-list')
  const items = nav.querySelectorAll(':scope > li')
  if (items.length === 0) {
    nav.innerHTML = emptyItem
  }
  navContainer.classList.remove('emptyPortlet')
}

/**
 * Decorates the big tables.
 */
function decorateBigTables() {
  const bigTables = [...document.querySelectorAll('.tc-big-table')]
  bigTables.forEach(table => {
    // Container for all of this table's data.
    const tableData = {
      header: null,
      sections: null,
    }

    function getSortedRows(header, rows) {
      const direction = header.direction === 'asc' ? 1 : -1
      return rows.sort((a, b) => {
        const dataA = a.data[header.n].value
        const dataB = b.data[header.n].value
        const idxA = a.data[0].value
        const idxB = b.data[0].value
        if (dataA === dataB) {
          if (idxA === idxB) {
            return 0
          }
          return (idxA < idxB ? -1 : 1) * direction
        }
        return (dataA < dataB ? -1 : 1) * direction
      })
    }

    function flipDirection(direction) {
      return direction === 'asc' ? 'desc' : 'asc'
    }

    function sortTable(n) {
      const header = tableData.header[n]
      if (header.isActive) {
        header.direction = flipDirection(header.direction)
      }
      for (const everyHeader of tableData.header) {
        everyHeader.isActive = false
        everyHeader.el.setAttribute('data-active', everyHeader.isActive)
      }
      header.isActive = true
      header.el.setAttribute('data-direction', header.direction)
      header.el.setAttribute('data-active', header.isActive)
      for (const section of tableData.sections) {
        const rows = getSortedRows(header, section.rows)
        const allRows = [
          {el: section.separator, isSeparator: true},
          {el: section.section, isSeparator: true},
          ...rows
        ]
        for (const row of allRows) {
          section.tbody.appendChild(row.el)
          if (!row.isSeparator) {
            const allCells = [...row.el.querySelectorAll(`td:not(:nth-child(${n + 1}))`)]
            const highlightedCells = [...row.el.querySelectorAll(`td:nth-child(${n + 1})`)]
            allCells.forEach(cell => cell.classList.toggle('highlighted', false))
            highlightedCells.forEach(cell => cell.classList.toggle('highlighted', true))
          }
        }
      }
    }

    function getHeaderCols() {
      // Get a list of all table columns we can sort by.
      const header = table.querySelector('tr.header')
      const headerCols = [...header.querySelectorAll('th')]
      const headerColsWithData = headerCols.map((col, n) => {
        const defaultDirection = 'asc'
        const defaultActive = false
        const text = col.innerText
        const slug = col.getAttribute('data-slug') ?? text.toLowerCase().replaceAll(' ', '_')
        const dataType = col.getAttribute('data-type') ?? 'string'
        col.setAttribute('data-direction', defaultDirection)
        col.setAttribute('data-active', defaultActive)
        col.insertAdjacentHTML('beforeend', '<span class="sorter"></span>');
        col.addEventListener('click', ev => {
          ev.preventDefault()
          sortTable(n)
        })
        return {
          text,
          slug,
          dataType,
          isActive: defaultActive,
          direction: defaultDirection,
          el: col,
          n,
        }
      })
      return headerColsWithData
    }

    function getRowData(row) {
      // Determines the data inside the rows.
      const cols = [...row.querySelectorAll('td')]
      const data = cols.map((col, n) => {
        const header = tableData.header[n]
        const dataValue = col.getAttribute('data-value')
        const rawValue = dataValue ? dataValue : col.innerText
        let value
        if (header.dataType === 'number') {
          value = Number(rawValue)
        }
        else {
          value = rawValue
        }
        return {
          el: col,
          value,
        }
      })
      return data
    }

    function getRowSections() {
      // Get all sections, then list all the subsequent rows per section.
      const sectionRows = [...table.querySelectorAll('tr.section')]
      const rows = [...table.querySelectorAll('tr')]
      const sections = []
      let n = 0
      for (const sectionRow of sectionRows) {
        const rowsForSection = []
        let foundSectionRow = false
        for (const row of rows) {
          if (sectionRow === row) {
            foundSectionRow = true
            continue
          }
          if (foundSectionRow) {
            if (row.classList.contains('section') || row.classList.contains('header')) {
              break
            }
            rowsForSection.push(row)
          }
        }
        const rowsWithData = []
        n = 0
        for (const row of rowsForSection) {
          const rowData = getRowData(row)
          rowsWithData.push({data: rowData, el: row, n})
          n += 1
        }
        sections.push({
          n,
          section: sectionRow,
          separator: sectionRow.previousElementSibling,
          tbody: sectionRow.parentElement,
          rows: rowsWithData
        })
        n += 1
      }
      return sections
    }

    tableData.header = getHeaderCols()
    tableData.sections = getRowSections()
  })
}

/**
 * Allows the hamburger menu to be usable on mobile.
 */
function decorateHamburgerMenu() {
  const hamContainer = document.querySelector('#p-views-label > span')
  const rightNavigation = document.querySelector('#right-navigation')
  
  const boxClosed = rightNavigation.getBoundingClientRect()
  rightNavigation.style.height = 'auto'
  const boxOpen = rightNavigation.getBoundingClientRect()
  rightNavigation.style.height = `${boxClosed.height}px`

  const state = {
    isOpen: false
  }

  hamContainer.addEventListener('click', ev => {
    ev.preventDefault()
    state.isOpen = !state.isOpen
    rightNavigation.style.height = `${(state.isOpen ? boxOpen : boxClosed).height}px`
  })
}

/**
 * Script for the Tomba Club Mediawiki skin.
 * 
 * This runs after the <footer> has been printed.
 */
function main() {
  highlightSidenavLink()
  ensureNonEmptyNav()
  decorateHamburgerMenu()
  decorateBigTables()
}

main()
