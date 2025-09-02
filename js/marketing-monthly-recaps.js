/**
 * Marketing Monthly Recaps Report JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeMarketingRecapReport();
});

function initializeMarketingRecapReport() {
    // Initialize campaign selection
    initializeCampaignSelection();
    
    // Initialize drag and drop
    initializeDragAndDrop();
    
    // Load pre-selected campaigns
    loadSelectedCampaigns();
    
    // Export CSV functionality
    const exportCsvBtn = document.getElementById('export-csv');
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            exportTableToCSV();
        });
    }
    
    // Copy table to clipboard
    const copyTableBtn = document.getElementById('copy-table');
    if (copyTableBtn) {
        copyTableBtn.addEventListener('click', function() {
            copyTableToClipboard();
        });
    }
    
    // Note: Month/year selectors removed - using existing report date filter instead
}

function exportTableToCSV() {
    const table = document.querySelector('.recap-table');
    if (!table) {
        alert('No table found to export');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        let rowData = [];
        const cells = row.querySelectorAll('th, td');
        for (let cell of cells) {
            // Clean up the cell content and handle quotes
            let cellText = cell.textContent.trim();
            cellText = cellText.replace(/"/g, '""'); // Escape quotes
            rowData.push('"' + cellText + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    // Get current date for filename
    const today = new Date();
    const month = today.getMonth() + 1;
    const year = today.getFullYear();
    
    link.href = url;
    link.download = `marketing-recap-${year}-${month.toString().padStart(2, '0')}.csv`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function copyTableToClipboard() {
    const table = document.querySelector('.recap-table');
    if (!table) {
        alert('No table found to copy');
        return;
    }
    
    // Create a range and select the table
    const range = document.createRange();
    range.selectNode(table);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('Table copied to clipboard!');
        } else {
            fallbackCopyTable(table);
        }
    } catch (err) {
        console.error('Copy failed:', err);
        fallbackCopyTable(table);
    }
    
    // Clear selection
    window.getSelection().removeAllRanges();
}

function fallbackCopyTable(table) {
    // Fallback method using textarea
    let tableText = '';
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        let rowText = [];
        const cells = row.querySelectorAll('th, td');
        for (let cell of cells) {
            rowText.push(cell.textContent.trim());
        }
        tableText += rowText.join('\t') + '\n';
    }
    
    const textarea = document.createElement('textarea');
    textarea.value = tableText;
    textarea.style.position = 'fixed';
    textarea.style.left = '-999999px';
    textarea.style.top = '-999999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    
    try {
        document.execCommand('copy');
        alert('Table copied to clipboard!');
    } catch (err) {
        alert('Failed to copy table. Please select and copy manually.');
    }
    
    document.body.removeChild(textarea);
}

// Campaign Selection Functions
function initializeCampaignSelection() {
    const searchInput = document.getElementById('campaign-search');
    const searchResults = document.getElementById('search-results');
    const selectAllBtn = document.getElementById('select-all-campaigns');
    const clearAllBtn = document.getElementById('clear-campaigns');
    
    if (!searchInput || !searchResults) return;
    
    // Search functionality with debounce
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (query.length < 1) {
            searchResults.classList.remove('show');
            return;
        }
        
        // Debounce search for better performance
        searchTimeout = setTimeout(() => {
            showSearchResults(query);
        }, 200);
    });
    
    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) {
            searchResults.classList.remove('show');
        }
    });
    
    // Select All button
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            if (window.allTriggeredCampaigns) {
                window.allTriggeredCampaigns.forEach(campaign => {
                    addCampaign(campaign.id, campaign.name);
                });
            }
        });
    }
    
    // Clear All button
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            clearAllCampaigns();
        });
    }
}

function showSearchResults(query) {
    const searchResults = document.getElementById('search-results');
    if (!searchResults || !window.allTriggeredCampaigns) return;
    
    const filteredCampaigns = window.allTriggeredCampaigns.filter(campaign => 
        campaign.name.toLowerCase().includes(query)
    );
    
    if (filteredCampaigns.length === 0) {
        searchResults.innerHTML = '<div class="search-result-item">No campaigns found</div>';
    } else {
        searchResults.innerHTML = filteredCampaigns.map(campaign => {
            const isSelected = isAlreadySelected(campaign.id);
            const statusText = isSelected ? 'Selected' : 'Click to add';
            const statusClass = isSelected ? 'already-selected' : '';
            
            return `
                <div class="search-result-item ${statusClass}" data-campaign-id="${campaign.id}" data-campaign-name="${escapeHtml(campaign.name)}">
                    <span class="campaign-name">${escapeHtml(campaign.name)}</span>
                    <span class="search-result-status">${statusText}</span>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        searchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-campaign-id');
                const campaignName = this.getAttribute('data-campaign-name');
                
                if (campaignId && campaignName) {
                    if (isAlreadySelected(campaignId)) {
                        removeCampaign(campaignId);
                    } else {
                        addCampaign(campaignId, campaignName);
                    }
                    
                    // Refresh search results to update status
                    const searchInput = document.getElementById('campaign-search');
                    if (searchInput && searchInput.value.trim()) {
                        showSearchResults(searchInput.value.trim().toLowerCase());
                    }
                }
            });
        });
    }
    
    searchResults.classList.add('show');
}

function addCampaign(campaignId, campaignName) {
    if (isAlreadySelected(campaignId)) return;
    
    const selectedList = document.getElementById('selected-campaigns-list');
    const selectedCount = document.getElementById('selected-count');
    const campaignsInput = document.getElementById('campaigns-input');
    
    if (!selectedList || !selectedCount || !campaignsInput) return;
    
    // Remove "no campaigns" message if it exists
    const noMsg = selectedList.querySelector('.no-campaigns-msg');
    if (noMsg) {
        noMsg.remove();
        selectedList.classList.add('has-campaigns');
    }
    
    // Create campaign item with drag and drop attributes
    const campaignItem = document.createElement('div');
    campaignItem.className = 'selected-campaign-item';
    campaignItem.setAttribute('data-campaign-id', campaignId);
    campaignItem.setAttribute('draggable', 'true');
    campaignItem.innerHTML = `
        <span class="selected-campaign-name">${escapeHtml(campaignName)}</span>
        <button type="button" class="remove-campaign-btn" onclick="removeCampaign('${campaignId}')">Remove</button>
    `;
    
    // Add drag event listeners
    addDragEventListeners(campaignItem);
    
    selectedList.appendChild(campaignItem);
    
    // Update hidden input and count
    updateSelectedCampaigns();
}

function removeCampaign(campaignId) {
    const selectedList = document.getElementById('selected-campaigns-list');
    const campaignItem = selectedList.querySelector(`[data-campaign-id="${campaignId}"]`);
    
    if (campaignItem) {
        campaignItem.remove();
        updateSelectedCampaigns();
        
        // Add back "no campaigns" message if needed
        if (selectedList.children.length === 0) {
            selectedList.classList.remove('has-campaigns');
            selectedList.innerHTML = '<p class="no-campaigns-msg">No campaigns selected. Search and click campaigns above to add them.</p>';
        }
    }
}

function clearAllCampaigns() {
    const selectedList = document.getElementById('selected-campaigns-list');
    const selectedCount = document.getElementById('selected-count');
    const campaignsInput = document.getElementById('campaigns-input');
    
    if (selectedList) {
        selectedList.classList.remove('has-campaigns');
        selectedList.innerHTML = '<p class="no-campaigns-msg">No campaigns selected. Search and click campaigns above to add them.</p>';
    }
    
    if (selectedCount) {
        selectedCount.textContent = '0';
    }
    
    if (campaignsInput) {
        campaignsInput.value = '';
    }
}

function updateSelectedCampaigns() {
    const selectedList = document.getElementById('selected-campaigns-list');
    const selectedCount = document.getElementById('selected-count');
    const campaignsInput = document.getElementById('campaigns-input');
    
    if (!selectedList || !selectedCount || !campaignsInput) return;
    
    const campaignItems = selectedList.querySelectorAll('.selected-campaign-item');
    const campaignIds = Array.from(campaignItems).map(item => 
        item.getAttribute('data-campaign-id')
    ).filter(id => id);
    
    selectedCount.textContent = campaignIds.length;
    campaignsInput.value = campaignIds.join(',');
}

function isAlreadySelected(campaignId) {
    const selectedList = document.getElementById('selected-campaigns-list');
    return selectedList && selectedList.querySelector(`[data-campaign-id="${campaignId}"]`) !== null;
}

function loadSelectedCampaigns() {
    if (!window.selectedCampaignIds || !window.allTriggeredCampaigns) return;
    
    // Create a map for faster lookup
    const campaignMap = {};
    window.allTriggeredCampaigns.forEach(campaign => {
        campaignMap[campaign.id] = campaign.name;
    });
    
    // Add pre-selected campaigns in the order they appear in the URL
    window.selectedCampaignIds.forEach(campaignId => {
        const campaignName = campaignMap[campaignId];
        if (campaignName) {
            addCampaign(campaignId, campaignName);
        }
    });
    
    // Ensure drag listeners are applied to loaded campaigns
    setTimeout(() => {
        reapplyDragListeners();
    }, 100);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Drag and Drop Functions
let draggedElement = null;

function initializeDragAndDrop() {
    const selectedList = document.getElementById('selected-campaigns-list');
    if (!selectedList) return;
    
    // Add dragover and drop event listeners to the container
    selectedList.addEventListener('dragover', handleDragOver);
    selectedList.addEventListener('drop', handleDrop);
}

function addDragEventListeners(element) {
    element.addEventListener('dragstart', handleDragStart);
    element.addEventListener('dragend', handleDragEnd);
    element.addEventListener('dragenter', handleDragEnter);
    element.addEventListener('dragleave', handleDragLeave);
}

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.outerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    
    // Clean up all drag-over classes
    const selectedList = document.getElementById('selected-campaigns-list');
    if (selectedList) {
        selectedList.querySelectorAll('.selected-campaign-item').forEach(item => {
            item.classList.remove('drag-over');
        });
    }
    
    draggedElement = null;
}

function handleDragEnter(e) {
    if (this !== draggedElement) {
        this.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    // Only remove if we're actually leaving the element (not entering a child)
    if (!this.contains(e.relatedTarget)) {
        this.classList.remove('drag-over');
    }
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault(); // Allows us to drop
    }
    
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation(); // Stops some browsers from redirecting
    }
    
    if (draggedElement !== this) {
        const targetElement = e.target.closest('.selected-campaign-item');
        
        if (targetElement && targetElement !== draggedElement) {
            const selectedList = document.getElementById('selected-campaigns-list');
            const allItems = Array.from(selectedList.querySelectorAll('.selected-campaign-item'));
            const draggedIndex = allItems.indexOf(draggedElement);
            const targetIndex = allItems.indexOf(targetElement);
            
            if (draggedIndex !== -1 && targetIndex !== -1) {
                // Remove dragged element from DOM
                draggedElement.remove();
                
                // Insert at new position
                if (draggedIndex < targetIndex) {
                    // Moving down - insert after target
                    targetElement.parentNode.insertBefore(draggedElement, targetElement.nextSibling);
                } else {
                    // Moving up - insert before target
                    targetElement.parentNode.insertBefore(draggedElement, targetElement);
                }
                
                // Update the order in hidden input
                updateSelectedCampaigns();
            }
        }
    }
    
    return false;
}

// Re-add drag listeners when campaigns are loaded from URL
function reapplyDragListeners() {
    const selectedList = document.getElementById('selected-campaigns-list');
    if (selectedList) {
        selectedList.querySelectorAll('.selected-campaign-item').forEach(item => {
            if (!item.getAttribute('draggable')) {
                item.setAttribute('draggable', 'true');
                addDragEventListeners(item);
            }
        });
    }
}

// Utility function to format numbers
function formatNumber(num, decimals = 0) {
    if (isNaN(num) || num === null || num === undefined) {
        return '0';
    }
    return Number(num).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// Utility function to format currency
function formatCurrency(amount) {
    if (isNaN(amount) || amount === null || amount === undefined) {
        return '$0.00';
    }
    return '$' + Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + E for export
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        const exportBtn = document.getElementById('export-csv');
        if (exportBtn) {
            exportBtn.click();
        }
    }
    
    // Ctrl/Cmd + A for select all campaigns (when campaign select is focused)
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        const campaignSelect = document.getElementById('campaign-select');
        if (document.activeElement === campaignSelect) {
            e.preventDefault();
            const selectAllBtn = document.getElementById('select-all-campaigns');
            if (selectAllBtn) {
                selectAllBtn.click();
            }
        }
    }
});
