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
  // The API code is very poor quality, but whatever, it works for now.
  // TODO: rewrite this sometime.
  const bigTables = [...document.querySelectorAll('.tc-big-table')]
  bigTables.forEach(table => {
    // Container for all of this table's data.
    const tableData = {
      header: null,
      sections: null,
      defaultSort: null,
      apiEndpoint: null,
      apiBaseURL: null,
      isRunningApiCall: false,
      tablePagination: null,
      pageBaseURL: null,
      sortCol: null,
      tableState: {},
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

    function getApiEndpointURLData(page, slug, direction) {
      const base = new URL(document.URL)
      const url = new URL([base.origin, tableData.apiBaseURL, tableData.apiEndpoint].join('/'));
      const searchValue = base.searchParams.get('search') ?? '';
      const pageValue = page ?? tableData.tableState.page;
      const slugValue = slug ?? tableData.tableState.slug;
      const directionValue = direction ?? tableData.tableState.direction;
      url.searchParams.set('search', `${searchValue}`);
      url.searchParams.set('page', `${pageValue}`);
      url.searchParams.set('sort', `${slugValue}`);
      url.searchParams.set('direction', `${directionValue}`);
      return {
        url: url.toString(),
        page: pageValue,
        slug: slugValue,
        direction: directionValue,
      };
    }

    async function runApiRequest(url) {
      try {
        const res = await fetch(url)
        if (!res.ok) {
          throw new Error('not 200')
        }
        const data = await res.json()
        return {success: true, err: null, data}
      }
      catch (err) {
        return {success: false, err, data: null}
      }
    }

    async function runApiCall(page, slug, direction) {
      if (tableData.isRunningApiCall) {
        return
      }
      tableData.isRunningApiCall = true;
      const urlData = getApiEndpointURLData(page, slug, direction)
      const {success, data, err} = await runApiRequest(urlData.url)
      if (success && data) {
        replaceTableRows(data.data.results.rows, data.data.results.layout)
        replaceTablePagination(data.data.results.paginationLinks)
      }
      tableData.tableState.page = urlData.page;
      tableData.tableState.slug = urlData.slug;
      tableData.tableState.direction = urlData.direction;
      tableData.sections = getRowSections();
      tableData.isRunningApiCall = false;

      const newUrl = new URL(window.location);
      newUrl.searchParams.set('page', urlData.page);
      window.history.replaceState({}, '', newUrl);
    }

    function getNewTableRows(rows, layout) {
      const tableRows = []
      for (const row of rows) {
        const tr = document.createElement('tr');
        for (const col of layout) {
          const td = document.createElement('td');
          const {slug, classes} = col;
          const inner = row[slug];
          const classItems = (classes ?? '').split(/\s+/).filter(c => c)
          classItems.forEach(cls => td.classList.add(cls));
          if (slug === tableData.tableState.slug) {
            td.classList.add('highlighted');
          }
          td.innerHTML = `<span class="inner">${inner}</span>`;
          tr.appendChild(td)
        }
        tableRows.push(tr)
      }
      return tableRows;
    }

    function getNewTablePagination(paginationLinks) {
      function pageLink(linkData, isPrevious = false, isNext = false) {
        const item = document.createElement(linkData.active ? 'a' : 'span');
        item.classList.add('item', 'blue');
        if (isPrevious) {
          item.classList.add('previous');
        }
        if (isNext) {
          item.classList.add('next');
        }
        if (isPrevious || isNext) {
          item.classList.add('icon');
          item.classList.add('icon-only');
          const itemURL = new URL(`http://example.com/${linkData.url}`)
          item.setAttribute('data-page-number', itemURL.searchParams.get('page'));
        }
        else {
          if (linkData.text) {
            item.setAttribute('data-page-number', Number(linkData.text));
          }
        }
        if (linkData.active) {
          item.setAttribute('href', linkData.url)
        }
        item.classList.toggle('active', !linkData.active);
        item.innerText = linkData.text;
        if (linkData.type === 'ellipsis') {
          item.innerText = '...';
          item.classList.toggle('active', false);
        }
        return item;
      }
      const paginationDiv = document.createElement('div');
      paginationDiv.classList.add('action-sets');

      const pageNumbersDiv = document.createElement('div');
      pageNumbersDiv.classList.add('action-set');
      pageNumbersDiv.classList.add('page-numbers');
      paginationLinks.pages.forEach(pageNum => {
        pageNumbersDiv.appendChild(pageLink(pageNum));
      });

      const prevNextDiv = document.createElement('div');
      prevNextDiv.classList.add('action-set');
      prevNextDiv.classList.add('previous-next');
      prevNextDiv.appendChild(pageLink(paginationLinks.previous, true, false));
      prevNextDiv.appendChild(pageLink(paginationLinks.next, false, true));

      // Put it all together.
      paginationDiv.appendChild(pageNumbersDiv);
      paginationDiv.appendChild(prevNextDiv);

      return paginationDiv;
    }

    function replaceTablePagination(paginationData) {
      const newPagination = getNewTablePagination(paginationData)
      if (tableData.tablePagination) {
        tableData.tablePagination.innerHTML = '';
        tableData.tablePagination.appendChild(newPagination);
      }
      bindTablePagination();
    }

    function bindTablePagination() {
      // Only bind if we're using an API endpoint.
      if (!tableData.apiEndpoint) {
        return;
      }
      const pageNumbers = tableData.tablePagination.querySelectorAll('a.item')
      pageNumbers.forEach(pageNumber => pageNumber.addEventListener('click', async ev => {
        ev.preventDefault();
        const target = ev.target;
        const page = Number(target.getAttribute('data-page-number') ?? tableData.tableState.page);
        await runApiCall(page)
        //sortTable(tableData.sortCol, true);
      }))
    }

    function replaceTableRows(rows, layout) {
      // TODO: for now we just assume we have one section.
      const section = tableData.sections[0];
      const toRemove = section.rows.map(row => row.el)
      toRemove.forEach(el => el.remove())
      const newRows = getNewTableRows(rows, layout)
      let refNode = section.section
      newRows.forEach(row => {
        refNode.parentNode.insertBefore(row, refNode.nextSibling)
        refNode = row;
      })
    }

    async function sortTable(n, keepSameSortOrder = false, waitForCall = true) {
      tableData.sortCol = n;
      const header = tableData.header[n]
      if (!keepSameSortOrder) {
        if (header.isActive) {
          header.direction = flipDirection(header.direction)
        }
      }
      if (tableData.apiEndpoint) {
        const call = runApiCall(tableData.tableState.page, header.slug, header.direction)
        if (waitForCall) {
          await call
        }
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
        const slug = col.getAttribute('data-slug') ? col.getAttribute('data-slug') : text.toLowerCase().replaceAll(' ', '_')
        const dataType = col.getAttribute('data-type') ? col.getAttribute('data-type') : 'string'
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
        let header = tableData.header[n]
        if (header == null) {
          header = {
            text: '',
            slug: '',
            dataType: '',
            isActive: false,
            direction: 'asc',
            el: col,
            n,
          }
        }
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
      const sectionRows = [...table.querySelectorAll('tr.section'), ...table.querySelectorAll('tr.separator:not(:has( + .section))')]
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

    function selectDefaultSort() {
      if (!tableData.defaultSort) {
        return
      }
      const n = tableData.header.findIndex(col => col.slug === tableData.defaultSort)
      if (n < 0) {
        return
      }
      sortTable(n, false, false)
    }

    function getTableMeta() {
      const defaultSort = table.getAttribute('data-default-sort')
      const defaultDirection = table.getAttribute('data-default-direction')
      const apiEndpoint = table.getAttribute('data-api-endpoint')
      const apiBaseURL = table.getAttribute('data-api-base-url')
      tableData.defaultSort = defaultSort
      tableData.apiEndpoint = apiEndpoint
      tableData.apiBaseURL = apiBaseURL

      const tablePagination = table.nextElementSibling && table.nextElementSibling.classList.contains('pagination')
        ? table.nextElementSibling
        : null;
      tableData.tablePagination = tablePagination;
      tableData.pageBaseURL = new URL(document.URL)

      tableData.tableState.page = Number(tableData.pageBaseURL.searchParams.get('page') ?? '1');
      tableData.tableState.slug = defaultSort;
      tableData.tableState.direction = defaultDirection;
    }

    tableData.header = getHeaderCols()
    tableData.sections = getRowSections()
    getTableMeta()
    selectDefaultSort()
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
 * Decorates dynamic content for infoboxes.
 */
function decorateInfoBoxes() {
  const infoboxes = [...document.querySelectorAll('.tc-infobox')]
  infoboxes.forEach(infobox => {
    const labels = [...infobox.querySelectorAll('.label-items .label-item')]
    labels.forEach((label, n) => {
      label.addEventListener('click', ev => {
        ev.preventDefault()
        for (let n = 0; n < labels.length; ++n) {
          infobox.classList.remove(`viewing-image-${n + 1}`)
        }
        infobox.classList.add(`viewing-image-${n + 1}`)
      })
    })
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
  decorateInfoBoxes()
}

main()
