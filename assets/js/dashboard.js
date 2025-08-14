/* ===================== RB Stores — Enhanced Dashboard JavaScript =====================
   Interactive enhancements for better user experience
================================================================================== */

document.addEventListener('DOMContentLoaded', function() {
  
  /* ---------- Enhanced Tooltips ---------- */
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
      animation: true,
      delay: { show: 300, hide: 100 }
    });
  });

  /* ---------- Enhanced Table Filtering ---------- */
  function setupTableFilter(searchId, tableSelector, statusFilterId = null) {
    const searchInput = document.getElementById(searchId);
    const statusFilter = statusFilterId ? document.getElementById(statusFilterId) : null;
    const tableRows = document.querySelectorAll(`${tableSelector} tbody tr`);
    
    // Check if we have actual data rows (not just "no data" messages)
    const hasData = Array.from(tableRows).some(row => !row.querySelector('td[colspan]'));
    
    if (searchInput) searchInput.disabled = !hasData;
    if (statusFilter) statusFilter.disabled = !hasData;
    
    function filterTable() {
      const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
      const statusTerm = statusFilter ? statusFilter.value.toLowerCase().trim() : '';
      
      let visibleCount = 0;
      
      tableRows.forEach(row => {
        // Skip "no data" rows
        if (row.querySelector('td[colspan]')) return;
        
        const rowText = row.innerText.toLowerCase();
        const statusBadge = row.querySelector('.badge');
        const rowStatus = statusBadge ? statusBadge.innerText.toLowerCase() : '';
        
        const matchesSearch = !searchTerm || rowText.includes(searchTerm);
        const matchesStatus = !statusTerm || rowStatus === statusTerm;
        
        if (matchesSearch && matchesStatus) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      // Show/hide "no results" message
      updateNoResultsMessage(tableSelector, visibleCount, hasData);
    }
    
    if (searchInput) {
      searchInput.addEventListener('input', debounce(filterTable, 300));
    }
    
    if (statusFilter) {
      statusFilter.addEventListener('change', filterTable);
    }
  }

  /* ---------- No Results Message ---------- */
  function updateNoResultsMessage(tableSelector, visibleCount, hasData) {
    const tbody = document.querySelector(`${tableSelector} tbody`);
    let noResultsRow = tbody.querySelector('.no-results-row');
    
    if (hasData && visibleCount === 0) {
      if (!noResultsRow) {
        const colCount = tbody.closest('table').querySelectorAll('thead th').length;
        noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
          <td colspan="${colCount}" class="text-center text-muted py-4">
            <i class="fa-solid fa-search me-2"></i>
            No results match your search criteria
          </td>
        `;
        tbody.appendChild(noResultsRow);
      }
      noResultsRow.style.display = '';
    } else if (noResultsRow) {
      noResultsRow.style.display = 'none';
    }
  }

  /* ---------- Setup Table Filters ---------- */
  setupTableFilter('orderSearch', '.recent-orders', 'orderStatusFilter');
  setupTableFilter('itemSearch', '.top-items');

  /* ---------- Enhanced Theme Switcher ---------- */
  const themeKey = 'rb_theme_preset';
  const themeSelect = document.getElementById('themePreset');
  const resetButton = document.getElementById('themeReset');

  const themePresets = {
    default: {
      name: 'Default (Bootstrap)',
      colors: null
    },
    ocean: {
      name: 'Ocean',
      colors: {
        primary: '#0ea5e9',
        success: '#10b981',
        warning: '#eab308',
        danger: '#ef4444',
        info: '#38bdf8',
        secondary: '#64748b'
      }
    },
    emerald: {
      name: 'Emerald',
      colors: {
        primary: '#059669',
        success: '#16a34a',
        warning: '#f59e0b',
        danger: '#dc2626',
        info: '#14b8a6',
        secondary: '#6b7280'
      }
    },
    crimson: {
      name: 'Crimson',
      colors: {
        primary: '#e11d48',
        success: '#22c55e',
        warning: '#f59e0b',
        danger: '#b91c1c',
        info: '#3b82f6',
        secondary: '#4b5563'
      }
    }
  };

  function getThemeStyleElement() {
    let styleEl = document.getElementById('rb-theme-vars');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'rb-theme-vars';
      document.head.appendChild(styleEl);
    }
    return styleEl;
  }

  function generateThemeCSS(preset) {
    if (!preset || !preset.colors) return '';
    
    const { colors } = preset;
    return `
      :root {
        --bs-primary: ${colors.primary};
        --bs-success: ${colors.success};
        --bs-warning: ${colors.warning};
        --bs-danger: ${colors.danger};
        --bs-info: ${colors.info};
        --bs-secondary: ${colors.secondary};
      }
      
      /* Enhanced button theming */
      .btn-primary {
        --bs-btn-bg: var(--bs-primary);
        --bs-btn-border-color: var(--bs-primary);
        --bs-btn-hover-bg: color-mix(in srgb, var(--bs-primary) 85%, #000 15%);
        --bs-btn-hover-border-color: color-mix(in srgb, var(--bs-primary) 85%, #000 15%);
        --bs-btn-active-bg: color-mix(in srgb, var(--bs-primary) 75%, #000 25%);
      }
      
      .btn-outline-primary {
        --bs-btn-color: var(--bs-primary);
        --bs-btn-border-color: var(--bs-primary);
        --bs-btn-hover-bg: var(--bs-primary);
      }
      
      /* Badge theming */
      .badge.text-bg-primary { background-color: var(--bs-primary) !important; }
      .badge.text-bg-success { background-color: var(--bs-success) !important; }
      .badge.text-bg-warning { background-color: var(--bs-warning) !important; }
      .badge.text-bg-danger { background-color: var(--bs-danger) !important; }
      .badge.text-bg-info { background-color: var(--bs-info) !important; }
      .badge.text-bg-secondary { background-color: var(--bs-secondary) !important; }
      
      /* Link theming */
      a, .link-primary { color: var(--bs-primary) !important; }
      
      /* KPI icon theming */
      main .kpi .text-primary .icon { 
        background: linear-gradient(135deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 80%, #000 20%)) !important;
      }
    `;
  }

  function applyTheme(themeName) {
    const styleEl = getThemeStyleElement();
    const preset = themePresets[themeName];
    
    styleEl.textContent = generateThemeCSS(preset);
    
    if (themeName && themeName !== 'default') {
      document.documentElement.setAttribute('data-theme', themeName);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    
    // Add visual feedback
    showThemeChangeNotification(preset ? preset.name : 'Default');
  }

  function showThemeChangeNotification(themeName) {
    // Remove existing notification
    const existing = document.querySelector('.theme-notification');
    if (existing) existing.remove();
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = 'theme-notification position-fixed top-0 end-0 m-3 p-3 bg-dark text-white rounded shadow';
    notification.style.cssText = 'z-index: 9999; transform: translateX(100%); transition: transform 0.3s ease;';
    notification.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-palette"></i>
        <span>Theme changed to: <strong>${themeName}</strong></span>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => notification.remove(), 300);
    }, 2000);
  }

  // Load saved theme
  const savedTheme = localStorage.getItem(themeKey) || 'default';
  applyTheme(savedTheme);
  if (themeSelect) themeSelect.value = savedTheme;

  // Theme change events
  if (themeSelect) {
    themeSelect.addEventListener('change', function() {
      const selectedTheme = this.value || 'default';
      applyTheme(selectedTheme);
      localStorage.setItem(themeKey, selectedTheme);
    });
  }

  if (resetButton) {
    resetButton.addEventListener('click', function() {
      applyTheme('default');
      if (themeSelect) themeSelect.value = 'default';
      localStorage.setItem(themeKey, 'default');
    });
  }

  /* ---------- Enhanced Card Animations ---------- */
  function observeCards() {
    const cards = document.querySelectorAll('main .card');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    });

    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
      observer.observe(card);
    });
  }

  /* ---------- Enhanced Number Animation ---------- */
  function animateNumbers() {
    const numberElements = document.querySelectorAll('.kpi .fs-4');
    
    numberElements.forEach(el => {
      const text = el.textContent.trim();
      const number = parseFloat(text.replace(/[^\d.-]/g, ''));
      
      if (!isNaN(number) && number > 0) {
        animateNumber(el, 0, number, 1000, text);
      }
    });
  }

  function animateNumber(element, start, end, duration, originalText) {
    const startTime = performance.now();
    const isMonetary = originalText.includes('Rs');
    const prefix = isMonetary ? 'Rs ' : '';
    const suffix = originalText.match(/,\d{2}$/) ? '' : '';
    
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function
      const easeOutQuart = 1 - Math.pow(1 - progress, 4);
      const current = start + (end - start) * easeOutQuart;
      
      let formattedNumber;
      if (isMonetary) {
        formattedNumber = new Intl.NumberFormat('en-LK').format(Math.round(current));
      } else {
        formattedNumber = Math.round(current).toLocaleString();
      }
      
      element.textContent = prefix + formattedNumber + suffix;
      
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        element.textContent = originalText; // Restore original formatting
      }
    }
    
    requestAnimationFrame(update);
  }

  /* ---------- Real-time Updates (Mock) ---------- */
  function setupRealTimeUpdates() {
    // Simulate real-time updates every 30 seconds
    setInterval(() => {
      const timestamp = document.querySelector('.hero .text-secondary');
      if (timestamp) {
        const now = new Date();
        const timeString = now.toISOString().slice(0, 19).replace('T', ' ');
        timestamp.innerHTML = timestamp.innerHTML.replace(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/, timeString);
      }
    }, 30000);
  }

  /* ---------- Enhanced Search with Keyboard Shortcuts ---------- */
  function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K to focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('orderSearch') || document.getElementById('itemSearch');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
      
      // Escape to clear search
      if (e.key === 'Escape') {
        const activeSearch = document.activeElement;
        if (activeSearch && (activeSearch.id === 'orderSearch' || activeSearch.id === 'itemSearch')) {
          activeSearch.value = '';
          activeSearch.dispatchEvent(new Event('input'));
          activeSearch.blur();
        }
      }
    });
  }

  /* ---------- Enhanced Loading States ---------- */
  function showLoadingState(tableSelector) {
    const tbody = document.querySelector(`${tableSelector} tbody`);
    if (tbody) {
      tbody.innerHTML = `
        <tr class="loading">
          <td colspan="100%" class="text-center py-4">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            Loading...
          </td>
        </tr>
      `;
    }
  }

  /* ---------- Utility Functions ---------- */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /* ---------- Initialize Enhancements ---------- */
  // Only run animations if user hasn't indicated they prefer reduced motion
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    observeCards();
    setTimeout(animateNumbers, 500); // Delay for better visual effect
  }
  
  setupRealTimeUpdates();
  setupKeyboardShortcuts();

  /* ---------- Enhanced Responsive Table Handling ---------- */
  function handleResponsiveTables() {
    const tables = document.querySelectorAll('main .table-responsive');
    
    tables.forEach(tableContainer => {
      const table = tableContainer.querySelector('table');
      if (!table) return;
      
      // Add horizontal scroll indicator
      const scrollIndicator = document.createElement('div');
      scrollIndicator.className = 'scroll-indicator position-absolute bottom-0 end-0 p-2 text-muted small';
      scrollIndicator.innerHTML = '<i class="fa-solid fa-arrows-left-right"></i> Scroll';
      scrollIndicator.style.cssText = 'opacity: 0; transition: opacity 0.3s ease; pointer-events: none; z-index: 10;';
      
      tableContainer.style.position = 'relative';
      tableContainer.appendChild(scrollIndicator);
      
      // Show/hide scroll indicator
      function updateScrollIndicator() {
        const canScrollHorizontally = tableContainer.scrollWidth > tableContainer.clientWidth;
        scrollIndicator.style.opacity = canScrollHorizontally ? '0.7' : '0';
      }
      
      updateScrollIndicator();
      window.addEventListener('resize', debounce(updateScrollIndicator, 250));
      
      // Add smooth scrolling with mouse wheel
      tableContainer.addEventListener('wheel', function(e) {
        if (e.shiftKey) {
          e.preventDefault();
          this.scrollLeft += e.deltaY;
        }
      });
    });
  }

  /* ---------- Enhanced Form Validation ---------- */
  function setupFormEnhancements() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
      const inputs = form.querySelectorAll('input, select, textarea');
      
      inputs.forEach(input => {
        // Add floating label effect
        if (input.placeholder && !input.closest('.input-group')) {
          const wrapper = document.createElement('div');
          wrapper.className = 'form-floating';
          input.parentNode.insertBefore(wrapper, input);
          wrapper.appendChild(input);
          
          const label = document.createElement('label');
          label.textContent = input.placeholder;
          label.setAttribute('for', input.id || '');
          wrapper.appendChild(label);
          
          input.placeholder = '';
        }
        
        // Real-time validation feedback
        input.addEventListener('blur', function() {
          validateField(this);
        });
        
        input.addEventListener('input', function() {
          if (this.classList.contains('is-invalid')) {
            validateField(this);
          }
        });
      });
    });
  }

  function validateField(field) {
    const isValid = field.checkValidity();
    field.classList.toggle('is-valid', isValid);
    field.classList.toggle('is-invalid', !isValid);
    
    // Remove existing feedback
    const existingFeedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
    if (existingFeedback) {
      existingFeedback.remove();
    }
    
    // Add new feedback
    if (!isValid) {
      const feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      feedback.textContent = field.validationMessage || 'Please provide a valid value.';
      field.parentNode.appendChild(feedback);
    }
  }

  /* ---------- Enhanced Data Export ---------- */
  function setupDataExport() {
    const tables = document.querySelectorAll('main .table');
    
    tables.forEach(table => {
      const cardHeader = table.closest('.card')?.querySelector('.card-header');
      if (!cardHeader) return;
      
      const exportBtn = document.createElement('button');
      exportBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
      exportBtn.innerHTML = '<i class="fa-solid fa-download me-1"></i>Export';
      exportBtn.setAttribute('data-bs-toggle', 'tooltip');
      exportBtn.setAttribute('title', 'Export table data as CSV');
      
      exportBtn.addEventListener('click', function() {
        exportTableToCSV(table);
      });
      
      const titleElement = cardHeader.querySelector('h4, h6');
      if (titleElement) {
        titleElement.parentNode.appendChild(exportBtn);
        
        // Initialize tooltip
        new bootstrap.Tooltip(exportBtn);
      }
    });
  }

  function exportTableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(row => {
      const cells = row.querySelectorAll('th, td');
      const rowData = Array.from(cells).map(cell => {
        // Clean cell content (remove HTML, extra whitespace)
        let text = cell.textContent.trim();
        // Escape quotes and wrap in quotes if contains comma
        if (text.includes(',') || text.includes('"')) {
          text = '"' + text.replace(/"/g, '""') + '"';
        }
        return text;
      });
      
      if (rowData.some(cell => cell.length > 0)) {
        csvContent.push(rowData.join(','));
      }
    });
    
    if (csvContent.length === 0) return;
    
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `rb-stores-data-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Show success notification
    showNotification('Data exported successfully!', 'success');
  }

  /* ---------- Enhanced Notifications ---------- */
  function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification position-fixed top-0 end-0 m-3 shadow`;
    notification.style.cssText = 'z-index: 9999; transform: translateX(100%); transition: transform 0.3s ease; max-width: 300px;';
    notification.innerHTML = `
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="fa-solid fa-${getIconForType(type)}"></i>
          <span>${message}</span>
        </div>
        <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Close button functionality
    const closeBtn = notification.querySelector('.btn-close');
    closeBtn.addEventListener('click', () => removeNotification(notification));
    
    // Auto remove
    if (duration > 0) {
      setTimeout(() => removeNotification(notification), duration);
    }
  }

  function removeNotification(notification) {
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }

  function getIconForType(type) {
    const icons = {
      success: 'check-circle',
      warning: 'triangle-exclamation',
      danger: 'circle-exclamation',
      info: 'circle-info'
    };
    return icons[type] || 'circle-info';
  }

  /* ---------- Enhanced Dark Mode Toggle ---------- */
  function setupDarkModeToggle() {
    const darkModeBtn = document.createElement('button');
    darkModeBtn.className = 'btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3 rounded-circle';
    darkModeBtn.style.cssText = 'width: 50px; height: 50px; z-index: 1000; transition: all 0.3s ease;';
    darkModeBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    darkModeBtn.setAttribute('data-bs-toggle', 'tooltip');
    darkModeBtn.setAttribute('title', 'Toggle dark mode');
    
    document.body.appendChild(darkModeBtn);
    
    // Initialize tooltip
    new bootstrap.Tooltip(darkModeBtn);
    
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
      toggleDarkMode(true);
    }
    
    darkModeBtn.addEventListener('click', function() {
      const currentDarkMode = document.documentElement.hasAttribute('data-dark-mode');
      toggleDarkMode(!currentDarkMode);
      localStorage.setItem('darkMode', !currentDarkMode);
    });
  }

  function toggleDarkMode(enable) {
    const icon = document.querySelector('.position-fixed .fa-moon, .position-fixed .fa-sun');
    
    if (enable) {
      document.documentElement.setAttribute('data-dark-mode', '');
      if (icon) {
        icon.className = 'fa-solid fa-sun';
      }
    } else {
      document.documentElement.removeAttribute('data-dark-mode');
      if (icon) {
        icon.className = 'fa-solid fa-moon';
      }
    }
  }

  /* ---------- Performance Monitoring ---------- */
  function setupPerformanceMonitoring() {
    // Monitor page load performance
    window.addEventListener('load', function() {
      setTimeout(() => {
        const perfData = performance.getEntriesByType('navigation')[0];
        const loadTime = Math.round(perfData.loadEventEnd - perfData.loadEventStart);
        
        if (loadTime > 3000) {
          console.warn('Dashboard loaded slowly:', loadTime + 'ms');
        }
        
        // Optional: Send performance data to analytics
        // sendPerformanceData({ loadTime, timestamp: new Date().toISOString() });
      }, 1000);
    });
  }

  /* ---------- Initialize All Enhancements ---------- */
  handleResponsiveTables();
  setupFormEnhancements();
  setupDataExport();
  setupDarkModeToggle();
  setupPerformanceMonitoring();

  /* ---------- Final Initialization Message ---------- */
  console.log('🚀 RB Stores Dashboard Enhanced - All features loaded successfully!');
  
  // Add subtle loading complete indicator
  setTimeout(() => {
    const loadingIndicator = document.querySelector('.loading-overlay');
    if (loadingIndicator) {
      loadingIndicator.style.opacity = '0';
      setTimeout(() => loadingIndicator.remove(), 300);
    }
  }, 1000);

}); // End DOMContentLoaded

/* ---------- Additional Utility Functions (Global Scope) ---------- */

// Global function to refresh dashboard data
window.refreshDashboard = function() {
  showNotification('Refreshing dashboard...', 'info', 1000);
  
  // Simulate data refresh
  setTimeout(() => {
    location.reload();
  }, 1000);
};

// Global function to print dashboard
window.printDashboard = function() {
  // Hide interactive elements before printing
  const hideElements = document.querySelectorAll('.btn, .form-control, .form-select, .alert');
  hideElements.forEach(el => el.style.display = 'none');
  
  window.print();
  
  // Restore elements after printing
  setTimeout(() => {
    hideElements.forEach(el => el.style.display = '');
  }, 1000);
};

// Global keyboard shortcuts info
window.showKeyboardShortcuts = function() {
  const shortcuts = `
    <div class="p-3">
      <h6>Keyboard Shortcuts</h6>
      <div class="row g-2 small">
        <div class="col-6"><kbd>Ctrl/Cmd + K</kbd></div>
        <div class="col-6">Focus search</div>
        <div class="col-6"><kbd>Esc</kbd></div>
        <div class="col-6">Clear search</div>
        <div class="col-6"><kbd>Shift + Scroll</kbd></div>
        <div class="col-6">Horizontal scroll</div>
      </div>
    </div>
  `;
  
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.innerHTML = `
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        ${shortcuts}
        <div class="modal-footer p-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  const bootstrapModal = new bootstrap.Modal(modal);
  bootstrapModal.show();
  
  modal.addEventListener('hidden.bs.modal', () => {
    document.body.removeChild(modal);
  });
};